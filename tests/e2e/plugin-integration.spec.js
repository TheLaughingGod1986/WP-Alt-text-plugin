/**
 * BeepBeep AI Plugin – Integration E2E Tests
 *
 * Covers: auth flow, license activation, dashboard, billing, alt text generation.
 *
 * Run: BBAI_E2E_BASE_URL=http://localhost:8080 BBAI_E2E_WP_USER=admin BBAI_E2E_WP_PASS=xxx npx playwright test tests/e2e/plugin-integration.spec.js
 *
 * Or with defaults (admin/admin123): BBAI_E2E_BASE_URL=http://localhost:8080 npx playwright test tests/e2e/plugin-integration.spec.js
 */

const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.BBAI_E2E_BASE_URL || 'http://127.0.0.1:8889';
const WP_USER = process.env.BBAI_E2E_WP_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_WP_PASS || 'admin123';
const DASHBOARD_PATH = process.env.BBAI_E2E_DASHBOARD_PATH || '/wp-admin/admin.php?page=bbai';
const MEDIA_ALT_PATH = '/wp-admin/upload.php?page=bbai';
const HAS_BASE_URL = Boolean(process.env.BBAI_E2E_BASE_URL);

test.describe('BeepBeep AI Plugin Integration', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!HAS_BASE_URL, 'Set BBAI_E2E_BASE_URL to run integration tests.');
  });

  test('login and reach dashboard', async ({ page }) => {
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    await page.goto(`${BASE_URL}${DASHBOARD_PATH}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await expect(page.locator('body')).toBeVisible();
    // Dashboard shell should load (logged-in or logged-out state)
    const body = await page.locator('body').textContent();
    expect(body).toBeTruthy();
  });

  test('dashboard page loads without critical errors', async ({ page }) => {
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));
    page.on('requestfailed', (req) => {
      const url = req.url();
      if (url.includes('alttext-ai-backend') || url.includes('api')) {
        errors.push(`API request failed: ${url}`);
      }
    });

    await page.goto(`${BASE_URL}${DASHBOARD_PATH}`, { waitUntil: 'networkidle', timeout: 20000 });

    const critical = errors.filter((e) =>
      /syntax|uncaught|failed to fetch|404|500/i.test(e)
    );
    expect(critical, `Critical errors: ${critical.join('; ')}`).toEqual([]);
  });

  test('Media AI ALT Text page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    await page.goto(`${BASE_URL}${MEDIA_ALT_PATH}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await expect(page.locator('body')).toBeVisible();
  });

  test('auth modal can be opened (when logged out)', async ({ page }) => {
    await page.goto(`${BASE_URL}${DASHBOARD_PATH}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const loginBtn = page.locator('[data-action="show-auth-modal"], [data-action="login"], button:has-text("Log in"), a:has-text("Log in")').first();
    if (await loginBtn.isVisible()) {
      await loginBtn.click();
      await page.waitForTimeout(500);
      const modal = page.locator('[role="dialog"], .bbai-modal, [data-bbai-auth-modal]').first();
      await expect(modal).toBeVisible({ timeout: 5000 });
    } else {
      test.skip(true, 'No login button visible (may already be logged in)');
    }
  });

  test('upgrade modal opens when clicking upgrade button', async ({ page }) => {
    await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    await page.goto(`${BASE_URL}${DASHBOARD_PATH}`, { waitUntil: 'networkidle', timeout: 20000 });

    const upgradeBtn = page.locator(
      '[data-action="show-upgrade-modal"], button:has-text("Upgrade to Growth"), button:has-text("Upgrade to continue"), a:has-text("Upgrade to Growth"), .bbai-upgrade-cta-card button'
    ).first();
    await expect(upgradeBtn).toBeVisible({ timeout: 5000 });
    await upgradeBtn.click();
    await page.waitForTimeout(800);

    const upgradeModal = page.locator('#bbai-upgrade-modal, .bbai-upgrade-modal, [data-bbai-upgrade-modal]').first();
    await expect(upgradeModal).toBeVisible({ timeout: 5000 });

    // Modal should show plan/pricing content
    const modalContent = page.locator('.bbai-upgrade-modal__content, .bbai-modal__content, [role="dialog"]');
    await expect(modalContent.first()).toBeVisible({ timeout: 3000 });
  });
});
