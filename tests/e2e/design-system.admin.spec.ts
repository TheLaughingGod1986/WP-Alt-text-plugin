import { test, expect } from '@playwright/test';

/**
 * Layout / shell regression for shared admin UI.
 * Skips when BBAI_E2E_BASE_URL is unset (default clone / CI without WP).
 *
 * With auth: set BBAI_E2E_STORAGE_STATE to a saved storageState.json after wp-admin login.
 */
const base = process.env.BBAI_E2E_BASE_URL;

const pluginPages = [
  { name: 'dashboard', path: '/wp-admin/admin.php?page=bbai-dashboard' },
  { name: 'library', path: '/wp-admin/admin.php?page=bbai-library' },
  { name: 'analytics', path: '/wp-admin/admin.php?page=bbai-analytics' },
  { name: 'usage', path: '/wp-admin/admin.php?page=bbai-credit-usage' },
  { name: 'settings', path: '/wp-admin/admin.php?page=bbai-settings' },
];

test.describe('Design system — admin shell', () => {
  for (const { name, path } of pluginPages) {
    test(`${name}: body.bbai-dashboard and content shell`, async ({ page }) => {
      test.skip(!base, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');

      await page.goto(path);
      const body = page.locator('body');
      await expect(body).toHaveClass(/bbai-dashboard/);

      const shell = page.locator('.bbai-content-shell').first();
      await expect(shell).toBeVisible();

      // Layout sanity: main wrap should not force tiny max-width on plugin pages
      const wrap = page.locator('#wpbody-content .wrap').first();
      if (await wrap.count()) {
        const maxWidth = await wrap.evaluate((el) => getComputedStyle(el).maxWidth);
        expect(maxWidth === 'none' || maxWidth === '100%' || parseFloat(maxWidth) >= 800).toBeTruthy();
      }
    });
  }
});
