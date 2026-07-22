import { expect, test, type Page, type Route } from '@playwright/test';
import { spawnSync } from 'child_process';

const BASE = (process.env.BBAI_E2E_BASE_URL || '').replace(/\/$/, '');
const WP_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';
const LIBRARY_MISSING_URL = `${BASE}/wp-admin/admin.php?page=bbai-library&status=missing&filter=missing#bbai-review-filter-tabs`;
const LIBRARY_WEAK_URL = `${BASE}/wp-admin/admin.php?page=bbai-library&status=needs_review&filter=weak#bbai-review-filter-tabs`;
const QA_ALT_TEXT = 'QA intercepted ALT text describing a landscape image with clear foreground detail.';

type ErrorCapture = {
  consoleErrors: string[];
  pageErrors: string[];
};

type AjaxCall = {
  action: string;
  postData: string;
};

type LibraryCounts = {
  all: number;
  missing: number;
  weak: number;
  optimized: number;
};

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

function seedLibraryFixtures() {
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
for ( $i = 1; $i <= 4; $i++ ) {
	$title = 'bbai-generation-state-' . $i;
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
	if ( $i <= 2 ) {
		delete_post_meta( $id, '_wp_attachment_image_alt' );
	} else {
		update_post_meta( $id, '_wp_attachment_image_alt', 'Weak fixture ALT ' . $i );
	}
}
`;

  const seeded = runWpCli(['eval', php]);
  if (seeded.status !== 0) {
    throw new Error(`Failed to seed ALT Library generation fixtures.\n${seeded.stderr}\n${seeded.stdout}`);
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
    if (/favicon\.ico/i.test(text) || /Failed to load resource.*favicon/i.test(text)) {
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

async function gotoMissingLibrary(page: Page) {
  await page.goto(LIBRARY_MISSING_URL, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#bbai-library-table-body')).toBeVisible();
  await expect(page.locator('.bbai-library-row[data-alt-missing="true"]').first()).toBeVisible();
}

async function gotoWeakLibrary(page: Page) {
  await page.goto(LIBRARY_WEAK_URL, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#bbai-library-table-body')).toBeVisible();
  await expect(page.locator('#bbai-review-filter-tabs button[data-filter="weak"]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('.bbai-library-row[data-alt-missing="false"]').first()).toBeVisible();
}

async function firstMissingRowId(page: Page): Promise<string> {
  const id = await page.locator('.bbai-library-row[data-alt-missing="true"]').first().getAttribute('data-attachment-id');
  expect(id).toMatch(/^\d+$/);
  return id || '';
}

async function firstWeakRowId(page: Page): Promise<string> {
  const id = await page.locator('.bbai-library-row[data-alt-missing="false"][data-status="weak"], .bbai-library-row[data-alt-missing="false"][data-review-state="weak"]').first().getAttribute('data-attachment-id');
  expect(id).toMatch(/^\d+$/);
  return id || '';
}

async function readWorkspaceCounts(page: Page): Promise<LibraryCounts> {
  return page.evaluate(() => {
    const root = document.querySelector('[data-bbai-library-workspace-root="1"]');
    const chipRoot = document.querySelector('#bbai-review-filter-tabs');
    const readAttr = (name: string) => Number.parseInt(root?.getAttribute(name) || '0', 10) || 0;
    const readChip = (filter: string) => {
      const button = chipRoot?.querySelector(`button[data-filter="${filter}"]`);
      const node = button?.querySelector('.bbai-filter-group__count, .nai-filter-pill__count');
      const source = node?.textContent || button?.textContent || '0';
      const match = source.match(/([0-9][0-9,]*)\s*$/);
      return Number.parseInt((match ? match[1] : source).replace(/[^0-9]+/g, ''), 10) || 0;
    };
    const attrCounts = {
      all: readAttr('data-bbai-total-count'),
      missing: readAttr('data-bbai-missing-count'),
      weak: readAttr('data-bbai-weak-count'),
      optimized: readAttr('data-bbai-optimized-count'),
    };
    return {
      all: attrCounts.all || readChip('all'),
      missing: attrCounts.missing || readChip('missing'),
      weak: attrCounts.weak || readChip('weak'),
      optimized: attrCounts.optimized || readChip('optimized'),
    };
  });
}

async function readFilterChipCounts(page: Page): Promise<LibraryCounts> {
  return page.evaluate(() => {
    const roots = Array.from(document.querySelectorAll('#bbai-review-filter-tabs'));
    const root = roots.find((candidate) => {
      const rect = candidate.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    }) || roots[roots.length - 1] || document;
    const read = (filter: string) => {
      const button = root.querySelector(`button[data-filter="${filter}"]`);
      const node = button?.querySelector('.bbai-filter-group__count, .nai-filter-pill__count');
      const source = node?.textContent || button?.textContent || '0';
      const match = source.match(/([0-9][0-9,]*)\s*$/);
      return Number.parseInt((match ? match[1] : source).replace(/[^0-9]+/g, ''), 10) || 0;
    };
    return {
      all: read('all'),
      missing: read('missing'),
      weak: read('weak'),
      optimized: read('optimized'),
    };
  });
}

async function activeFilter(page: Page): Promise<string> {
  return page.locator('#bbai-review-filter-tabs:visible button[aria-pressed="true"], #bbai-review-filter-tabs:visible button.bbai-filter-group__item--active').first().getAttribute('data-filter').then((value) => value || '');
}

async function visibleRowIds(page: Page): Promise<string[]> {
  return page.locator('.bbai-library-row:not(.bbai-library-row--hidden)[aria-hidden="false"]').evaluateAll((rows) =>
    rows.map((row) => row.getAttribute('data-attachment-id') || '').filter(Boolean)
  );
}

async function expectVisibleRowsMatchFilter(page: Page, filter: 'missing' | 'weak' | 'optimized') {
  const rows = page.locator('.bbai-library-row:not(.bbai-library-row--hidden)[aria-hidden="false"]');
  const count = await rows.count();
  for (let i = 0; i < count; i++) {
    const row = rows.nth(i);
    if (filter === 'missing') {
      await expect(row).toHaveAttribute('data-alt-missing', 'true');
    } else if (filter === 'weak') {
      await expect(row).toHaveAttribute('data-alt-missing', 'false');
      await expect(row).toHaveAttribute('data-status', /weak|needs-review/);
    } else {
      await expect(row).toHaveAttribute('data-status', 'optimized');
    }
  }
}

async function expectNoDuplicateVisibleRows(page: Page) {
  const ids = await visibleRowIds(page);
  expect(new Set(ids).size, 'visible rows should not contain duplicates').toBe(ids.length);
}

function statsAfter(base: LibraryCounts, patch: Partial<LibraryCounts>) {
  const next = { ...base, ...patch };
  return {
    total_images: next.all,
    images_missing_alt: next.missing,
    images_with_alt: Math.max(0, next.weak + next.optimized),
    needs_review_count: next.weak,
    optimized_count: next.optimized,
  };
}

function parseAttachmentIds(postData: string): string[] {
  const params = new URLSearchParams(postData);
  const arrayIds = params.getAll('attachment_ids[]').filter(Boolean);
  if (arrayIds.length) {
    return arrayIds;
  }
  const single = params.get('attachment_ids');
  return single ? single.split(',').filter(Boolean) : [];
}

async function installGenerationMocks(
  page: Page,
  options: {
    calls: AjaxCall[];
    delayRegenerateMs?: number;
    delayQueueMs?: number;
    delayInlineMs?: number;
    regenerateData?: () => Record<string, unknown>;
    inlineData?: (ids: string[]) => Record<string, unknown>;
    scanPayload?: () => Record<string, unknown>;
  }
) {
  await page.route('**/wp-admin/admin-ajax.php', async (route: Route) => {
    const request = route.request();
    const postData = request.postData() || '';
    const params = new URLSearchParams(postData);
    const action = params.get('action') || '';

    if (action === 'beepbeepai_regenerate_single') {
      options.calls.push({ action, postData });
      if (options.delayRegenerateMs) {
        await new Promise((resolve) => setTimeout(resolve, options.delayRegenerateMs));
      }
      const defaultData = {
        alt_text: QA_ALT_TEXT,
        usage: { used: 1, limit: 50, remaining: 49, plan: 'free' },
        stats: {
          total_images: 4,
          images_missing_alt: 1,
          images_with_alt: 3,
          images_needing_review: 1,
        },
        meta: {
          generated: 'Just now',
          quality_score: 82,
          review_state: 'review',
        },
      };
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: options.regenerateData ? options.regenerateData() : defaultData,
        }),
      });
      return;
    }

    if (action === 'beepbeepai_bulk_queue') {
      options.calls.push({ action, postData });
      if (options.delayQueueMs) {
        await new Promise((resolve) => setTimeout(resolve, options.delayQueueMs));
      }
      const ids = parseAttachmentIds(postData);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            queued: Math.max(1, ids.length || 1),
            job_id: 'qa-generation-state-job',
            attachment_ids: ids,
          },
        }),
      });
      return;
    }

    if (action === 'beepbeepai_inline_generate') {
      options.calls.push({ action, postData });
      if (options.delayInlineMs) {
        await new Promise((resolve) => setTimeout(resolve, options.delayInlineMs));
      }
      const ids = parseAttachmentIds(postData);
      const defaultData = {
        results: ids.map((id) => ({
          success: true,
          id: Number(id),
          attachment_id: Number(id),
          alt_text: `${QA_ALT_TEXT} ${id}`,
          title: `QA image ${id}`,
        })),
        usage: { used: 2, limit: 50, remaining: 48, plan: 'free' },
        updated_images: ids.map((id) => ({
          id: Number(id),
          alt_text: `${QA_ALT_TEXT} ${id}`,
        })),
      };
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: options.inlineData ? options.inlineData(ids) : defaultData,
        }),
      });
      return;
    }

    if (action === 'bbai_start_alt_coverage_scan' || action === 'bbai_rescan_alt_coverage') {
      options.calls.push({ action, postData });
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            done: true,
            payload: options.scanPayload ? options.scanPayload() : {
              total_images: 4,
              images_missing_alt: 0,
              images_with_alt: 4,
              needs_review_count: 0,
              optimized_count: 4,
            },
          },
        }),
      });
      return;
    }

    if (action === 'bbai_poll_alt_coverage_scan') {
      options.calls.push({ action, postData });
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            done: true,
            payload: options.scanPayload ? options.scanPayload() : {
              total_images: 4,
              images_missing_alt: 0,
              images_with_alt: 4,
              needs_review_count: 0,
              optimized_count: 4,
            },
          },
        }),
      });
      return;
    }

    if (action === 'beepbeepai_get_attachment_ids') {
      options.calls.push({ action, postData });
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: { ids: [] } }),
      });
      return;
    }

    await route.continue();
  });

  await page.route('**/wp-json/bbai/v1/attachment-alt/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { alt_text: QA_ALT_TEXT } }),
    });
  });

  await page.route('**/wp-json/bbai/v1/stats**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(options.scanPayload ? options.scanPayload() : {
        total_images: 4,
        images_missing_alt: 0,
        images_with_alt: 4,
        needs_review_count: 0,
        optimized_count: 4,
      }),
    });
  });
}

function countCalls(calls: AjaxCall[], action: string): number {
  return calls.filter((call) => call.action === action).length;
}

async function enableBulkMode(page: Page) {
  await page.evaluate(() => {
    const toggle = document.querySelector<HTMLElement>('[data-action="toggle-library-bulk-mode"], #bbai-library-bulk-toggle');
    if (toggle) {
      toggle.click();
    }
  });
}

test.describe('ALT Library generation state regressions', () => {
  test.skip(!BASE, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');

  test.beforeAll(() => {
    seedLibraryFixtures();
  });

  test.beforeEach(async ({ page }) => {
    await wpLogin(page);
  });

  test('single regenerate sends exactly one AJAX request, shows busy state, and updates row without refresh', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    await installGenerationMocks(page, { calls, delayRegenerateMs: 700 });
    await gotoMissingLibrary(page);

    const rowId = await firstMissingRowId(page);
    const row = page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`);
    const button = row.locator('[data-action="regenerate-single"]');

    await button.click();
    await expect(button).toBeDisabled();
    await expect(row).toContainText(/Generating ALT text|Processing/i);
    await expect(row).toBeHidden({ timeout: 8000 });

    expect(countCalls(calls, 'beepbeepai_regenerate_single')).toBe(1);
    expect(countCalls(calls, 'beepbeepai_bulk_queue')).toBe(0);
    expect(countCalls(calls, 'beepbeepai_inline_generate')).toBe(0);
    await expectNoCriticalErrors(errors);
  });

  test('generate selected is disabled before selection and enables after selecting a missing row', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoMissingLibrary(page);
    await enableBulkMode(page);

    const generateSelected = page.locator('#bbai-batch-generate');
    await expect(generateSelected).toBeDisabled();
    await expect(generateSelected).toHaveAttribute('aria-disabled', 'true');

    await page.locator('.bbai-library-row[data-alt-missing="true"] .bbai-library-row-check').first().check({ force: true });
    await expect(generateSelected).toBeEnabled();
    await expect(generateSelected).toHaveAttribute('aria-disabled', 'false');
    await expectNoCriticalErrors(errors);
  });

  test('generate selected sends bulk queue and inline generation once', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    await installGenerationMocks(page, { calls, delayQueueMs: 200, delayInlineMs: 200 });
    await gotoMissingLibrary(page);
    await enableBulkMode(page);

    await page.locator('.bbai-library-row[data-alt-missing="true"] .bbai-library-row-check').first().check({ force: true });
    await expect(page.locator('#bbai-batch-generate')).toBeEnabled();
    await page.locator('#bbai-batch-generate').click();

    await expect.poll(() => countCalls(calls, 'beepbeepai_bulk_queue')).toBe(1);
    await expect.poll(() => countCalls(calls, 'beepbeepai_inline_generate')).toBe(1);
    await page.waitForTimeout(500);
    expect(countCalls(calls, 'beepbeepai_bulk_queue')).toBe(1);
    expect(countCalls(calls, 'beepbeepai_inline_generate')).toBe(1);
    await expectNoCriticalErrors(errors);
  });

  test('server/quota-locked bulk controls remain locked after row selection', async ({ page }) => {
    const errors = captureErrors(page);
    await gotoMissingLibrary(page);
    await enableBulkMode(page);

    await page.evaluate(() => {
      const button = document.querySelector<HTMLButtonElement>('#bbai-batch-generate');
      if (!button) {
        return;
      }
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
      button.setAttribute('data-bbai-locked-cta', '1');
      button.setAttribute('data-bbai-action', 'open-upgrade');
    });

    await page.locator('.bbai-library-row[data-alt-missing="true"] .bbai-library-row-check').first().check({ force: true });
    const generateSelected = page.locator('#bbai-batch-generate');
    await expect(generateSelected).toBeDisabled();
    await expect(generateSelected).toHaveAttribute('aria-disabled', 'true');
    await expect(generateSelected).toHaveAttribute('data-bbai-locked-cta', '1');
    await expectNoCriticalErrors(errors);
  });

  test('out-of-credit mocked state blocks generation and opens quota or upgrade UI', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    await installGenerationMocks(page, { calls });
    await gotoMissingLibrary(page);

    await page.evaluate(() => {
      (window as any).BBAIEntitlements = (window as any).BBAIEntitlements || {};
      (window as any).BBAIEntitlements.get = () => ({
        tokens_remaining: 0,
        token_limit: 50,
        tokens_used_this_month: 50,
        plan_type: 'free',
        quota_state: 'exhausted',
        upgrade_required: true,
      });
      (window as any).BBAIEntitlements.isExhausted = () => true;
      (window as any).bbaiGenerationLock = { active: false, source: null, jobId: null, startedAt: null };
    });

    await page.locator('.bbai-library-row[data-alt-missing="true"] [data-action="regenerate-single"]').first().click();
    await expect(page.locator('#bbai-upgrade-modal, .bbai-upgrade-modal, [data-nai-modal="paywall"]').first()).toBeVisible();
    expect(calls).toEqual([]);
    await expectNoCriticalErrors(errors);
  });

  test('review filter query and hash activate the weak tab', async ({ page }) => {
    const errors = captureErrors(page);
    await page.goto(`${BASE}/wp-admin/admin.php?page=bbai-library&status=needs_review&filter=weak#bbai-review-filter-tabs`, {
      waitUntil: 'domcontentloaded',
    });
    await expect(page.locator('#bbai-review-filter-tabs')).toBeVisible();

    const weakTab = page.locator('#bbai-review-filter-tabs button[data-filter="weak"]');
    await expect(weakTab).toBeVisible();
    await expect(weakTab).toHaveAttribute('aria-current', 'true');
    await expect(weakTab).toHaveClass(/active/);
    await expectNoCriticalErrors(errors);
  });

  test('repeated single-regenerate clicks and navigation do not duplicate AJAX calls', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    await installGenerationMocks(page, { calls, delayRegenerateMs: 700 });
    await gotoMissingLibrary(page);

    let rowId = await firstMissingRowId(page);
    let button = page.locator(`.bbai-library-row[data-attachment-id="${rowId}"] [data-action="regenerate-single"]`);
    await button.click();
    await button.click({ force: true }).catch(() => {});
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });
    expect(countCalls(calls, 'beepbeepai_regenerate_single')).toBe(1);

    await gotoMissingLibrary(page);
    rowId = await firstMissingRowId(page);
    button = page.locator(`.bbai-library-row[data-attachment-id="${rowId}"] [data-action="regenerate-single"]`);
    await button.click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });

    expect(countCalls(calls, 'beepbeepai_regenerate_single')).toBe(2);
    expect(countCalls(calls, 'beepbeepai_bulk_queue')).toBe(0);
    expect(countCalls(calls, 'beepbeepai_inline_generate')).toBe(0);
    await expectNoCriticalErrors(errors);
  });

  test('single regenerate from Missing tab updates row, counts, and keeps the Missing filter stable', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    let beforeCounts: LibraryCounts = { all: 0, missing: 0, weak: 0, optimized: 0 };
    await installGenerationMocks(page, {
      calls,
      regenerateData: () => ({
        alt_text: QA_ALT_TEXT,
        usage: { used: 1, limit: 50, remaining: 49, plan: 'free' },
        stats: statsAfter(beforeCounts, {
          missing: Math.max(0, beforeCounts.missing - 1),
          optimized: beforeCounts.optimized + 1,
        }),
        meta: {
          generated: 'Just now',
          score: 92,
          row_status: 'optimized',
        },
      }),
      scanPayload: () => statsAfter(beforeCounts, {
        missing: Math.max(0, beforeCounts.missing - 1),
        optimized: beforeCounts.optimized + 1,
      }),
    });

    await gotoMissingLibrary(page);
    beforeCounts = await readFilterChipCounts(page);
    const rowId = await firstMissingRowId(page);

    await page.locator(`.bbai-library-row[data-attachment-id="${rowId}"] [data-action="regenerate-single"]`).click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });

    await expect.poll(() => readWorkspaceCounts(page)).toMatchObject({
      missing: Math.max(0, beforeCounts.missing - 1),
    });
    await expect.poll(() => readFilterChipCounts(page)).toMatchObject({
      missing: Math.max(0, beforeCounts.missing - 1),
    });
    expect((await readWorkspaceCounts(page)).optimized).toBeGreaterThanOrEqual(0);
    expect(await activeFilter(page)).toBe('missing');
    await expectVisibleRowsMatchFilter(page, 'missing');
    await expectNoDuplicateVisibleRows(page);
    expect(countCalls(calls, 'beepbeepai_regenerate_single')).toBe(1);
    await expectNoCriticalErrors(errors);
  });

  test('single regenerate from Review tab updates row state and keeps the Review filter stable', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    let beforeCounts: LibraryCounts = { all: 0, missing: 0, weak: 0, optimized: 0 };
    await installGenerationMocks(page, {
      calls,
      regenerateData: () => ({
        alt_text: QA_ALT_TEXT,
        usage: { used: 1, limit: 50, remaining: 49, plan: 'free' },
        stats: statsAfter(beforeCounts, {
          weak: Math.max(0, beforeCounts.weak - 1),
          optimized: beforeCounts.optimized + 1,
        }),
        meta: {
          generated: 'Just now',
          score: 94,
          row_status: 'optimized',
        },
      }),
      scanPayload: () => statsAfter(beforeCounts, {
        weak: Math.max(0, beforeCounts.weak - 1),
        optimized: beforeCounts.optimized + 1,
      }),
    });

    await gotoWeakLibrary(page);
    beforeCounts = {
      ...(await readFilterChipCounts(page)),
      weak: await page.locator('.bbai-library-row:not(.bbai-library-row--hidden)[aria-hidden="false"]').count(),
    };
    const rowId = await firstWeakRowId(page);

    await page.locator(`.bbai-library-row[data-attachment-id="${rowId}"] [data-action="regenerate-single"]`).click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });

    await expect.poll(() => readWorkspaceCounts(page)).toMatchObject({
      weak: Math.max(0, beforeCounts.weak - 1),
    });
    await expect.poll(() => readFilterChipCounts(page)).toMatchObject({
      weak: Math.max(0, beforeCounts.weak - 1),
    });
    expect((await readWorkspaceCounts(page)).optimized).toBeGreaterThanOrEqual(0);
    expect(await activeFilter(page)).toBe('weak');
    await expectVisibleRowsMatchFilter(page, 'weak');
    await expectNoDuplicateVisibleRows(page);
    expect(countCalls(calls, 'beepbeepai_regenerate_single')).toBe(1);
    await expectNoCriticalErrors(errors);
  });

  test('bulk selected generation updates selected rows and keeps missing counts aligned', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    let beforeCounts: LibraryCounts = { all: 0, missing: 0, weak: 0, optimized: 0 };
    let selectedCount = 0;
    await installGenerationMocks(page, {
      calls,
      inlineData: (ids) => ({
        results: ids.map((id) => ({
          success: true,
          id: Number(id),
          attachment_id: Number(id),
          alt_text: `${QA_ALT_TEXT} ${id}`,
          title: `QA image ${id}`,
          meta: { score: 91, row_status: 'optimized' },
        })),
        usage: { used: ids.length, limit: 50, remaining: Math.max(0, 50 - ids.length), plan: 'free' },
        updated_images: ids.map((id) => ({
          id: Number(id),
          alt_text: `${QA_ALT_TEXT} ${id}`,
        })),
      }),
      scanPayload: () => statsAfter(beforeCounts, {
        missing: Math.max(0, beforeCounts.missing - selectedCount),
        optimized: beforeCounts.optimized + selectedCount,
      }),
    });

    await gotoMissingLibrary(page);
    beforeCounts = await readFilterChipCounts(page);
    await enableBulkMode(page);

    const missingRows = page.locator('.bbai-library-row[data-alt-missing="true"]:not(.bbai-library-row--hidden)');
    selectedCount = Math.min(2, await missingRows.count());
    expect(selectedCount).toBeGreaterThan(0);
    const selectedIds: string[] = [];
    for (let i = 0; i < selectedCount; i++) {
      const row = missingRows.nth(i);
      selectedIds.push((await row.getAttribute('data-attachment-id')) || '');
      await row.locator('.bbai-library-row-check').check({ force: true });
    }

    await page.locator('#bbai-batch-generate').click();
    await expect.poll(() => countCalls(calls, 'beepbeepai_inline_generate')).toBe(1);

    for (const rowId of selectedIds) {
      await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 10000 });
    }
    await expect.poll(() => readWorkspaceCounts(page)).toMatchObject({
      missing: Math.max(0, beforeCounts.missing - selectedCount),
    });
    await expectVisibleRowsMatchFilter(page, 'missing');
    await expectNoDuplicateVisibleRows(page);
    const afterCounts = await readWorkspaceCounts(page);
    expect(afterCounts.missing).toBeGreaterThanOrEqual(0);
    expect(afterCounts.weak).toBeGreaterThanOrEqual(0);
    expect(afterCounts.optimized).toBeGreaterThanOrEqual(0);
    expect(countCalls(calls, 'beepbeepai_bulk_queue')).toBe(1);
    expect(countCalls(calls, 'beepbeepai_inline_generate')).toBe(selectedCount);
    await expectNoCriticalErrors(errors);
  });

  test('rescan after generation does not reintroduce stale Missing rows', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    let beforeCounts: LibraryCounts = { all: 0, missing: 0, weak: 0, optimized: 0 };
    let afterStats = statsAfter(beforeCounts, {});
    await installGenerationMocks(page, {
      calls,
      regenerateData: () => ({
        alt_text: QA_ALT_TEXT,
        usage: { used: 1, limit: 50, remaining: 49, plan: 'free' },
        stats: afterStats,
        meta: {
          generated: 'Just now',
          score: 90,
          row_status: 'optimized',
        },
      }),
      scanPayload: () => afterStats,
    });

    await gotoMissingLibrary(page);
    beforeCounts = await readFilterChipCounts(page);
    afterStats = statsAfter(beforeCounts, {
      missing: Math.max(0, beforeCounts.missing - 1),
      optimized: beforeCounts.optimized + 1,
    });
    const rowId = await firstMissingRowId(page);

    await page.locator(`.bbai-library-row[data-attachment-id="${rowId}"] [data-action="regenerate-single"]`).click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });

    await page.locator('[data-action="rescan-media-library"]').first().click();
    await expect.poll(() => countCalls(calls, 'bbai_start_alt_coverage_scan')).toBeGreaterThanOrEqual(1);
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${rowId}"]`)).toBeHidden({ timeout: 8000 });
    await expect.poll(() => readWorkspaceCounts(page)).toMatchObject({
      missing: Math.max(0, beforeCounts.missing - 1),
    });
    await expectVisibleRowsMatchFilter(page, 'missing');
    await expectNoDuplicateVisibleRows(page);
    await expectNoCriticalErrors(errors);
  });

  test('repeated mixed actions do not create duplicate rows, negative counts, or stale disabled buttons', async ({ page }) => {
    const errors = captureErrors(page);
    const calls: AjaxCall[] = [];
    let beforeCounts: LibraryCounts = { all: 0, missing: 0, weak: 0, optimized: 0 };
    let generatedCount = 0;
    await installGenerationMocks(page, {
      calls,
      regenerateData: () => {
        generatedCount += 1;
        return {
          alt_text: QA_ALT_TEXT,
          usage: { used: generatedCount, limit: 50, remaining: Math.max(0, 50 - generatedCount), plan: 'free' },
          stats: statsAfter(beforeCounts, {
            missing: Math.max(0, beforeCounts.missing - generatedCount),
            optimized: beforeCounts.optimized + generatedCount,
          }),
          meta: {
            generated: 'Just now',
            score: 91,
            row_status: 'optimized',
          },
        };
      },
      inlineData: (ids) => {
        generatedCount += ids.length;
        return {
          results: ids.map((id) => ({
            success: true,
            id: Number(id),
            attachment_id: Number(id),
            alt_text: `${QA_ALT_TEXT} ${id}`,
            title: `QA image ${id}`,
            meta: { score: 91, row_status: 'optimized' },
          })),
          usage: { used: generatedCount, limit: 50, remaining: Math.max(0, 50 - generatedCount), plan: 'free' },
          updated_images: ids.map((id) => ({
            id: Number(id),
            alt_text: `${QA_ALT_TEXT} ${id}`,
          })),
        };
      },
      scanPayload: () => statsAfter(beforeCounts, {
        missing: Math.max(0, beforeCounts.missing - generatedCount),
        optimized: beforeCounts.optimized + generatedCount,
      }),
    });

    await gotoMissingLibrary(page);
    beforeCounts = await readFilterChipCounts(page);

    const firstId = await firstMissingRowId(page);
    await page.locator(`.bbai-library-row[data-attachment-id="${firstId}"] [data-action="regenerate-single"]`).click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${firstId}"]`)).toBeHidden({ timeout: 8000 });

    const secondId = await firstMissingRowId(page);
    expect(secondId).not.toBe(firstId);
    await page.locator(`.bbai-library-row[data-attachment-id="${secondId}"] [data-action="regenerate-single"]`).click();
    await expect(page.locator(`.bbai-library-row[data-attachment-id="${secondId}"]`)).toBeHidden({ timeout: 8000 });

    await enableBulkMode(page);
    const remainingCheckbox = page.locator('.bbai-library-row[data-alt-missing="true"]:not(.bbai-library-row--hidden) .bbai-library-row-check').first();
    if (await remainingCheckbox.count()) {
      await remainingCheckbox.check({ force: true });
      await expect(page.locator('#bbai-batch-generate')).toBeEnabled();
      await page.locator('#bbai-batch-generate').click();
      await expect.poll(() => countCalls(calls, 'beepbeepai_bulk_queue')).toBe(1);
    }

    await expectNoDuplicateVisibleRows(page);
    const counts = await readWorkspaceCounts(page);
    expect(counts.missing).toBeGreaterThanOrEqual(0);
    expect(counts.weak).toBeGreaterThanOrEqual(0);
    expect(counts.optimized).toBeGreaterThanOrEqual(0);
    expect(await activeFilter(page)).toBe('missing');
    await expectVisibleRowsMatchFilter(page, 'missing');

    const generateSelected = page.locator('#bbai-batch-generate');
    if (await generateSelected.count()) {
      await expect(generateSelected).not.toHaveAttribute('data-bbai-generation-locked', '1');
    }
    await expectNoCriticalErrors(errors);
  });
});
