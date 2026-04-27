import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import { forceLoggedOut } from './utils/auth';

/**
 * Hero / onboarding state tests for the logged-out dashboard.
 *
 * Requires WP running at BBAI_E2E_BASE_URL (default http://127.0.0.1:8888).
 * Uses docker exec into the wp-env CLI container to control wp_options so we
 * can deterministically place the UI in each of the three hero states.
 *
 * States under test:
 *   trial_available  — no trial usage, free CTA visible
 *   trial_complete   — any usage recorded, conversion panel visible, free CTA absent
 *   logged_in        — authenticated user, logged-out panel never rendered
 */

// WP redirects back to its own siteurl (localhost:8888), so always normalise
// to that even if the test runner uses 127.0.0.1.
const BASE = (process.env.BBAI_E2E_BASE_URL ?? 'http://localhost:8888')
  .replace('127.0.0.1', 'localhost');
const DASHBOARD_PATH = '/wp-admin/admin.php?page=bbai';

// wp-env Docker CLI container name (stable hash derived from project path).
const CLI_CONTAINER = '06fe8883b07a5e21412cec8c726b075e-cli-1';
const WP_PATH = '--path=/var/www/html';

// Auth options that make dashboard-tab.php route to the authenticated view.
const AUTH_OPTIONS = ['beepbeepai_jwt_token', 'beepbeepai_license_key'];

// ---------------------------------------------------------------------------
// WP-CLI helpers (run synchronously in global/fixture setup).
// ---------------------------------------------------------------------------

function wp(...args: string[]): string {
  const cmd = `docker exec ${CLI_CONTAINER} wp ${WP_PATH} ${args.join(' ')} 2>/dev/null`;
  try {
    return execSync(cmd, { encoding: 'utf8' }).trim();
  } catch {
    return '';
  }
}

// Resolve the trial option key for a fresh (cookie-less) browser session.
// With no anon-id cookie the plugin keys usage by site identifier alone:
//   bbai_trial_usage_{sanitize_key(beepbeepai_site_id)}
function getTrialOptionKey(): string {
  // beepbeepai_site_id is already sanitize_key()-compatible (lowercase hex).
  const siteId = wp('option', 'get', 'beepbeepai_site_id').trim();
  if (siteId) return `bbai_trial_usage_${siteId}`;
  // Fallback: search for any existing usage option.
  const found = wp('option', 'list', '--search=bbai_trial_usage_*', '--format=csv', '--fields=option_name')
    .split('\n')
    .find(l => l.startsWith('bbai_trial_usage_'));
  return found ?? 'bbai_trial_usage_unknown';
}

function setTrialUsed(count: number) {
  const key = getTrialOptionKey();
  if (!key || key === 'bbai_trial_usage_unknown') return;
  // Clear all trial usage options (anon-ID-suffixed variants included).
  const allKeys = wp('option', 'list', '--search=bbai_trial_usage_*', '--format=csv', '--fields=option_name')
    .split('\n')
    .filter(l => l.startsWith('bbai_trial_usage_'));
  for (const k of allKeys) {
    wp('option', 'delete', k);
  }
  if (count > 0) {
    wp('option', 'set', key, String(count), '--autoload=no');
  }
}

// Fetch the current trial limit from WordPress (respects bbai_trial_limit filter).
function getTrialLimit(): number {
  try {
    // The wp-env CLI container name ends in "-cli-1"; the WordPress container ends in "-wordpress-1".
    const containerId = execSync(
      `docker ps --format '{{.Names}}' | grep -- '-cli-1' | head -1`,
      { encoding: 'utf-8', shell: '/bin/bash' }
    ).trim();
    if (!containerId) return 5;
    const result = execSync(
      `docker exec ${containerId} wp ${WP_PATH} eval 'echo (int) apply_filters("bbai_trial_limit", 5);'`,
      { encoding: 'utf-8' }
    ).trim();
    const parsed = parseInt(result, 10);
    return Number.isNaN(parsed) ? 5 : parsed;
  } catch {
    return 5;
  }
}

function saveOption(key: string): string {
  return wp('option', 'get', key);
}

function deleteOption(key: string) {
  wp('option', 'delete', key);
}

function restoreOption(key: string, value: string) {
  if (value) {
    // Use wp eval to set binary-safe values.
    wp('option', `set ${key} "${value.replace(/"/g, '\\"')}" --autoload=no`);
  }
}

// ---------------------------------------------------------------------------
// Login helper — creates a fresh authenticated browser session.
// ---------------------------------------------------------------------------

async function loginAsAdmin(page: Page) {
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  // WP may redirect to its canonical siteurl (localhost:8888) even if the
  // test navigated via 127.0.0.1; wait for any URL that contains wp-admin.
  await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
}

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

test.describe('Hero / onboarding state machine', () => {
  // Must run serially — all tests share the same WP database state and would
  // race each other if parallelised.
  test.describe.configure({ mode: 'serial' });

  test.skip(!process.env.BBAI_E2E_BASE_URL && BASE === 'http://127.0.0.1:8888',
    'Set BBAI_E2E_BASE_URL to your local WP base to run these tests');

  // Saved auth option values — restored after the logged-out tests.
  let savedTokens: Record<string, string> = {};

  test.describe('Logged-out hero states', () => {
    test.beforeEach(async ({ page }) => {
      await forceLoggedOut(page);
      await page.goto('/wp-admin/admin.php?page=bbai', { waitUntil: 'domcontentloaded' });
    });

    test.beforeAll(async () => {
      // Save and remove auth tokens so dashboard routes to logged-out view.
      for (const key of AUTH_OPTIONS) {
        savedTokens[key] = saveOption(key);
        deleteOption(key);
      }
    });

    test.afterAll(async () => {
      // Restore auth tokens regardless of test outcomes.
      for (const key of AUTH_OPTIONS) {
        if (savedTokens[key]) {
          restoreOption(key, savedTokens[key]);
        }
      }
    });

    // -----------------------------------------------------------------------
    // STATE: trial_available
    // -----------------------------------------------------------------------
    test.describe('trial_available — no trial usage', () => {
      test.beforeEach(async () => {
        setTrialUsed(0);
      });

      test('data-hero-state is trial_available', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const root = page.locator('.bbai-logged-out');
        await expect(root).toBeVisible();
        await expect(root).toHaveAttribute('data-hero-state', 'trial_available');
      });

      test('trial panel is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const trialPanel = page.locator('#bbai-ftue-panel-trial');
        await expect(trialPanel).toBeVisible();
      });

      test('conversion panel is hidden', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
        await expect(conversionPanel).toBeHidden();
      });

      test('"Generate alt text for N images (free)" button is rendered and visible', async ({ page }) => {
        const limit = getTrialLimit();
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const btn = page.locator('#bbai-trial-generate-btn');
        await expect(btn).toBeVisible();
        await expect(btn).toContainText(`Generate alt text for ${limit} image`);
      });

      test('conversion CTAs are not in the DOM', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        // Conversion panel hidden — its children should not be interactable.
        const registerBtn = page.locator('#bbai-conversion-register-btn');
        await expect(registerBtn).toBeHidden();
        const loginBtn = page.locator('#bbai-conversion-login-btn');
        await expect(loginBtn).toBeHidden();
      });

      test('marketing showcase is hidden by default (filter-gated)', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        // bbai_show_logged_out_marketing_showcase defaults to false — element absent from DOM.
        const preview = page.locator('.bbai-ftue-preview');
        await expect(preview).toHaveCount(0);
      });
    });

    // -----------------------------------------------------------------------
    // STATE: trial_complete (via used count > 0)
    // -----------------------------------------------------------------------
    test.describe('trial_complete — trial usage recorded', () => {
      test.beforeEach(async () => {
        setTrialUsed(3);
      });

      test.afterEach(async () => {
        setTrialUsed(0);
      });

      test('data-hero-state is trial_complete', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const root = page.locator('.bbai-logged-out');
        await expect(root).toBeVisible();
        await expect(root).toHaveAttribute('data-hero-state', 'trial_complete');
      });

      test('trial panel is hidden', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const trialPanel = page.locator('#bbai-ftue-panel-trial');
        await expect(trialPanel).toBeHidden();
      });

      test('conversion panel is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
        await expect(conversionPanel).toBeVisible();
      });

      test('"Generate alt text for 3 images (free)" button is NOT in the DOM', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        // PHP gates this button — it must not exist in the DOM at all.
        const btn = page.locator('#bbai-trial-generate-btn');
        await expect(btn).toHaveCount(0);
      });

      test('"Create free account" CTA is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const registerBtn = page.locator('#bbai-conversion-register-btn');
        await expect(registerBtn).toBeVisible();
        await expect(registerBtn).toContainText('Create free account');
      });

      test('"Already have an account? Log in" link is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const loginBtn = page.locator('#bbai-conversion-login-btn');
        await expect(loginBtn).toBeVisible();
        await expect(loginBtn).toContainText('Already have an account');
      });

      test('conversion panel headline mentions first alt text', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const headline = page.locator('#bbai-logged-out-title-conversion');
        await expect(headline).toBeVisible();
        await expect(headline).toContainText('generated your first alt text');
      });

      test('marketing showcase is hidden by default (filter-gated)', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        // bbai_show_logged_out_marketing_showcase defaults to false — element absent from DOM.
        const preview = page.locator('.bbai-ftue-preview');
        await expect(preview).toHaveCount(0);
      });

      test('"Generate 7 more (free trial)" button is NOT in the DOM', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        // PHP gates this button — must not exist in trial_complete state.
        const moreBtn = page.locator('#bbai-demo-generate-more-btn');
        await expect(moreBtn).toHaveCount(0);
      });
    });

    // -----------------------------------------------------------------------
    // STATE: trial_complete (via exhausted — used >= limit)
    // -----------------------------------------------------------------------
    test.describe('trial_complete — quota exhausted (used = limit)', () => {
      test.beforeEach(async () => {
        setTrialUsed(getTrialLimit() + 5); // exceed limit regardless of configured value
      });

      test.afterEach(async () => {
        setTrialUsed(0);
      });

      test('data-hero-state is trial_complete when quota exhausted', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const root = page.locator('.bbai-logged-out');
        await expect(root).toBeVisible();
        await expect(root).toHaveAttribute('data-hero-state', 'trial_complete');
      });

      test('"Generate alt text for 3 images (free)" button absent when exhausted', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const btn = page.locator('#bbai-trial-generate-btn');
        await expect(btn).toHaveCount(0);
      });
    });

    // -----------------------------------------------------------------------
    // STATE: trial_exhausted — approved dashboard hero design
    // -----------------------------------------------------------------------
    test.describe('trial_exhausted — all generations used', () => {
      test.beforeEach(async () => {
        setTrialUsed(getTrialLimit() + 5);
      });

      test.afterEach(async () => {
        setTrialUsed(0);
      });

      test('data-hero-state is trial_complete', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const root = page.locator('.bbai-logged-out');
        await expect(root).toBeVisible();
        await expect(root).toHaveAttribute('data-hero-state', 'trial_complete');
      });

      test('exhausted panel is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const exhaustedPanel = page.locator('#bbai-ftue-panel-exhausted');
        await expect(exhaustedPanel).toBeVisible();
      });

      test('trial panel is hidden', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const trialPanel = page.locator('#bbai-ftue-panel-trial');
        await expect(trialPanel).toBeHidden();
      });

      test('conversion panel is hidden', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const conversionPanel = page.locator('#bbai-ftue-panel-conversion');
        await expect(conversionPanel).toBeHidden();
      });

      test('approved headline: "You\'ve used all N free generations"', async ({ page }) => {
        const limit = getTrialLimit();
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const title = page.locator('#bbai-lo-exhausted-title');
        await expect(title).toBeVisible();
        await expect(title).toContainText(`You've used all ${limit} free generations`);
      });

      test('support text: "Continue fixing your remaining images and unlock full access."', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const panel = page.locator('#bbai-ftue-panel-exhausted');
        await expect(panel).toContainText('Continue fixing your remaining images and unlock full access.');
      });

      test('"No credit card required" microcopy is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const panel = page.locator('#bbai-ftue-panel-exhausted');
        await expect(panel).toContainText('No credit card required');
      });

      test('"Already have an account?" sign-in link is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const panel = page.locator('#bbai-ftue-panel-exhausted');
        await expect(panel).toContainText('Already have an account?');
      });

      test('locked library upsell is present: "Unlock your full ALT library"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const lockedPreview = page.locator('[data-bbai-trial-locked-preview]');
        await expect(lockedPreview).toBeVisible();
        await expect(lockedPreview).toContainText('Unlock your full ALT library');
      });

      test('locked library lists benefit: "50 generations per month"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const lockedPreview = page.locator('[data-bbai-trial-locked-preview]');
        await expect(lockedPreview).toContainText('50 generations per month');
      });

      test('locked library lists benefit: "Review and edit ALT text"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const lockedPreview = page.locator('[data-bbai-trial-locked-preview]');
        await expect(lockedPreview).toContainText('Review and edit ALT text');
      });

      test('locked library lists benefit: "Bulk optimise your media library"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const lockedPreview = page.locator('[data-bbai-trial-locked-preview]');
        await expect(lockedPreview).toContainText('Bulk optimise your media library');
      });

      test('old copy "Your free trial is almost used" is absent', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Your free trial is almost used');
      });

      test('"See the difference" marketing section is absent', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('See the difference');
      });

      test('"Golden retriever" placeholder copy is absent', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Golden retriever');
      });
    });

    // -----------------------------------------------------------------------
    // Mutual exclusivity — only one hero panel renders at a time
    // -----------------------------------------------------------------------
    test.describe('mutual exclusivity', () => {
      test('trial_available: exactly one panel is visible', async ({ page }) => {
        setTrialUsed(0);
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const visiblePanels = await page.locator(
          '#bbai-ftue-panel-trial:visible, #bbai-ftue-panel-conversion:visible'
        ).count();
        expect(visiblePanels).toBe(1);
      });

      test('trial_complete: exactly one panel is visible', async ({ page }) => {
        setTrialUsed(3);
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const visiblePanels = await page.locator(
          '#bbai-ftue-panel-trial:visible, #bbai-ftue-panel-conversion:visible'
        ).count();
        expect(visiblePanels).toBe(1);

        setTrialUsed(0);
      });
    });
  });

  // -----------------------------------------------------------------------
  // STATE: logged_in — authenticated user sees the dashboard, not logged-out view
  // -----------------------------------------------------------------------
  test.describe('logged_in — authenticated user', () => {
    test('bbai-logged-out panel is absent when user has active auth token', async ({ page }) => {
      // Auth tokens are present (default env state), so dashboard routes to
      // the authenticated view — the logged-out root element must not exist.
      await loginAsAdmin(page);
      await page.goto(`${BASE}${DASHBOARD_PATH}`);

      const loggedOutRoot = page.locator('.bbai-logged-out');
      await expect(loggedOutRoot).toHaveCount(0);
    });

    test('"Generate alt text for 3 images (free)" button is absent for authenticated users', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(`${BASE}${DASHBOARD_PATH}`);

      const btn = page.locator('#bbai-trial-generate-btn');
      await expect(btn).toHaveCount(0);
    });
  });
});
