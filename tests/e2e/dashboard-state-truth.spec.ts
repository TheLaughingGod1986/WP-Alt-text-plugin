import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';

const BASE = (process.env.BBAI_E2E_BASE_URL ?? 'http://localhost:8888').replace('127.0.0.1', 'localhost');
const DASHBOARD_PATH = '/wp-admin/admin.php?page=bbai';
const CLI_CONTAINER = '06fe8883b07a5e21412cec8c726b075e-cli-1';
const WP_PATH = '--path=/var/www/html';
const FIXTURE_OPTION = 'bbai_e2e_dashboard_state_truth_fixture';
const DASHBOARD_TRUTH_TRANSIENTS = [
  'bbai_token_last_check',
  'bbai_usage_cache',
  'bbai_quota_cache',
  'opptibbai_usage_cache',
  'opptibbai_token_last_check',
];

type TruthFixture = {
  state: string;
  counts: {
    missing: number;
    review: number;
    complete: number;
    failed: number;
    total: number;
  };
  credits: {
    used: number;
    total: number;
    remaining: number;
    plan: string;
    plan_slug: string;
    is_pro: boolean;
  };
  job: null | {
    active: boolean;
    pausable: boolean;
    status: string;
    done?: number;
    total?: number;
    eta_seconds?: number | null;
    last_checked_at?: string | null;
  };
  site: {
    site_hash: string;
    has_connected_account: boolean;
  };
  resolution_sources: Record<string, string>;
  last_run_at?: string;
};

function wp(...args: string[]): string {
  const cmd = `docker exec ${CLI_CONTAINER} wp ${WP_PATH} ${args.join(' ')} 2>/dev/null`;
  try {
    return execSync(cmd, { encoding: 'utf8' }).trim();
  } catch {
    return '';
  }
}

function wpRequired(...args: string[]): string {
  const cmd = `docker exec ${CLI_CONTAINER} wp ${WP_PATH} ${args.join(' ')} 2>/dev/null`;
  return execSync(cmd, { encoding: 'utf8' }).trim();
}

function resetDashboardTruthFixture() {
  wp(`eval 'delete_option("${FIXTURE_OPTION}");'`);
  for (const transient of DASHBOARD_TRUTH_TRANSIENTS) {
    wp('transient', 'delete', transient);
  }
  wp('cache', 'flush');

  const remaining = wp(`eval 'echo get_option("${FIXTURE_OPTION}", "");'`);
  if (remaining) {
    throw new Error(`Failed to clear ${FIXTURE_OPTION}`);
  }
}

function setDashboardTruthFixture(fixture: TruthFixture) {
  resetDashboardTruthFixture();

  const json = JSON.stringify(fixture);
  const encoded = Buffer.from(json, 'utf8').toString('base64');
  wpRequired(`eval 'update_option("${FIXTURE_OPTION}", base64_decode("${encoded}"), false);'`);
  wpRequired('cache', 'flush');

  const readBack = wpRequired(`eval 'echo get_option("${FIXTURE_OPTION}", "");'`);
  if (readBack !== json) {
    throw new Error(`Failed to verify ${FIXTURE_OPTION} write`);
  }
}

async function loginAsAdmin(page: Page) {
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
}

async function fetchDashboardTruth(page: Page) {
  return page.evaluate(async () => {
    const rootTruthUrl =
      document
        .querySelector('[data-bbai-li-state-truth-url]')
        ?.getAttribute('data-bbai-li-state-truth-url') ||
      '/wp-json/bbai/v1/dashboard/state-truth';
    const nonce =
      (window as any).BBAI?.nonce ||
      (window as any).bbai_env?.nonce ||
      (window as any).wpApiSettings?.nonce ||
      '';
    const res = await fetch(rootTruthUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': nonce,
      },
    });
    const json = await res.json();
    return {
      status: res.status,
      json,
    };
  });
}

async function installDashboardTruthRoutes(page: Page, fixture: TruthFixture, options: { failResolvedDashboard?: boolean } = {}) {
  await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
    if (request.method() === 'GET') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(fixture),
      });
      return;
    }
    await route.continue();
  });

  if (options.failResolvedDashboard) {
    await page.route('**/wp-json/bbai/v1/dashboard', async (route, request) => {
      if (request.method() === 'GET') {
        await route.abort();
        return;
      }
      await route.continue();
    });
  }
}

async function openDashboard(page: Page) {
  await page.goto(`${BASE}${DASHBOARD_PATH}`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-bbai-logged-in-dashboard]').waitFor({ state: 'attached', timeout: 15000 });
}

async function openDashboardForPollingState(page: Page) {
  await page.goto(`${BASE}${DASHBOARD_PATH}`, { waitUntil: 'load' });
  await expect(page.locator('[data-bbai-li-hero]')).toBeVisible({ timeout: 15000 });
}

async function expectHeroState(page: Page, state: string, timeout = 15000) {
  await expect(page.locator('[data-bbai-li-hero]')).toHaveAttribute('data-bbai-li-state', state, { timeout });
}

function heroSummaryValue(page: Page, label: string) {
  return page
    .locator('.bbai-li-summary__item')
    .filter({ has: page.locator('.bbai-li-summary__label', { hasText: label }) })
    .locator('.bbai-li-summary__value');
}

test.describe('Dashboard truth-driven UI', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(() => {
    resetDashboardTruthFixture();
  });

  test.afterEach(() => {
    resetDashboardTruthFixture();
  });

  test('QUEUED state keeps SSR, truth endpoint, and hydrated UI aligned without processing copy', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'QUEUED',
      counts: { missing: 4, review: 2, complete: 14, failed: 0, total: 20 },
      credits: { used: 10, total: 50, remaining: 40, plan: 'free', plan_slug: 'free', is_pro: false },
      job: {
        active: true,
        pausable: false,
        status: 'queued',
        done: 0,
        total: 4,
        last_checked_at: '2026-04-22T08:00:00Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    const root = page.locator('[data-bbai-logged-in-dashboard]');
    const hero = page.locator('[data-bbai-li-hero]');
    const truth = await fetchDashboardTruth(page);

    await expect(root).toHaveAttribute('data-state', 'QUEUED');
    await expect(hero).toHaveAttribute('data-bbai-li-state', 'QUEUED');
    expect(truth.status).toBe(200);
    expect(truth.json.state).toBe('QUEUED');

    await expect(hero.locator('[data-bbai-li-hero-headline]')).toContainText('queued');
    await expect(hero.locator('[data-bbai-li-hero-support]')).not.toContainText('Generating ALT text now');
    await expect(hero.locator('[data-bbai-li-donut-sub]')).not.toContainText('generating now');
    await expect(page.locator('.bbai-li-progress-steps')).toHaveCount(0);
    await expect(hero.locator('[data-bbai-li-action="pause-job"]')).toHaveCount(0);
    await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);
    await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeHidden();
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('Queued automatically');
    await expect(page.locator('.bbai-li-activity-strip')).toHaveClass(/bbai-li-activity-strip--queued/);
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('Last checked');
    await expect(hero.locator('.bbai-li-hero-status-line')).toContainText('Checking queue');

    setDashboardTruthFixture(fixture);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-bbai-logged-in-dashboard]').waitFor({ state: 'attached', timeout: 15000 });
    await expect(root).toHaveAttribute('data-state', 'QUEUED');
    await expect(hero).toHaveAttribute('data-bbai-li-state', 'QUEUED');
  });

  test('PROCESSING state only shows Pause when the backend marks the job active and pausable', async ({ page }) => {
    await loginAsAdmin(page);

    for (const scenario of [
      {
        name: 'pause visible',
        fixture: {
          state: 'PROCESSING',
          counts: { missing: 7, review: 1, complete: 12, failed: 0, total: 20 },
          credits: { used: 22, total: 100, remaining: 78, plan: 'pro', plan_slug: 'pro', is_pro: true },
          job: {
            active: true,
            pausable: true,
            status: 'processing',
            done: 5,
            total: 12,
            eta_seconds: 120,
            last_checked_at: '2026-04-22T08:00:00Z',
          },
          site: { site_hash: 'fixture-site', has_connected_account: true },
          resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
        } satisfies TruthFixture,
        expectedState: 'PROCESSING',
        activityText: 'Generation active',
        donutSubText: 'generating now',
        pauseVisible: true,
      },
      {
        name: 'inactive processing label falls back to mixed attention',
        fixture: {
          state: 'PROCESSING',
          counts: { missing: 7, review: 1, complete: 12, failed: 0, total: 20 },
          credits: { used: 22, total: 100, remaining: 78, plan: 'pro', plan_slug: 'pro', is_pro: true },
          job: {
            active: false,
            pausable: true,
            status: 'processing',
            done: 5,
            total: 12,
            eta_seconds: 120,
            last_checked_at: '2026-04-22T08:00:00Z',
          },
          site: { site_hash: 'fixture-site', has_connected_account: true },
          resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
        } satisfies TruthFixture,
        expectedState: 'MIXED_ATTENTION',
        activityText: '7 images need ALT text',
        donutSubText: '7 images need ALT',
        pauseVisible: false,
      },
    ]) {
      setDashboardTruthFixture(scenario.fixture);
      await openDashboard(page);

      const hero = page.locator('[data-bbai-li-hero]');
      const pause = hero.locator('[data-bbai-li-action="pause-job"]');

      await expect(hero).toHaveAttribute('data-bbai-li-state', scenario.expectedState);
      await expect(hero.locator('[data-bbai-li-donut-sub]')).toContainText(scenario.donutSubText);
      await expect(page.locator('.bbai-li-activity-strip')).toContainText(scenario.activityText);
      await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);

      if (scenario.pauseVisible) {
        await expect(pause).toHaveCount(1);
        await expect(pause).toContainText('Pause');
      } else {
        await expect(pause).toHaveCount(0);
      }
    }
  });

  test('MIXED_ATTENTION free state stays action-led without banner duplication', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 5, review: 1, complete: 14, failed: 0, total: 20 },
      credits: { used: 0, total: 50, remaining: 50, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    await expectHeroState(page, 'MIXED_ATTENTION');
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Generate missing ALT text');
    await expect(page.locator('[data-bbai-li-secondary-cta]')).toContainText('Review 1 image');
    await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);
    await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeHidden();
    await expect(heroSummaryValue(page, 'Credits left')).toHaveText('50 / 50');

    setDashboardTruthFixture(fixture);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.locator('[data-bbai-logged-in-dashboard]').waitFor({ state: 'attached', timeout: 15000 });
    await expectHeroState(page, 'MIXED_ATTENTION');
    await expect(heroSummaryValue(page, 'Credits left')).toHaveText('50 / 50');
  });

  test('NEEDS_REVIEW, ALL_CLEAR, and QUOTA_EXHAUSTED use truth counts and state-specific CTAs', async ({ page }) => {
    await loginAsAdmin(page);

    const scenarios: Array<{
      fixture: TruthFixture;
      state: string;
      primaryText: string;
      missingCount: string;
      reviewCount: string;
      creditsValue: string;
    }> = [
      {
        fixture: {
          state: 'NEEDS_REVIEW',
          counts: { missing: 0, review: 6, complete: 14, failed: 0, total: 20 },
          credits: { used: 30, total: 100, remaining: 70, plan: 'free', plan_slug: 'free', is_pro: false },
          job: null,
          site: { site_hash: 'fixture-site', has_connected_account: true },
          resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
        },
        state: 'NEEDS_REVIEW',
        primaryText: 'Approve all',
        missingCount: '0',
        reviewCount: '6',
        creditsValue: '70 / 100',
      },
      {
        fixture: {
          state: 'ALL_CLEAR',
          counts: { missing: 0, review: 0, complete: 20, failed: 0, total: 20 },
          credits: { used: 30, total: 100, remaining: 70, plan: 'free', plan_slug: 'free', is_pro: false },
          job: null,
          site: { site_hash: 'fixture-site', has_connected_account: true },
          resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
          last_run_at: '2026-04-22T08:00:00Z',
        },
        state: 'ALL_CLEAR',
        primaryText: 'Re-scan library',
        missingCount: '0',
        reviewCount: '0',
        creditsValue: '70 / 100',
      },
      {
        fixture: {
          state: 'QUOTA_EXHAUSTED',
          counts: { missing: 4, review: 0, complete: 16, failed: 0, total: 20 },
          credits: { used: 100, total: 100, remaining: 0, plan: 'free', plan_slug: 'free', is_pro: false },
          job: null,
          site: { site_hash: 'fixture-site', has_connected_account: true },
          resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
        },
        state: 'QUOTA_EXHAUSTED',
        primaryText: 'Add credits',
        missingCount: '4',
        reviewCount: '0',
        creditsValue: '0 / 100',
      },
    ];

    for (const scenario of scenarios) {
      setDashboardTruthFixture(scenario.fixture);
      await openDashboard(page);

      const root = page.locator('[data-bbai-dashboard-root="1"]');
      const hero = page.locator('[data-bbai-li-hero]');

      await expect(hero).toHaveAttribute('data-bbai-li-state', scenario.state);
      await expect(hero.locator('[data-bbai-li-primary-cta]')).toContainText(scenario.primaryText);
      await expect(root).toHaveAttribute('data-bbai-missing-count', scenario.missingCount);
      await expect(root).toHaveAttribute('data-bbai-weak-count', scenario.reviewCount);
      await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);

      await expect(hero.locator('.bbai-li-summary')).toContainText('Credits left');
      await expect(hero.locator('.bbai-li-summary')).toContainText(scenario.creditsValue);

      if (scenario.state === 'ALL_CLEAR') {
        await expect(hero.locator('[data-bbai-li-secondary-cta]')).toContainText('Open ALT Library');
        await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeVisible();
        await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toContainText('Keep new uploads moving with Pro');
      } else {
        await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeHidden();
      }

      if (scenario.state === 'NEEDS_REVIEW') {
        await expect(hero.locator('[data-bbai-li-secondary-cta]')).toContainText('Open review queue');
      }

      if (scenario.state === 'QUOTA_EXHAUSTED') {
        await expect(hero.locator('[data-bbai-li-action="generate-missing"]')).toHaveCount(0);
        await expect(hero.locator('.bbai-li-donut__meta')).toContainText('Add credits to continue');
      }
    }
  });

  test('NEEDS_REVIEW impact line stays aligned with truth across reload', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 19, complete: 42, failed: 0, total: 61 },
      credits: { used: 30, total: 100, remaining: 70, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:00Z',
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    await expectHeroState(page, 'NEEDS_REVIEW');
    await expect(page.locator('.bbai-li-impact-line')).toHaveText('19 ready for review');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('19 ready for review');

    await page.waitForTimeout(2500);
    await expect(page.locator('.bbai-li-impact-line')).toHaveText('19 ready for review');

	setDashboardTruthFixture(fixture);
    await page.reload({ waitUntil: 'load' });
    await expectHeroState(page, 'NEEDS_REVIEW');
    await expect(page.locator('.bbai-li-impact-line')).toHaveText('19 ready for review');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('19 ready for review');
  });

  test('NEEDS_REVIEW with counts.to_review renders correctly across all dashboard regions', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, to_review: 19, complete: 42, failed: 0, total: 61 },
      credits: { used: 30, total: 100, remaining: 70, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:00Z',
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    await expectHeroState(page, 'NEEDS_REVIEW');
    await expect(page.locator('.bbai-li-impact-line')).toHaveText('19 ready for review');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('19 ready for review');

    const root = page.locator('[data-bbai-dashboard-root="1"]');
    await expect(root).toHaveAttribute('data-bbai-weak-count', '19');

    await page.waitForTimeout(2500);
    await expect(page.locator('.bbai-li-impact-line')).toHaveText('19 ready for review');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('19 ready for review');
  });

  test('polling moves QUEUED jobs into PROCESSING without refresh', async ({ page }) => {
    const queuedFixture: TruthFixture = {
      state: 'QUEUED',
      counts: { missing: 4, review: 1, complete: 15, failed: 0, total: 20 },
      credits: { used: 11, total: 50, remaining: 39, plan: 'free', plan_slug: 'free', is_pro: false },
      job: {
        active: true,
        pausable: false,
        status: 'queued',
        done: 0,
        total: 4,
        last_checked_at: '2026-04-22T08:00:00Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };
    const processingFixture: TruthFixture = {
      state: 'PROCESSING',
      counts: { missing: 3, review: 1, complete: 16, failed: 0, total: 20 },
      credits: { used: 11, total: 50, remaining: 39, plan: 'free', plan_slug: 'free', is_pro: false },
      job: {
        active: true,
        pausable: true,
        status: 'processing',
        done: 1,
        total: 4,
        eta_seconds: 90,
        last_checked_at: '2026-04-22T08:00:05Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(queuedFixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(queuedFixture);
    await openDashboard(page);

    await expectHeroState(page, 'QUEUED');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('Queued automatically');

    setDashboardTruthFixture(processingFixture);

    await expectHeroState(page, 'PROCESSING', 10000);
    await expect(page.locator('[data-bbai-li-hero-headline]')).toContainText('1 of 4');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('Generation active');
    await expect(page.locator('[data-bbai-li-action="pause-job"]')).toContainText('Pause');
  });

  test('processing polling updates progress live and transitions cleanly into review', async ({ page }) => {
    const processingFixtureA: TruthFixture = {
      state: 'PROCESSING',
      counts: { missing: 8, review: 0, complete: 12, failed: 0, total: 20 },
      credits: { used: 18, total: 100, remaining: 82, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: {
        active: true,
        pausable: false,
        status: 'processing',
        done: 2,
        total: 10,
        eta_seconds: 180,
        last_checked_at: '2026-04-22T08:00:00Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };
    const processingFixtureB: TruthFixture = {
      state: 'PROCESSING',
      counts: { missing: 5, review: 0, complete: 15, failed: 0, total: 20 },
      credits: { used: 18, total: 100, remaining: 82, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: {
        active: true,
        pausable: false,
        status: 'processing',
        done: 5,
        total: 10,
        eta_seconds: 120,
        last_checked_at: '2026-04-22T08:00:03Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };
    const reviewFixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 5, complete: 15, failed: 0, total: 20 },
      credits: { used: 18, total: 100, remaining: 82, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:10Z',
    };

    setDashboardTruthFixture(processingFixtureA);
    await loginAsAdmin(page);
    setDashboardTruthFixture(processingFixtureA);
    await openDashboard(page);

    await expectHeroState(page, 'PROCESSING');
    await expect(page.locator('[data-bbai-li-action="pause-job"]')).toHaveCount(0);

    setDashboardTruthFixture(processingFixtureB);

    await expect(page.locator('[data-bbai-li-hero]')).toHaveAttribute('data-bbai-li-live-progress', '1', { timeout: 10000 });
    await expect(page.locator('[data-bbai-li-hero-headline]')).toContainText('5 of 10', { timeout: 10000 });
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('5 processed');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('5 remaining');
    await expect(page.locator('[data-bbai-li-action="pause-job"]')).toHaveCount(0);
    await expect(page.locator('[data-bbai-li-hero]')).not.toHaveAttribute('data-bbai-li-live-progress', '1');

    setDashboardTruthFixture(reviewFixture);

    await expect(page.locator('[data-bbai-logged-in-dashboard]')).toHaveClass(/bbai-li-dashboard--transition-success/, {
      timeout: 10000,
    });
    await expectHeroState(page, 'NEEDS_REVIEW', 10000);
    await expect(page.locator('[data-bbai-li-hero] .bbai-li-hero-status-line')).toContainText(
      'Batch complete. Ready for review.',
      { timeout: 10000 }
    );
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Approve all');
    await expect(page.locator('[data-bbai-li-secondary-cta]')).toContainText('Open review queue');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('ready for review');
    await expect(page.locator('.bbai-li-activity-strip')).not.toContainText('Generation active');
  });

  test('review polling reaches ALL_CLEAR and then stops polling', async ({ page }) => {
    test.slow();

    const requestCounts = { truth: 0 };
    const reviewFixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 3, complete: 17, failed: 0, total: 20 },
      credits: { used: 24, total: 100, remaining: 76, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:00Z',
    };
    const allClearFixture: TruthFixture = {
      state: 'ALL_CLEAR',
      counts: { missing: 0, review: 0, complete: 20, failed: 0, total: 20 },
      credits: { used: 24, total: 100, remaining: 76, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:01:00Z',
    };

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route) => {
      requestCounts.truth += 1;
      await route.continue();
    });

    setDashboardTruthFixture(reviewFixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(reviewFixture);
    await openDashboard(page);

    await expectHeroState(page, 'NEEDS_REVIEW');

    setDashboardTruthFixture(allClearFixture);

    await expect(page.locator('[data-bbai-logged-in-dashboard]')).toHaveClass(/bbai-li-dashboard--transition-success/, {
      timeout: 20000,
    });
    await expectHeroState(page, 'ALL_CLEAR', 20000);
    await expect(page.locator('[data-bbai-li-hero] .bbai-li-hero-status-line')).toContainText(
      'Review complete. Library is all clear.',
      { timeout: 20000 }
    );
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Re-scan library');
    await expect(page.locator('.bbai-li-activity-strip')).toContainText('Ready for new uploads');
    await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toBeVisible();
    await expect(page.locator('[data-bbai-li-upgrade-float="1"]')).toContainText('Keep new uploads moving with Pro');
    await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);

    const requestsAtAllClear = requestCounts.truth;
    await page.waitForTimeout(5000);
    expect(requestCounts.truth).toBe(requestsAtAllClear);
  });

  test('stable ALL_CLEAR state only performs the startup truth fetch', async ({ page }) => {
    const requestCounts = { truth: 0 };
    const fixture: TruthFixture = {
      state: 'ALL_CLEAR',
      counts: { missing: 0, review: 0, complete: 20, failed: 0, total: 20 },
      credits: { used: 30, total: 100, remaining: 70, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:00Z',
    };

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route) => {
      requestCounts.truth += 1;
      await route.continue();
    });

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    await expectHeroState(page, 'ALL_CLEAR');
    await page.waitForTimeout(5000);
    expect(requestCounts.truth).toBe(1);
    await expect(page.locator('[data-bbai-banner="1"]')).toHaveCount(0);
  });

  test('empty backend ledger with local media triggers one bootstrap sync', async ({ page }) => {
    let bootstrapRequestCount = 0;
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 0, review: 0, complete: 0, failed: 0, total: 0 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states_empty_unseeded',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      bootstrapRequestCount += 1;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          triggered: true,
          sent_count: 12,
          local_total: 12,
          chunks: 1,
          truth: fixture,
        }),
      });
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect.poll(() => bootstrapRequestCount).toBe(1);
    await page.waitForTimeout(2500);
    expect(bootstrapRequestCount).toBe(1);
  });

  test('successful bootstrap refreshes dashboard truth', async ({ page }) => {
    let bootstrapRequestCount = 0;
    let truthRequestCount = 0;
    const initialFixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 0, review: 0, complete: 0, failed: 0, total: 0 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states_empty_unseeded',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };
    const refreshedFixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 6, review: 2, complete: 12, failed: 0, total: 20 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(initialFixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      bootstrapRequestCount += 1;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          triggered: true,
          sent_count: 20,
          local_total: 20,
          chunks: 1,
        }),
      });
    });

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
      if (request.method() !== 'GET') {
        await route.continue();
        return;
      }

      truthRequestCount += 1;
      if (truthRequestCount >= 2) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(refreshedFixture),
        });
        return;
      }

      await route.continue();
    });

    setDashboardTruthFixture(initialFixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect(heroSummaryValue(page, 'Missing')).toHaveText('6', { timeout: 10000 });
    await expect(heroSummaryValue(page, 'To review')).toHaveText('2');
    expect(truthRequestCount).toBeGreaterThanOrEqual(2);
    expect(bootstrapRequestCount).toBe(1);

    await page.reload({ waitUntil: 'load' });
    await expectHeroState(page, 'MISSING_ALT');
    await page.waitForTimeout(2500);
    expect(bootstrapRequestCount).toBe(1);
  });

  test('bootstrap stays retryable when refreshed truth is still empty and unseeded', async ({ page }) => {
    let bootstrapRequestCount = 0;
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 0, review: 0, complete: 0, failed: 0, total: 0 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states_empty_unseeded',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      bootstrapRequestCount += 1;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          triggered: true,
          sent_count: 20,
          local_total: 20,
          chunks: 1,
          truth: fixture,
        }),
      });
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect.poll(() => bootstrapRequestCount).toBe(1);
    await page.waitForTimeout(2500);
    expect(bootstrapRequestCount).toBe(1);

    const firstGuardState = await page.evaluate(() =>
      window.localStorage.getItem('bbaiDashboardBootstrapSync:fixture-site')
    );
    expect(firstGuardState).toBeNull();

    await page.reload({ waitUntil: 'load' });
    await expectHeroState(page, 'MISSING_ALT');
    await expect.poll(() => bootstrapRequestCount).toBe(2);
  });

  test('already-populated ledger does not re-trigger full sync', async ({ page }) => {
    let bootstrapRequestCount = 0;
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 4, review: 1, complete: 15, failed: 0, total: 20 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      bootstrapRequestCount += 1;
      await route.continue();
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect(heroSummaryValue(page, 'Missing')).toHaveText('4');
    await page.waitForTimeout(2500);
    expect(bootstrapRequestCount).toBe(0);
  });

  test('failed bootstrap sync does not loop endlessly', async ({ page }) => {
    let bootstrapRequestCount = 0;
    let truthRequestCount = 0;
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 0, review: 0, complete: 0, failed: 0, total: 0 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states_empty_unseeded',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
      if (request.method() === 'GET') {
        truthRequestCount += 1;
      }
      await route.continue();
    });

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      bootstrapRequestCount += 1;
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          code: 'dashboard_bootstrap_sync_failed',
          message: 'bootstrap failed',
        }),
      });
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect(heroSummaryValue(page, 'Missing')).toHaveText('0');
    await page.waitForTimeout(4000);
    expect(bootstrapRequestCount).toBe(1);
    expect(truthRequestCount).toBe(1);
  });

  test('dashboard exits the false zero state after bootstrap truth refresh', async ({ page }) => {
    const initialFixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 0, review: 0, complete: 0, failed: 0, total: 0 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states_empty_unseeded',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };
    const refreshedFixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 9, review: 0, complete: 11, failed: 0, total: 20 },
      credits: { used: 12, total: 100, remaining: 88, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: {
        state: 'fixture',
        counts: 'image_alt_states',
        job: 'fixture',
        credits: 'fixture',
        site: 'fixture',
      },
    };

    setDashboardTruthFixture(initialFixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/dashboard/bootstrap-sync', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          triggered: true,
          sent_count: 20,
          local_total: 20,
          chunks: 1,
          truth: refreshedFixture,
        }),
      });
    });

    setDashboardTruthFixture(initialFixture);
    await openDashboard(page);
    await expectHeroState(page, 'MISSING_ALT');
    await expect(heroSummaryValue(page, 'Missing')).toHaveText('9', { timeout: 10000 });
    await expect(heroSummaryValue(page, 'Missing')).not.toHaveText('0');
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Generate missing ALT text');
  });

  test('polling failures back off and recover without dropping the current UI state', async ({ page }) => {
    const consoleMessages: string[] = [];
    let truthRequestCount = 0;

    const initialFixture: TruthFixture = {
      state: 'PROCESSING',
      counts: { missing: 7, review: 0, complete: 13, failed: 0, total: 20 },
      credits: { used: 20, total: 100, remaining: 80, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: {
        active: true,
        pausable: true,
        status: 'processing',
        done: 1,
        total: 8,
        eta_seconds: 180,
        last_checked_at: '2026-04-22T08:00:00Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };
    const recoveredFixture: TruthFixture = {
      state: 'PROCESSING',
      counts: { missing: 2, review: 0, complete: 18, failed: 0, total: 20 },
      credits: { used: 20, total: 100, remaining: 80, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: {
        active: true,
        pausable: true,
        status: 'processing',
        done: 6,
        total: 8,
        eta_seconds: 45,
        last_checked_at: '2026-04-22T08:00:20Z',
      },
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    page.on('console', (msg) => {
      consoleMessages.push(msg.text());
    });

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
      if (request.method() !== 'GET') {
        await route.continue();
        return;
      }

      truthRequestCount += 1;

      if (truthRequestCount === 2 || truthRequestCount === 3) {
        await route.abort();
        return;
      }

      if (truthRequestCount === 4) {
        setDashboardTruthFixture(recoveredFixture);
      }

      await route.continue();
    });

    setDashboardTruthFixture(initialFixture);
    await loginAsAdmin(page);
    setDashboardTruthFixture(initialFixture);
    await openDashboard(page);

    await expectHeroState(page, 'PROCESSING');
    await page.waitForTimeout(7000);
    await expectHeroState(page, 'PROCESSING');
    await expect(page.locator('[data-bbai-li-hero-headline]')).toContainText('1 of 8');

    await expect(page.locator('[data-bbai-li-hero-headline]')).toContainText('6 of 8', { timeout: 12000 });
    expect(consoleMessages.some((line) => line.includes('[dashboard-ui] polling_failed'))).toBeTruthy();
  });

  test('approve all gives instant feedback and a one-cycle optimistic review count reduction', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 3, complete: 17, failed: 0, total: 20 },
      credits: { used: 18, total: 100, remaining: 82, plan: 'pro', plan_slug: 'pro', is_pro: true },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:10Z',
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-json/bbai/v1/approve-all-alt-text', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 300));
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            approved_count: 2,
            approved_ids: [701, 702],
          },
        }),
      });
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);
    await expectHeroState(page, 'NEEDS_REVIEW');
    await expect(heroSummaryValue(page, 'To review')).toHaveText('3');

    const primary = page.locator('[data-bbai-li-primary-cta]');
    await primary.click();

    await expect(primary).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-bbai-li-hero] .bbai-li-hero-status-line')).toContainText('Approving');
    await expect(heroSummaryValue(page, 'To review')).toHaveText('1');
    await expect(page.locator('[data-bbai-li-hero] .bbai-li-hero-status-line')).toContainText('Updating review queue…');
    await expectHeroState(page, 'NEEDS_REVIEW');
  });

  test('optimistic generate state unwinds safely when truth confirmation fails', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'MISSING_ALT',
      counts: { missing: 3, review: 0, complete: 17, failed: 0, total: 20 },
      credits: { used: 20, total: 50, remaining: 30, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);

    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      const request = route.request();
      const postData = request.postData() || '';

      if (postData.includes('action=beepbeepai_get_attachment_ids')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              ids: [101, 102, 103],
              scope: 'missing',
              pagination: { limit: 3, offset: 0 },
            },
          }),
        });
        return;
      }

      if (postData.includes('action=beepbeepai_bulk_queue')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            data: {
              message: '3 image(s) queued for processing',
              queued: 3,
              total: 3,
              scheduled: true,
              job_state: 'QUEUED',
              job_status: 'queued',
            },
          }),
        });
        return;
      }

      await route.continue();
    });

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
      if (request.method() === 'GET') {
        await route.abort();
        return;
      }
      await route.continue();
    });

    setDashboardTruthFixture(fixture);
    await openDashboard(page);

    const hero = page.locator('[data-bbai-li-hero]');
    const primary = hero.locator('[data-bbai-li-primary-cta]');

    await expect(hero).toHaveAttribute('data-bbai-li-state', 'MISSING_ALT');
    await primary.click();
    await page.waitForTimeout(7000);

    await expect(primary).not.toHaveAttribute('aria-busy', 'true');
    await expect(hero.locator('.bbai-li-hero-status-line')).toContainText(
      'Could not confirm the latest dashboard state yet. Refresh to check progress.'
    );
    await expect(hero).toHaveAttribute('data-bbai-li-state', 'MISSING_ALT');
  });

  test('mixed: missing=4 and to_review=19 renders MIXED_ATTENTION with exact copy and CTAs', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW', // backend sends wrong state — missing > 0 must win
      counts: { missing: 4, to_review: 19, complete: 38, failed: 0, total: 61 } as any,
      credits: { used: 0, total: 50, remaining: 50, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await installDashboardTruthRoutes(page, fixture, { failResolvedDashboard: true });
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboardForPollingState(page);

    const hero = page.locator('[data-bbai-li-hero]');
    const steps = page.locator('.bbai-li-progress-steps__step');

    await expectHeroState(page, 'MIXED_ATTENTION');
    await expect(hero.locator('[data-bbai-li-hero-headline]')).toHaveText(
      '4 images need ALT text, 19 ready for review'
    );
    await expect(hero.locator('[data-bbai-li-hero-support]')).toHaveText(
      'Generate missing ALT text first, then review the suggested descriptions before they go live.'
    );
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Generate missing ALT text');
    await expect(page.locator('[data-bbai-li-secondary-cta]')).toContainText('Review 19 images');
    await expect(page.locator('[data-bbai-li-primary-cta]')).not.toContainText('Approve all');
    await expect(heroSummaryValue(page, 'Missing')).toHaveText('4');
    await expect(heroSummaryValue(page, 'To review')).toHaveText('19');
    await expect(heroSummaryValue(page, 'Credits left')).toHaveText('50 / 50');
    await expect(steps.nth(0)).toContainText('Generate');
    await expect(steps.nth(0)).toHaveClass(/bbai-li-progress-steps__step--active/);
    await expect(steps.nth(1)).toContainText('Review');
    await expect(steps.nth(1)).toHaveClass(/bbai-li-progress-steps__step--active/);
    await expect(steps.nth(2)).toContainText('Done');
    await expect(steps.nth(2)).toHaveClass(/bbai-li-progress-steps__step--idle/);
    await expect(page.locator('.bbai-li-activity-strip')).toContainText(
      '4 images need ALT text · 19 ready for review',
      { timeout: 15000 }
    );
  });

  test('mixed: polling keeps MIXED_ATTENTION and does not flip to NEEDS_REVIEW', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW', // backend sends wrong state
      counts: { missing: 4, to_review: 19, complete: 38, failed: 0, total: 61 } as any,
      credits: { used: 30, total: 100, remaining: 70, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await installDashboardTruthRoutes(page, fixture, { failResolvedDashboard: true });
    await loginAsAdmin(page);
    setDashboardTruthFixture(fixture);
    await openDashboardForPollingState(page);

    await expectHeroState(page, 'MIXED_ATTENTION');

    // Wait for at least one polling cycle
    await page.waitForTimeout(3000);

    // State must remain MIXED_ATTENTION — not flipped to NEEDS_REVIEW
    await expectHeroState(page, 'MIXED_ATTENTION');
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Generate missing ALT text');
    await expect(page.locator('[data-bbai-li-primary-cta]')).not.toContainText('Approve all');

    // Reload must also stay MIXED_ATTENTION
    await page.reload({ waitUntil: 'load' });
    await expectHeroState(page, 'MIXED_ATTENTION');
    await expect(page.locator('[data-bbai-li-primary-cta]')).toContainText('Generate missing ALT text');
  });

  test('mixed: approve all request failure restores button and shows inline error', async ({ page }) => {
    const fixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 19, complete: 42, failed: 0, total: 61 },
      credits: { used: 0, total: 50, remaining: 50, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };

    setDashboardTruthFixture(fixture);
    await loginAsAdmin(page);
    await installDashboardTruthRoutes(page, fixture, { failResolvedDashboard: true });

    await page.route('**/wp-json/bbai/v1/approve-all-alt-text', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 250));
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          code: 'approve_all_failed',
          message: 'Approval service failed',
        }),
      });
    });

    setDashboardTruthFixture(fixture);
    await openDashboardForPollingState(page);
    await expectHeroState(page, 'NEEDS_REVIEW');

    const primary = page.locator('[data-bbai-li-primary-cta]');
    await primary.click();

    await expect(primary).toHaveAttribute('aria-busy', 'true');
    await expect(primary).toContainText('Approving');
    await expect(primary).not.toHaveAttribute('aria-busy', 'true', { timeout: 10000 });
    await expect(primary).toContainText('Approve all');
    await expect(page.locator('[data-bbai-li-hero] .bbai-li-hero-status-line')).toContainText(
      'Approval service failed'
    );
  });

  test('mixed: approve all success refreshes state truth', async ({ page }) => {
    let approveSucceeded = false;
    let truthRefreshAfterApprove = 0;
    const reviewFixture: TruthFixture = {
      state: 'NEEDS_REVIEW',
      counts: { missing: 0, review: 19, complete: 42, failed: 0, total: 61 },
      credits: { used: 0, total: 50, remaining: 50, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
    };
    const allClearFixture: TruthFixture = {
      state: 'ALL_CLEAR',
      counts: { missing: 0, review: 0, complete: 61, failed: 0, total: 61 },
      credits: { used: 0, total: 50, remaining: 50, plan: 'free', plan_slug: 'free', is_pro: false },
      job: null,
      site: { site_hash: 'fixture-site', has_connected_account: true },
      resolution_sources: { state: 'fixture', counts: 'fixture', job: 'fixture', credits: 'fixture', site: 'fixture' },
      last_run_at: '2026-04-22T08:00:00Z',
    };

    setDashboardTruthFixture(reviewFixture);
    await loginAsAdmin(page);
    await installDashboardTruthRoutes(page, reviewFixture, { failResolvedDashboard: true });

    await page.route('**/wp-json/bbai/v1/dashboard/state-truth', async (route, request) => {
      if (request.method() === 'GET' && approveSucceeded) {
        truthRefreshAfterApprove += 1;
      }
      if (request.method() === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(approveSucceeded ? allClearFixture : reviewFixture),
        });
        return;
      }
      await route.continue();
    });

    await page.route('**/wp-json/bbai/v1/approve-all-alt-text', async (route) => {
      approveSucceeded = true;
      setDashboardTruthFixture(allClearFixture);
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          approved_count: 19,
          approved_ids: [701, 702, 703],
        }),
      });
    });

    setDashboardTruthFixture(reviewFixture);
    await openDashboardForPollingState(page);
    await expectHeroState(page, 'NEEDS_REVIEW');

    await page.locator('[data-bbai-li-primary-cta]').click();

    await expect.poll(() => truthRefreshAfterApprove, { timeout: 10000 }).toBeGreaterThanOrEqual(1);
    await expectHeroState(page, 'ALL_CLEAR', 15000);
    await expect(heroSummaryValue(page, 'Optimised')).toHaveText('61');
  });
});
