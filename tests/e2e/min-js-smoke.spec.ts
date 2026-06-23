import { test, expect, Page } from '@playwright/test';

/**
 * Production-mode smoke for the minified admin scripts.
 *
 * Run with SCRIPT_DEBUG off so the asset resolver serves bbai-admin.min.js /
 * bbai-dashboard.min.js instead of the raw sources. Walks the admin pages and
 * asserts:
 *   - no raw bbai-*.js is served (production resolution works),
 *   - both minified bundles are actually loaded somewhere, and
 *   - nothing throws an uncaught exception (terser didn't break the jQuery).
 *
 * Creds default to the wp-env admin (admin / password); override with
 * BBAI_E2E_USER / BBAI_E2E_PASS.
 */
const USER = process.env.BBAI_E2E_USER || 'admin';
const PASS = process.env.BBAI_E2E_PASS || 'password';

const adminPaths = [
  '/wp-admin/admin.php?page=bbai',
  '/wp-admin/admin.php?page=bbai-dashboard',
  '/wp-admin/admin.php?page=bbai-library',
  '/wp-admin/admin.php?page=bbai-settings',
];

async function login(page: Page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
}

test('minified admin JS loads and runs clean across admin pages', async ({ page }) => {
  test.setTimeout(120000); // walks several admin pages with settle waits
  const pageErrors: string[] = [];
  page.on('pageerror', (e) => pageErrors.push(e.message));

  const servedMin = new Set<string>();
  const servedRaw = new Set<string>();
  page.on('response', (r) => {
    const file = (r.url().split('?')[0].split('/').pop() || '');
    if (/^bbai-(admin|dashboard)\.min\.js$/.test(file)) servedMin.add(file);
    else if (/^bbai-(admin|dashboard)\.js$/.test(file)) servedRaw.add(file);
  });

  await login(page);

  for (const path of adminPaths) {
    // 'load' (not 'networkidle') — admin pages poll and never reach idle.
    await page.goto(path, { waitUntil: 'load' });
    await page.waitForTimeout(1200); // let deferred scripts run / throw
    await expect(page.locator('body')).toBeVisible();
  }

  expect([...servedRaw], 'production must not serve raw bbai-*.js').toEqual([]);
  expect([...servedMin].sort()).toEqual(['bbai-admin.min.js', 'bbai-dashboard.min.js']);
  expect(pageErrors, `uncaught JS errors: ${pageErrors.join(' | ')}`).toHaveLength(0);
});
