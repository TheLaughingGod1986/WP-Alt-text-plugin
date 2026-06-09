import { test, expect, type Page, type Response } from '@playwright/test';
import { spawnSync } from 'child_process';

const BASE = (process.env.BBAI_E2E_BASE_URL || process.env.WP_BASE_URL || '').replace(/\/$/, '');
const WP_USER = process.env.BBAI_E2E_ADMIN_USER || process.env.WP_ADMIN_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_ADMIN_PASS || process.env.WP_ADMIN_PASS || 'password';
const RUN_CONNECTED_GENERATION =
  process.env.BBAI_E2E_CONNECTED_ENTITLEMENT === '1' &&
  process.env.BBAI_E2E_RUN_CONNECTED_GENERATION === '1';
const LIBRARY_URL = `${BASE}/wp-admin/admin.php?page=bbai-library`;
const DASHBOARD_URL = `${BASE}/wp-admin/admin.php?page=bbai`;

type EntitlementState = {
  tokens_remaining?: number;
  can_generate?: boolean;
  quota_state?: string;
};

function seedWpEnvImageFixture() {
  if (!BASE || process.env.BBAI_E2E_SKIP_LIBRARY_SEED === '1') {
    return;
  }
  const ps = spawnSync('docker', ['ps', '--format', '{{.Names}}'], { encoding: 'utf8' });
  const container = (ps.stdout || '')
    .split(/\r?\n/)
    .find((name) => name.trim().endsWith('-cli-1'));
  if (!container) {
    return;
  }
  const php = `
$title = 'bbai-entitlement-connected-fixture';
$existing = get_page_by_title( $title, OBJECT, 'attachment' );
if ( ! $existing ) {
	$upload = wp_upload_dir();
	$file = trailingslashit( $upload['path'] ) . $title . '.png';
	file_put_contents( $file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $file );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
} else {
	$id = (int) $existing->ID;
}
delete_post_meta( $id, '_wp_attachment_image_alt' );
`;
  spawnSync('docker', ['exec', container.trim(), 'wp', '--path=/var/www/html', 'eval', php], { encoding: 'utf8' });
}

function findEntitlement(payload: unknown, depth = 0): EntitlementState | null {
  if (!payload || typeof payload !== 'object' || depth > 6) {
    return null;
  }
  const value = payload as Record<string, unknown>;
  if (value.entitlement_state && typeof value.entitlement_state === 'object') {
    return value.entitlement_state as EntitlementState;
  }
  for (const key of ['data', 'result', 'job', 'payload', 'response', 'usage']) {
    const found = findEntitlement(value[key], depth + 1);
    if (found) {
      return found;
    }
  }
  return null;
}

function mayContainEntitlement(response: Response): boolean {
  const url = response.url();
  return url.includes('/wp-json/bbai/') || url.includes('/admin-ajax.php');
}

async function entitlementFromResponse(response: Response): Promise<EntitlementState | null> {
  if (!mayContainEntitlement(response)) {
    return null;
  }
  try {
    return findEntitlement(await response.json());
  } catch {
    return null;
  }
}

async function waitForExhaustedResponse(page: Page): Promise<EntitlementState> {
  const response = await page.waitForResponse(async (candidate) => {
    const entitlement = await entitlementFromResponse(candidate);
    return !!entitlement && entitlement.tokens_remaining === 0 && entitlement.can_generate === false;
  }, { timeout: 90_000 });
  const entitlement = await entitlementFromResponse(response);
  expect(entitlement, 'expected canonical entitlement_state in a WordPress proxy response').not.toBeNull();
  return entitlement as EntitlementState;
}

async function login(page: Page) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);
  }
  await expect(page).toHaveURL(/wp-admin/);
}

async function gotoLibrary(page: Page, refreshEntitlements = true) {
  await page.goto(LIBRARY_URL, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-nai-screen="library"]')).toBeVisible();
  if (refreshEntitlements) {
    await page.evaluate(async () => {
      await (window as any).BBAIEntitlements.refresh();
    });
  }
}

async function readEntitlement(page: Page): Promise<EntitlementState> {
  return page.evaluate(() => (window as any).BBAIEntitlements.get());
}

async function assertLibraryExhausted(page: Page) {
  await expect(page.locator('[data-bbai-entitlement-exhausted]')).toBeVisible();
  await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-bbai-entitlement-locked', '1');
  await expect(page.locator('[data-action="generate-selected"]')).toHaveAttribute('data-bbai-action', 'open-upgrade');
  await expect(page.locator('[data-action="edit-alt-inline"]').first()).not.toHaveAttribute('aria-disabled', 'true');
  await expect(page.locator('[data-action="preview-image"]').first()).toBeVisible();
  const conflict = await page.evaluate(() => {
    const visible = (element: Element | null) => {
      if (!(element instanceof HTMLElement)) return false;
      return !element.hidden && getComputedStyle(element).display !== 'none';
    };
    return {
      loadingAndEmpty: visible(document.querySelector('[data-bbai-library-loading]')) &&
        visible(document.querySelector('[data-bbai-library-empty-state], .bbai-library-filter-empty, .bbai-table-empty')),
      emptyAndPagination: visible(document.querySelector('[data-bbai-library-empty-state], .bbai-library-filter-empty, .bbai-table-empty')) &&
        visible(document.querySelector('[data-bbai-library-pagination]')),
    };
  });
  expect(conflict).toEqual({ loadingAndEmpty: false, emptyAndPagination: false });
}

test.describe.serial('nAi entitlements - connected backend exhaustion', () => {
  test.skip(!BASE || !RUN_CONNECTED_GENERATION,
    'Set BBAI_E2E_BASE_URL, BBAI_E2E_CONNECTED_ENTITLEMENT=1, and BBAI_E2E_RUN_CONNECTED_GENERATION=1 for the prepared one-credit connected account.');

  let finalCreditConsumed = false;
  let fixtureSkipReason = '';

  test.beforeEach(async ({ page }) => {
    seedWpEnvImageFixture();
    await login(page);
  });

  test('one real UI generation consumes the final credit and exhausts Dashboard and Library', async ({ page }) => {
    test.setTimeout(150_000);
    await gotoLibrary(page);

    const initial = await readEntitlement(page);
    if (initial.tokens_remaining !== 1 || initial.can_generate !== true) {
      fixtureSkipReason = `Connected fixture is not prepared: expected exactly one remaining credit with generation allowed; received remaining=${String(initial.tokens_remaining)}, can_generate=${String(initial.can_generate)}. Run scripts/check-connected-entitlement-fixture.js before this suite.`;
      test.skip(true, fixtureSkipReason);
    }
    expect(initial.tokens_remaining).toBe(1);
    expect(initial.can_generate).toBe(true);

    const button = page.locator('.bbai-library-row [data-action="regenerate-single"]').first();
    await expect(button).toBeVisible();
    await expect(button).not.toHaveAttribute('data-bbai-entitlement-locked', '1');

    const exhaustedResponse = waitForExhaustedResponse(page);
    await button.click();
    const responseState = await exhaustedResponse;
    expect(responseState.quota_state).toBe('exhausted');

    await expect.poll(async () => (await readEntitlement(page)).tokens_remaining).toBe(0);
    await expect.poll(async () => (await readEntitlement(page)).can_generate).toBe(false);
    await assertLibraryExhausted(page);

    await page.goto(DASHBOARD_URL, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-bbai-entitlement-exhausted]')).toBeVisible();
    await expect(page.locator('.nai-app')).toHaveAttribute('data-bbai-entitlement-remaining-value', '0');

    await gotoLibrary(page);
    await assertLibraryExhausted(page);
    finalCreditConsumed = true;
  });

  test('an exhausted backend rejects a replayed stale generation capability and canonical state wins', async ({ page }) => {
    test.setTimeout(150_000);
    test.skip(!finalCreditConsumed, fixtureSkipReason || 'Quota denial requires the preceding final-credit test to exhaust the prepared fixture.');
    await gotoLibrary(page);
    await expect.poll(async () => (await readEntitlement(page)).tokens_remaining).toBe(0);

    const analyticsEvents = await page.evaluate(() => {
      const events: string[] = [];
      document.addEventListener('bbai:analytics', (event: Event) => {
        const detail = (event as CustomEvent).detail || {};
        events.push(String(detail.event || ''));
      });
      (window as any).__bbaiConnectedEntitlementEvents = events;
      return events;
    });
    expect(analyticsEvents).toEqual([]);

    // Reproduce the stale Library fallback: UI claims a credit while backend is exhausted.
    await page.evaluate(() => {
      (window as any).BBAIEntitlements.merge({
        tokens_remaining: 1,
        token_limit: 50,
        tokens_used_this_month: 49,
        can_generate: true,
        quota_state: 'active',
        upgrade_required: false,
      }, 'stale_legacy_library_fixture');
    });
    const button = page.locator('.bbai-library-row [data-action="regenerate-single"]').first();
    await expect(button).not.toHaveAttribute('data-bbai-entitlement-locked', '1');

    const deniedResponse = waitForExhaustedResponse(page);
    await button.click();
    await deniedResponse;
    await assertLibraryExhausted(page);

    // The now-locked UI records one blocked intent without issuing another generation call.
    await button.click();
    await expect.poll(async () => page.evaluate(() =>
      ((window as any).__bbaiConnectedEntitlementEvents as string[])
        .filter((name) => name === 'generation_blocked_no_credits').length
    )).toBe(1);
    await expect.poll(async () => (await readEntitlement(page)).can_generate).toBe(false);
  });
});

test.describe('nAi entitlements - terminal bulk poll contract', () => {
  test.skip(!BASE, 'Set BBAI_E2E_BASE_URL to run the live wp-admin terminal-job bridge contract.');

  test('nested terminal job entitlement_state immediately locks generation controls', async ({ page }) => {
    seedWpEnvImageFixture();
    await login(page);
    await gotoLibrary(page, false);

    await page.evaluate(() => {
      (window as any).BBAIEntitlements.merge({
        tokens_remaining: 2,
        token_limit: 50,
        tokens_used_this_month: 48,
        can_generate: true,
        quota_state: 'active',
      }, 'bulk_contract_start');
    });

    await page.route('**/admin-ajax.php', async (route, request) => {
      if ((request.postData() || '').includes('action=beepbeepai_bulk_job_poll')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              job: {
                id: 'contract-terminal-job',
                status: 'completed',
                entitlement_state: {
                  plan: 'free',
                  plan_type: 'free',
                  token_limit: 50,
                  tokens_used_this_month: 50,
                  total_tokens_used: 50,
                  tokens_remaining: 0,
                  can_generate: false,
                  can_autopilot: false,
                  is_logged_in: true,
                  upgrade_required: true,
                  quota_state: 'exhausted',
                  message: 'Monthly credits exhausted.',
                },
              },
              updated_images: [],
            },
          }),
        });
        return;
      }
      await route.continue();
    });

    await page.evaluate(async () => {
      await new Promise<void>((resolve) => {
        (window as any).jQuery.ajax({
          url: (window as any).bbai_ajax.ajax_url,
          method: 'POST',
          dataType: 'json',
          data: {
            action: 'beepbeepai_bulk_job_poll',
            job_id: 'contract-terminal-job',
            nonce: (window as any).bbai_ajax.nonce,
          },
        }).always(() => resolve());
      });
    });

    await expect.poll(async () => (await readEntitlement(page)).tokens_remaining).toBe(0);
    await assertLibraryExhausted(page);
  });
});
