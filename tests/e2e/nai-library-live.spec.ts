import { test, expect, type Page } from '@playwright/test';
import { spawnSync } from 'child_process';

const BASE = (process.env.BBAI_E2E_BASE_URL || '').replace(/\/$/, '');
const WP_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';
const LIBRARY_URL = `${BASE}/wp-admin/admin.php?page=bbai-library`;

type ErrorCapture = {
  consoleErrors: string[];
  pageErrors: string[];
};

const criticalConsoleAllowlist = [
  /favicon\.ico/i,
  /Failed to load resource.*favicon/i,
];

function findWpCliContainer(): string {
  if (process.env.BBAI_E2E_WP_CLI_CONTAINER) {
    return process.env.BBAI_E2E_WP_CLI_CONTAINER;
  }

  const ps = spawnSync('docker', ['ps', '--format', '{{.Names}}'], { encoding: 'utf8' });
  if (ps.status !== 0) {
    return '';
  }

  return ps.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .find((name) => name.endsWith('-cli-1')) || '';
}

function runWpCli(args: string[]): { status: number | null; stdout: string; stderr: string } {
  const container = findWpCliContainer();
  if (!container) {
    return { status: 1, stdout: '', stderr: 'No wp-env CLI container found.' };
  }

  const result = spawnSync('docker', ['exec', container, 'wp', '--path=/var/www/html', ...args], {
    encoding: 'utf8',
  });

  return {
    status: result.status,
    stdout: result.stdout || '',
    stderr: result.stderr || '',
  };
}

function seedLiveLibraryFixtures() {
  if (!BASE || process.env.BBAI_E2E_SKIP_LIBRARY_SEED === '1') {
    return;
  }

  runWpCli(['plugin', 'activate', 'beepbeep-ai-alt-text-generator']);
  runWpCli(['user', 'update', WP_USER, `--user_pass=${WP_PASS}`]);

  const php = `
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
$upload = wp_upload_dir();
if ( ! empty( $upload['error'] ) ) {
	fwrite( STDERR, $upload['error'] );
	exit( 1 );
}
$dir = $upload['path'];
if ( ! is_dir( $dir ) ) {
	wp_mkdir_p( $dir );
}
for ( $i = 1; $i <= 3; $i++ ) {
	$title = 'bbai-nai-live-bridge-' . $i;
	$existing = get_page_by_title( $title, OBJECT, 'attachment' );
	if ( $existing ) {
		$id = (int) $existing->ID;
	} else {
		$file = trailingslashit( $dir ) . $title . '.png';
		file_put_contents( $file, $png );
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$file
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	}
	if ( 1 === $i ) {
		delete_post_meta( $id, '_wp_attachment_image_alt' );
	} else {
		update_post_meta( $id, '_wp_attachment_image_alt', 'Live bridge fixture image ' . $i );
	}
}
`;

  const seeded = runWpCli(['eval', php]);
  if (seeded.status !== 0) {
    throw new Error(`Failed to seed live Library fixtures.\n${seeded.stderr}\n${seeded.stdout}`);
  }
}

async function wpLogin(page: Page) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.context().addCookies([
    {
      name: 'wordpress_test_cookie',
      value: 'WP Cookie check',
      domain: new URL(BASE).hostname,
      path: '/',
      httpOnly: true,
      sameSite: 'Lax',
    },
  ]);
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

function captureErrors(page: Page): ErrorCapture {
  const errors: ErrorCapture = {
    consoleErrors: [],
    pageErrors: [],
  };

  page.on('console', (msg) => {
    if (msg.type() !== 'error') {
      return;
    }
    const text = msg.text();
    if (criticalConsoleAllowlist.some((pattern) => pattern.test(text))) {
      return;
    }
    errors.consoleErrors.push(text);
  });

  page.on('pageerror', (error) => {
    errors.pageErrors.push(error.message);
  });

  return errors;
}

async function expectNoCriticalErrors(errors: ErrorCapture) {
  expect(errors.pageErrors, 'page JavaScript errors').toEqual([]);
  expect(errors.consoleErrors, 'critical console errors').toEqual([]);
}

async function gotoLibrary(page: Page) {
  await page.goto(LIBRARY_URL, { waitUntil: 'domcontentloaded' });
  // Admin pages can continue polling in the background; do not block smoke coverage on idle.
  await page.waitForLoadState('networkidle', { timeout: 1500 }).catch(() => {});
}

async function assertNoVisiblePhpErrors(page: Page) {
  const body = await page.locator('body').innerText();
  expect(body.toLowerCase()).not.toContain('fatal error');
  expect(body.toLowerCase()).not.toContain('parse error');
  expect(body).not.toContain('Notice:');
  expect(body).not.toContain('Warning:');
}

async function assertBridgeHooks(page: Page) {
  await expect(page.locator('[data-nai-screen="library"]')).toBeVisible();
  await expect(page.locator('.nai-topbar')).toBeVisible();
  await expect(page.locator('#bbai-library-search')).toBeVisible();
  await expect(page.locator('#bbai-review-filter-tabs')).toBeVisible();
  await expect(page.locator('#bbai-library-table-body')).toBeVisible();
  await expect(page.locator('.bbai-library-row').first()).toBeVisible();
  await expect(page.locator('.bbai-library-row-check').first()).toBeVisible();
}

async function loadedScriptSources(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    Array.from(document.scripts)
      .map((script) => script.getAttribute('src') || '')
      .filter(Boolean)
  );
}

test.describe('nAi Library bridge — live wp-admin', () => {
  test.skip(!BASE, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');

  test.beforeAll(() => {
    seedLiveLibraryFixtures();
  });

  test.beforeEach(async ({ page }) => {
    await wpLogin(page);
  });

  test('loads NAI Library with production assets and compatibility hooks', async ({ page }) => {
    const errors = captureErrors(page);

    await gotoLibrary(page);
    await assertNoVisiblePhpErrors(page);
    await assertBridgeHooks(page);

    const scripts = await loadedScriptSources(page);
    expect(scripts.some((src) => src.includes('/assets/js/bbai-admin.js'))).toBeTruthy();
    expect(scripts.some((src) => src.includes('/assets/js/bbai-entitlements.js'))).toBeTruthy();
    expect(scripts.some((src) => src.includes('/assets/js/alt-library-filters.js'))).toBeTruthy();
    expect(scripts.some((src) => src.includes('/assets/js/nai-dashboard.js'))).toBeTruthy();

    await expectNoCriticalErrors(errors);
  });

  test('filter clicks update active state without JS errors', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoLibrary(page);
    await assertBridgeHooks(page);

    const filters = page.locator('#bbai-review-filter-tabs button[data-filter]');
    const count = await filters.count();
    expect(count).toBeGreaterThanOrEqual(4);

    for (let i = 0; i < count; i++) {
      const filter = filters.nth(i);
      const key = await filter.getAttribute('data-filter');
      await filter.click();
      await expect(page.locator(`#bbai-review-filter-tabs button[data-filter="${key}"]`)).toHaveAttribute('aria-pressed', 'true');
      await expect(page.locator('#bbai-library-table-body')).toBeVisible();
      await expect(page.locator('.bbai-library-row:not(.bbai-library-row--hidden), .bbai-library-filter-empty:not(.bbai-library-filter-empty--hidden)').first()).toBeVisible();
    }

    await expectNoCriticalErrors(errors);
  });

  test('search input filters or shows empty state cleanly, then restores rows', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoLibrary(page);
    await assertBridgeHooks(page);

    const initialVisibleRows = await page.locator('.bbai-library-row:not(.bbai-library-row--hidden)').count();
    expect(initialVisibleRows).toBeGreaterThan(0);

    const search = page.locator('#bbai-library-search');
    await search.fill('bbai-nai-live-bridge-1');
    await expect(page.locator('.bbai-library-row:not(.bbai-library-row--hidden), .bbai-library-filter-empty:not(.bbai-library-filter-empty--hidden)').first()).toBeVisible();

    await search.fill('zzzz-no-live-library-match');
    await expect(page.locator('.bbai-library-filter-empty, .bbai-table-empty').first()).toBeVisible();

    await search.fill('');
    await expect(page.locator('.bbai-library-row:not(.bbai-library-row--hidden)').first()).toBeVisible();

    await expectNoCriticalErrors(errors);
  });

  test('row selection updates checkbox and bulk UI without errors', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoLibrary(page);
    await assertBridgeHooks(page);

    const first = page.locator('.bbai-library-row-check').first();
    const second = page.locator('.bbai-library-row-check').nth(1);

    await first.check();
    await expect(first).toBeChecked();

    if (await second.count()) {
      await second.check();
      await expect(second).toBeChecked();
    }

    await expect(page.locator('#bbai-library-selection-bar')).toBeVisible();
    await expect(page.locator('[data-bbai-selected-count]')).toContainText(/\d+ selected/);

    await expectNoCriticalErrors(errors);
  });

  test('preview and edit paths open and close the expected modals', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoLibrary(page);
    await assertBridgeHooks(page);

    await page.locator('.bbai-library-row [data-action="preview-image"]').first().click();
    await expect(page.locator('#bbai-library-preview-modal.is-visible')).toBeVisible();
    await page.locator('#bbai-library-preview-modal .bbai-library-preview-modal__actions button[data-action="close-library-preview"]').click();
    await expect(page.locator('#bbai-library-preview-modal')).toHaveAttribute('aria-hidden', 'true');

    await page.locator('.bbai-library-row [data-action="edit-alt-inline"]').first().click();
    await expect(page.locator('.bbai-library-inline-alt').first()).toBeVisible();
    await expect(page.locator('.bbai-library-inline-alt__textarea').first()).toBeVisible();
    await page.locator('.bbai-library-inline-alt [data-action="cancel-alt-inline"]').first().click();
    await expect(page.locator('.bbai-library-inline-alt')).toHaveCount(0);

    await expectNoCriticalErrors(errors);
  });

  test('regenerate CTAs keep existing data-action path in unlocked and locked states', async ({ page }) => {
    const errors = captureErrors(page);
    const ajaxRequests: Array<{ url: string; postData: string }> = [];
    page.on('request', (request) => {
      const url = request.url();
      if (url.includes('admin-ajax.php') || url.includes('/wp-json/bbai/')) {
        ajaxRequests.push({
          url,
          postData: request.postData() || '',
        });
      }
    });

    await gotoLibrary(page);
    await assertBridgeHooks(page);

    const regen = page.locator('.bbai-library-row [data-action="regenerate-single"]').first();
    await expect(regen).toHaveAttribute('data-action', 'regenerate-single');
    await regen.click();
    await page.waitForTimeout(750);
    expect(ajaxRequests.length).toBeGreaterThan(0);

    await page.evaluate(() => {
      (window as any).BBAIEntitlements.consume({
        data: {
          entitlement_state: {
            plan_type: 'free',
            token_limit: 50,
            tokens_used_this_month: 50,
            tokens_remaining: 0,
            can_generate: false,
            can_autopilot: false,
            is_logged_in: true,
            quota_state: 'exhausted',
            upgrade_required: true,
          },
        },
      }, 'quota_denial_test');
    });

    await expect(regen).toHaveAttribute('data-bbai-entitlement-locked', '1');
    await expect(page.locator('.bbai-library-row [data-action="edit-alt-inline"]').first()).not.toHaveAttribute('aria-disabled', 'true');
    ajaxRequests.length = 0;
    await regen.dispatchEvent('click');
    await page.waitForTimeout(750);
    await expect(page.locator('#bbai-upgrade-modal, [data-nai-modal="paywall"]').first()).toBeVisible();
    const lockedGenerationRequests = ajaxRequests.filter(({ url, postData }) =>
      url.includes('/wp-json/bbai/v1/alt') || postData.includes('beepbeepai_inline_generate')
    );
    expect(lockedGenerationRequests).toEqual([]);

    await expectNoCriticalErrors(errors);
  });

  test('mobile viewport keeps filters, search, rows, and actions reachable', async ({ page }) => {
    const errors = captureErrors(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await gotoLibrary(page);
    await assertBridgeHooks(page);

    await expect(page.locator('#bbai-review-filter-tabs')).toBeVisible();
    await expect(page.locator('#bbai-library-search')).toBeVisible();
    await expect(page.locator('.bbai-library-row').first()).toBeVisible();
    await expect(page.locator('.bbai-library-row [data-action="edit-alt-inline"]').first()).toBeVisible();

    const scrollWidth = await page.locator('.nai-app').evaluate((el) => el.scrollWidth);
    const clientWidth = await page.locator('.nai-app').evaluate((el) => el.clientWidth);
    expect(scrollWidth - clientWidth).toBeLessThanOrEqual(24);

    await expectNoCriticalErrors(errors);
  });
});
