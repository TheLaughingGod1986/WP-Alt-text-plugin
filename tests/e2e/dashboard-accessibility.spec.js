const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const DASHBOARD_PATH = process.env.BBAI_E2E_DASHBOARD_PATH || '/wp-admin/admin.php?page=bbai';
const HAS_BASE_URL = Boolean(process.env.BBAI_E2E_BASE_URL);

test.describe('BeepBeep AI Admin Smoke', () => {
  test.skip(!HAS_BASE_URL, 'Set BBAI_E2E_BASE_URL to run Playwright smoke and accessibility checks.');

  test('dashboard page shell loads', async ({ page, baseURL }) => {
    await page.goto(new URL(DASHBOARD_PATH, baseURL).toString(), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();
  });

  test('dashboard has no serious or critical axe violations', async ({ page, baseURL }) => {
    await page.goto(new URL(DASHBOARD_PATH, baseURL).toString(), { waitUntil: 'domcontentloaded' });

    const results = await new AxeBuilder({ page }).analyze();
    const seriousViolations = results.violations.filter(
      (violation) => violation.impact === 'serious' || violation.impact === 'critical'
    );

    expect(seriousViolations, JSON.stringify(seriousViolations, null, 2)).toEqual([]);
  });
});
