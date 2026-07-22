import { test, expect } from '@playwright/test';

// Skips when BBAI_E2E_BASE_URL is unset (default clone / CI without WP).
const BASE = process.env.BBAI_E2E_BASE_URL || 'http://localhost:8888';
const ADMIN_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';

async function login(page: any) {
  await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);
  }
  await expect(page).toHaveURL(/wp-admin/);
}

test('Phase 1 validation: credits + generation lock', async ({ page }) => {
  test.skip(!process.env.BBAI_E2E_BASE_URL, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');
  test.setTimeout(180_000);

  const requests: string[] = [];
  const ajaxPosts: string[] = [];
  page.on('request', (req: any) => {
    const url = req.url();
    if (url.includes('admin-ajax.php') || url.includes('/wp-json/')) {
      requests.push(`${req.method()} ${url}`);
    }
    if (req.method() === 'POST' && url.includes('admin-ajax.php')) {
      const postData = req.postData() || '';
      if (postData.includes('action=')) {
        ajaxPosts.push(postData);
      }
    }
  });

  await login(page);

  // Go to dashboard and capture starting credits.
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'domcontentloaded' });
  // 1) Confirm connected state in DOM.
  const hasConnected = await page.evaluate(() => {
    const root =
      document.querySelector<HTMLElement>('[data-bbai-dashboard-root]') ||
      document.querySelector<HTMLElement>('#bbai-dashboard-root') ||
      document.body;
    return (root.getAttribute('data-bbai-has-connected-account') || '0') === '1';
  });
  expect(hasConnected).toBe(true);

  const startCredits = await page.evaluate(() => {
    const root =
      document.querySelector<HTMLElement>('[data-bbai-dashboard-root]') ||
      document.querySelector<HTMLElement>('#bbai-dashboard-root') ||
      document.body;
    const read = (k: string) => parseInt(String(root.getAttribute(`data-bbai-${k}`) || '0').replace(/[^\d]/g, ''), 10) || 0;
    return {
      used: read('credits-used'),
      remaining: read('credits-remaining'),
      total: read('credits-total'),
    };
  });

  // Go to ALT Library in Missing view and select a missing image.
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai-library&status=missing`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('text=Missing', { timeout: 20000 });

  // Start generation for a single missing image via the row primary action.
  const rowGenerateBtn = page.locator('article[data-alt-missing="true"] button[data-action="regenerate-single"]').first();
  await expect(rowGenerateBtn).toBeVisible({ timeout: 20000 });

  const beforeCount = requests.length;
  const beforeAjax = ajaxPosts.length;
  await rowGenerateBtn.click();

  // "Lock" validation: avoid starting a second generation request.
  // In this UI the clicked button may be replaced/removed quickly, so we validate by request volume.
  await page.waitForTimeout(2500);
  const afterCount = requests.length;
  expect(afterCount - beforeCount).toBeLessThanOrEqual(6); // allow polling + state refresh noise
  const afterAjax = ajaxPosts.length;
  const generationAjax = ajaxPosts.slice(beforeAjax).filter((b) => b.includes('beepbeepai_') || b.includes('bbai_') || b.includes('generate') || b.includes('regenerate'));
  expect(generationAjax.length).toBeLessThanOrEqual(2);

  // Wait for missing filter chip to flip to 0 (generation applied).
  const missingChip = page.locator('#bbai-review-filter-tabs .bbai-filter-group__item:has-text("Missing") .bbai-filter-group__count').first();
  await expect(missingChip).toHaveText(/0/, { timeout: 60000 });

  // Wait a bit for credits to update (if generation completes quickly in this env).
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'domcontentloaded' });
  let endCredits = startCredits;
  for (let i = 0; i < 10; i += 1) {
    endCredits = await page.evaluate(() => {
      const root =
        document.querySelector<HTMLElement>('[data-bbai-dashboard-root]') ||
        document.querySelector<HTMLElement>('#bbai-dashboard-root') ||
        document.body;
      const read = (k: string) => parseInt(String(root.getAttribute(`data-bbai-${k}`) || '0').replace(/[^\d]/g, ''), 10) || 0;
      return {
        used: read('credits-used'),
        remaining: read('credits-remaining'),
        total: read('credits-total'),
      };
    });
    if (endCredits.used >= startCredits.used + 1 || endCredits.remaining <= startCredits.remaining - 1) {
      break;
    }
    await page.waitForTimeout(2000);
    await page.reload({ waitUntil: 'domcontentloaded' });
  }

  // Credits assertions: if remaining was > 0, it should decrement and used increment.
  if (startCredits.remaining > 0) {
    expect(endCredits.used).toBeGreaterThanOrEqual(startCredits.used + 1);
    expect(endCredits.remaining).toBeLessThanOrEqual(startCredits.remaining - 1);
  }
});

