/**
 * One-shot logout flow verification.
 * Run: npx playwright test tests/e2e/logout-flow-check.ts --headed
 */
import { test, expect } from '@playwright/test';

// Skips when BBAI_E2E_BASE_URL is unset (default clone / CI without WP).
const BASE = process.env.BBAI_E2E_BASE_URL || 'http://localhost:8888';
const DASHBOARD = `${BASE}/wp-admin/admin.php?page=bbai`;

async function wpLogin(page: any) {
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
}

test('after sign-out the exhausted hero panel is shown', async ({ page }) => {
  test.skip(!process.env.BBAI_E2E_BASE_URL, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');
  // ── Step 1: log into wp-admin ──────────────────────────────────────────
  await wpLogin(page);

  // ── Step 2: open the plugin dashboard ─────────────────────────────────
  await page.goto(DASHBOARD, { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: 'output/logout-01-before-signout.png', fullPage: true });

  // ── Step 3: find the Sign out button (header or dashboard) ────────────
  const signOutBtn = page.locator(
    'button[type="submit"].bbai-header-logout-btn, button[type="submit"].bbai-dashboard-signout-btn, .bbai-header-logout-form button, .bbai-dashboard-signout-form button'
  ).first();

  const signOutVisible = await signOutBtn.isVisible().catch(() => false);
  if (!signOutVisible) {
    // Try the form submit button more broadly
    const fallback = page.locator('form[action*="admin-post"] button[type="submit"]').first();
    await fallback.click({ timeout: 5000 });
  } else {
    await signOutBtn.click();
  }

  // ── Step 4: wait for redirect back to dashboard ───────────────────────
  await page.waitForURL('**/admin.php?page=bbai**', { timeout: 10000 });
  await page.waitForLoadState('domcontentloaded');

  // ── Step 5: take screenshot of logged-out screen ──────────────────────
  await page.screenshot({ path: 'output/logout-02-after-signout.png', fullPage: true });

  // ── Step 6: assert the exhausted hero panel is visible (not hidden) ───
  const exhaustedPanel = page.locator('#bbai-ftue-panel-exhausted');
  await expect(exhaustedPanel).not.toHaveAttribute('hidden', { timeout: 5000 });

  // Confirm the trial_available and conversion panels are hidden
  const trialPanel = page.locator('#bbai-ftue-panel-trial');
  const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
  await expect(trialPanel).toHaveAttribute('hidden');
  await expect(conversionPanel).toHaveAttribute('hidden');

  // Confirm "Sign back in" or "You've signed out" text is present
  const heroSection = page.locator('#bbai-ftue-panel-exhausted');
  const text = await heroSection.innerText();
  console.log('Exhausted panel text:', text.trim().slice(0, 200));

  expect(text).toMatch(/signed out|Sign back in/i);

  console.log('✅ Logout flow looks correct — exhausted hero panel is showing.');
});
