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
 *   trial_available  — no trial usage, dashboard hero in not-scanned state
 *   trial_exhausted  — used >= limit, approved dashboard hero in exhausted state
 *   logged_in        — authenticated user, full dashboard (not trial-exhausted hero)
 *
 * Approved UI contract (exhausted state):
 *   - .bbai-funnel-hero visible with class bbai-funnel-hero--trial-exhausted
 *   - headline: "You've used all N free generations"
 *   - context: "You're N images away from full optimisation"
 *   - CTA: "Fix your N remaining images"
 *   - secondary: "Already have an account? Sign in"
 *   - support: "No credit card required"
 *   - .bbai-dashboard-locked-preview-stack visible
 *   - overlay headline: "Unlock your full ALT library"
 *   - overlay body: "Create a free account to keep fixing…unlock 50 generations per month"
 *   - benefits: "Review and edit ALT text", "Bulk optimise your media library", "50 generations per month"
 *   - NO "Your free trial is almost used"
 *   - NO "See the difference"
 *   - NO "Golden retriever running through green field"
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
    // STATE: trial_available — no trial usage, dashboard hero in not-scanned state
    // -----------------------------------------------------------------------
    test.describe('trial_available — no trial usage', () => {
      test.beforeEach(async () => {
        setTrialUsed(0);
      });

      test('dashboard hero section is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const hero = page.locator('.bbai-funnel-hero');
        await expect(hero).toBeVisible();
      });

      test('hero is NOT in trial-exhausted mode', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const exhaustedHero = page.locator('.bbai-funnel-hero--trial-exhausted');
        await expect(exhaustedHero).toHaveCount(0);
      });

      test('does NOT show "Your free trial is almost used"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Your free trial is almost used');
      });

      test('does NOT show "See the difference"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('See the difference');
      });

      test('does NOT show "Golden retriever running through green field"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Golden retriever running through green field');
      });
    });

    // -----------------------------------------------------------------------
    // STATE: trial_exhausted — used >= limit, approved dashboard hero
    // -----------------------------------------------------------------------
    test.describe('trial_exhausted — quota exhausted (used >= limit)', () => {
      test.beforeEach(async () => {
        setTrialUsed(getTrialLimit() + 5); // exceed limit regardless of configured value
      });

      test.afterEach(async () => {
        setTrialUsed(0);
      });

      test('dashboard hero is visible in exhausted mode', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const exhaustedHero = page.locator('.bbai-funnel-hero--trial-exhausted');
        await expect(exhaustedHero).toBeVisible();
      });

      test('headline contains "You\'ve used all" and "free generations"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const title = page.locator('[data-bbai-funnel-hero-title]');
        await expect(title).toBeVisible();
        await expect(title).toContainText("You've used all");
        await expect(title).toContainText('free generations');
      });

      test('primary CTA says "Fix your N remaining images"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const primaryCta = page.locator('.bbai-dashboard-hero-action__cta--primary');
        await expect(primaryCta).toBeVisible();
        await expect(primaryCta).toContainText('Fix your');
        await expect(primaryCta).toContainText('remaining images');
      });

      test('secondary CTA says "Already have an account? Sign in"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const secondaryCta = page.locator('.bbai-dashboard-hero-action__cta--secondary');
        await expect(secondaryCta).toBeVisible();
        await expect(secondaryCta).toContainText('Already have an account');
        await expect(secondaryCta).toContainText('Sign in');
      });

      test('support line shows "No credit card required"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const support = page.locator('[data-bbai-funnel-hero-support]');
        await expect(support).toBeVisible();
        await expect(support).toContainText('No credit card required');
      });

      test('locked library section is visible', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const lockedPreview = page.locator('.bbai-dashboard-locked-preview-stack');
        await expect(lockedPreview).toBeVisible();
      });

      test('locked overlay headline shows "Unlock your full ALT library"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const overlayTitle = page.locator('.bbai-dashboard-locked-preview__overlay-title');
        await expect(overlayTitle).toBeVisible();
        await expect(overlayTitle).toContainText('Unlock your full ALT library');
      });

      test('overlay body mentions "50 generations per month"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const overlayBody = page.locator('.bbai-dashboard-locked-preview__overlay-copy');
        await expect(overlayBody).toBeVisible();
        await expect(overlayBody).toContainText('50 generations per month');
      });

      test('benefits list shows all three items', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        const benefits = page.locator('.bbai-dashboard-locked-preview__benefits');
        await expect(benefits).toBeVisible();
        await expect(benefits).toContainText('Review and edit ALT text');
        await expect(benefits).toContainText('Bulk optimise your media library');
        await expect(benefits).toContainText('50 generations per month');
      });

      test('does NOT show "Your free trial is almost used"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Your free trial is almost used');
      });

      test('does NOT show "See the difference"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('See the difference');
      });

      test('does NOT show "Golden retriever running through green field"', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto(`${BASE}${DASHBOARD_PATH}`);

        await expect(page.locator('body')).not.toContainText('Golden retriever running through green field');
      });
    });
  });

  // -----------------------------------------------------------------------
  // STATE: logged_in — authenticated user sees the full dashboard
  // -----------------------------------------------------------------------
  test.describe('logged_in — authenticated user', () => {
    test('hero is NOT in trial-exhausted mode for authenticated users', async ({ page }) => {
      // Auth tokens are present (default env state) so the user is connected.
      await loginAsAdmin(page);
      await page.goto(`${BASE}${DASHBOARD_PATH}`);

      const exhaustedHero = page.locator('.bbai-funnel-hero--trial-exhausted');
      await expect(exhaustedHero).toHaveCount(0);
    });

    test('locked library preview is absent for authenticated users', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(`${BASE}${DASHBOARD_PATH}`);

      // dashboard-trial-locked-preview.php guards itself: returns if has_connected_account.
      const lockedPreview = page.locator('.bbai-dashboard-locked-preview-stack');
      await expect(lockedPreview).toHaveCount(0);
    });

    test('does NOT show "Your free trial is almost used" banner', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(`${BASE}${DASHBOARD_PATH}`);

      await expect(page.locator('body')).not.toContainText('Your free trial is almost used');
    });
  });
});
