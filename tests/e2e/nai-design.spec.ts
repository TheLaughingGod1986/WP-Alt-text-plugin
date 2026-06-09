/**
 * nAi design regression suite.
 *
 * Boots PHP's built-in server against tests/preview/nai-preview.php so each
 * redesign partial can be rendered in isolation, then asserts that key design
 * markers (eyebrow, hero copy, Pro/Free plan affordances) are present.
 *
 * Self-contained — does not require WordPress, Docker, or BBAI_E2E_BASE_URL.
 * Skips automatically if `php` is missing from PATH.
 */

import { test, expect, type Page } from '@playwright/test';
import { ChildProcess, spawn, spawnSync } from 'child_process';
import { join } from 'path';

const PORT = 8779;
const BASE = `http://127.0.0.1:${PORT}`;
const REPO_ROOT = join(__dirname, '..', '..');

let server: ChildProcess | undefined;

const hasPhp = (): boolean => {
  try {
    const r = spawnSync('php', ['-v'], { stdio: 'ignore' });
    return r.status === 0;
  } catch {
    return false;
  }
};

const waitForServer = async (url: string, timeoutMs = 5000): Promise<boolean> => {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    try {
      const res = await fetch(url);
      if (res.ok) return true;
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 100));
  }
  return false;
};

const expectNaiNav = async (page: Page, activeLabel: string) => {
  await expect(page.locator('.nai-topbar')).toBeVisible();
  await expect(page.locator('.nai-topbar__link')).toHaveCount(4);
  await expect(page.locator('.nai-topbar__link.is-active')).toHaveText(activeLabel);
  await expect(page.locator('.nai-topbar__link', { hasText: 'Home' })).toBeVisible();
  await expect(page.locator('.nai-topbar__link', { hasText: 'Library' })).toBeVisible();
  await expect(page.locator('.nai-topbar__link', { hasText: 'Autopilot' })).toBeVisible();
  await expect(page.locator('.nai-topbar__link', { hasText: 'Settings' })).toBeVisible();
};

test.describe('nAi design — static render', () => {
  test.skip(!hasPhp(), 'PHP not available — skipping nAi design tests');

  test.beforeAll(async () => {
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', REPO_ROOT], {
      cwd: REPO_ROOT,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    const up = await waitForServer(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid`);
    if (!up) {
      throw new Error('Failed to start PHP preview server');
    }
  });

  test.afterAll(async () => {
    if (server && !server.killed) {
      server.kill('SIGTERM');
      await new Promise((r) => setTimeout(r, 200));
    }
  });

  test('dashboard renders without PHP errors and shows the design tokens', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid`, {
      waitUntil: 'domcontentloaded',
    });

    // No PHP fatal/notice/warning leaked into the page body.
    const body = await page.locator('body').innerText();
    expect(body.toLowerCase()).not.toContain('fatal error');
    expect(body.toLowerCase()).not.toContain('parse error');
    expect(body).not.toContain('Notice:');
    expect(body).not.toContain('Warning:');
    expect(body).not.toContain('Undefined variable');
    expect(body).not.toContain('%%');

    // Screen scope + design eyebrow + hero present.
    await expectNaiNav(page, 'Home');
    await expect(page.locator('[data-nai-screen="dashboard"]')).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Today's pass/i })).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Library health/i })).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Library coverage/i })).toBeVisible();
  });

  test('dashboard hero shows queue + primary CTA when work remains (Free + mid library)', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid`);
    // Free hero CTA reads "Start today's pass".
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Start today's pass/i })).toBeVisible();
    await expect(page.locator('.nai-hero__queue img.nai-thumb')).toHaveCount(3);
    await expect(page.locator('.nai-activity .nai-eyebrow')).toContainText(/Latest activity/i);
    await expect(page.locator('.nai-activity__row', { hasText: /3 new uploads detected for today's pass/i })).toBeVisible();
    await expect(page.locator('.nai-activity__row', { hasText: /247 images generated or improved/i })).toBeVisible();
    await expect(page.locator('.nai-activity__row', { hasText: /142 images left without ALT text/i })).toBeVisible();
    // Footer metrics show monthly usage row.
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Monthly usage/i })).toBeVisible();
  });

  test('empty todays pass surfaces existing missing ALT work', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=fresh`);

    await expect(page.locator('.nai-hero__title')).toContainText(/384\s+images need ALT text/i);
    await expect(page.locator('.nai-hero__complete')).toContainText(/Missing ALT text still needs review/i);
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Review missing ALT/i })).toBeVisible();
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Review missing ALT/i })).not.toHaveAttribute('data-nai-open-drawer');
    await expect(page.locator('.nai-hero__complete')).not.toContainText(/No new images in today's pass/i);
    await expect(page.locator('.nai-hero__complete')).not.toContainText(/Library fully covered/i);
  });

  test('dashboard Pro variant drops quota framing in favour of automation status', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=pro&state=near`);
    // Pro hero CTA reads "Run optimisation pass".
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Run optimisation pass/i })).toBeVisible();
    // Pro footer metrics: automation status replaces quota framing.
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Improvements this week/i })).toBeVisible();
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Monthly usage/i })).toHaveCount(0);
  });

  test('dashboard tweaks expose onboarding, generation drawer, limits, and sign-out states', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid&nai_tweaks=1`);
    await expect(page.locator('.nai-tweaks')).toBeVisible();

    await page.locator('.nai-tweaks button', { hasText: /Replay onboarding/i }).click();
    await expect(page.locator('[data-nai-modal="onboarding"] h2', { hasText: /Welcome to BeepBeep AI/i })).toBeVisible();
    await page.locator('[data-nai-onboarding-next]').click();
    await expect(page.locator('[data-nai-modal="onboarding"] h2', { hasText: /Scanning your library/i })).toBeVisible();
    await page.keyboard.press('Escape');

    await page.locator('.nai-tweaks button', { hasText: /Generation drawer/i }).click();
    await expect(page.locator('[data-nai-drawer]')).toBeVisible();
    await expect(page.locator('.nai-gen-row').first()).toBeVisible({ timeout: 2000 });
    await page.locator('[data-nai-gen-regen]').first().click();
    await expect(page.locator('.nai-gen-row p').first()).toContainText(/Rewritten ALT text/i);
    await page.keyboard.press('Escape');

    await page.locator('.nai-tweaks button', { hasText: /Trigger daily limit/i }).click();
    await expect(page.locator('[data-nai-paywall-title]')).toContainText(/today's free generations/i);
    await page.keyboard.press('Escape');
    await page.locator('.nai-tweaks button', { hasText: /Trigger monthly limit/i }).click();
    await expect(page.locator('[data-nai-paywall-title]')).toContainText(/this month's free generations/i);
    await page.keyboard.press('Escape');

    await page.locator('.nai-tweaks button', { hasText: /Trigger sign-out/i }).click();
    await expect(page.locator('[data-nai-modal="signout"] h2')).toContainText(/Sign out/i);
    await page.locator('[data-nai-confirm-signout]').click();
    await expect(page.locator('[data-nai-signedout]')).toBeVisible();
    await page.locator('[data-nai-signin]').click();
    await expect(page.locator('[data-nai-screen="dashboard"]')).toBeVisible();
  });

  test('dashboard at 100% coverage hides the forward projection and shows completion copy', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=pro&state=done`);
    const insight = page.locator('.nai-health__insight-body');
    await expect(insight).toBeVisible();
    const text = await insight.innerText();
    expect(text.toLowerCase()).toContain('fully optimised');
    // No "could reach X% coverage in around" sentence.
    expect(text.toLowerCase()).not.toContain('could reach');
  });

  test('autopilot screen renders preset grid + length segmented control + schedule rows', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=autopilot&plan=pro`);
    await expectNaiNav(page, 'Autopilot');
    await expect(page.locator('[data-nai-screen="autopilot"]')).toBeVisible();
    await expect(page.locator('.nai-page-header__title', { hasText: /Hands-off image SEO/i })).toBeVisible();
    // Four presets.
    await expect(page.locator('.nai-preset')).toHaveCount(4);
    // Three length options.
    await expect(page.locator('.nai-seg__btn')).toHaveCount(3);
    // Three schedule rows.
    await expect(page.locator('.nai-sched-row')).toHaveCount(3);
    // Live preview block present.
    await expect(page.locator('.nai-preview')).toBeVisible();
  });

  test('autopilot Free variant shows the upsell hero instead of the active toggle', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=autopilot&plan=free`);
    await expect(page.locator('.nai-ap-hero--upsell')).toBeVisible();
    await expect(page.locator('.nai-ap-hero .nai-btn--pro', { hasText: /Upgrade/i })).toBeVisible();
    // Lock icons render on the schedule rows for Free.
    await expect(page.locator('.nai-sched-row__icon--locked')).toHaveCount(3);
  });

  test('settings screen renders plan card, section heads, and danger zone', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=settings&plan=free`);
    await expectNaiNav(page, 'Settings');
    await expect(page.locator('[data-nai-screen="settings"]')).toBeVisible();
    await expect(page.locator('.nai-page-header__title', { hasText: /Plan & preferences/i })).toBeVisible();
    await expect(page.locator('.nai-plan-card').first()).toBeVisible();
    // Free shows the progress bar + "Upgrade to Pro" CTA inside the plan card.
    await expect(page.locator('.nai-plan-card .nai-progress__bar').first()).toBeVisible();
    await expect(page.locator('.nai-plan-card .nai-btn--pro', { hasText: /Upgrade to Pro/i })).toBeVisible();
    // Section heads appear.
    await expect(page.locator('.nai-set-section__title', { hasText: /Account/i })).toBeVisible();
    await expect(page.locator('.nai-set-section__title', { hasText: /Notifications/i })).toBeVisible();
    await expect(page.locator('.nai-set-section__title', { hasText: /Danger zone/i })).toBeVisible();
  });

  test('settings Pro variant flips plan card to continuous-optimisation status', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=settings&plan=pro`);
    await expect(page.locator('.nai-plan-card--pro')).toBeVisible();
    // Pro shows "Manage billing" instead of Upgrade.
    await expect(page.locator('.nai-plan-card .nai-btn--secondary', { hasText: /Manage billing/i })).toBeVisible();
    // No progress bar on Pro (renders only the pulse-dot status line).
    await expect(page.locator('.nai-plan-card .nai-progress__bar')).toHaveCount(0);
  });

  test('library renders filter pills, coverage strip, and rows with preserved data-action selectors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);

    // No PHP errors leaked through.
    const body = await page.locator('body').innerText();
    expect(body.toLowerCase()).not.toContain('fatal error');
    expect(body.toLowerCase()).not.toContain('parse error');
    expect(body).not.toContain('Notice:');
    expect(body).not.toContain('Warning:');

    // Wrapper present.
    await expectNaiNav(page, 'Library');
    await expect(page.locator('[data-nai-screen="library"]')).toBeVisible();
    // Coverage strip eyebrow + four filter pills (All / Needs ALT / Low quality / Optimised).
    await expect(page.locator('.nai-eyebrow', { hasText: /Library coverage/i })).toBeVisible();
    await expect(page.locator('.nai-filter-pill')).toHaveCount(4);
    await expect(page.locator('#bbai-review-filter-tabs button[data-filter]')).toHaveCount(4);
    await expect(page.locator('.nai-filter-pill--active', { hasText: /All/i })).toBeVisible();

    // Sample rows render and preserve the data-action attributes the legacy JS expects.
    await expect(page.locator('.nai-lib-row')).toHaveCount(6);
    await expect(page.locator('#bbai-library-table-body')).toBeVisible();
    await expect(page.locator('.bbai-library-row')).toHaveCount(6);
    await expect(page.locator('.bbai-library-row-check')).toHaveCount(6);
    await expect(page.locator('[data-action="regenerate-single"]').first()).toBeVisible();
    await expect(page.locator('[data-action="edit-alt-inline"]').first()).toBeVisible();
    await expect(page.locator('[data-action="generate-selected"]')).toBeVisible();
    await expect(page.locator('[data-action="regenerate-selected"]')).toBeVisible();
    await expect(page.locator('[data-action="rescan-media-library"]')).toBeVisible();

    // Free variant carries the Pro upsell strip at the bottom.
    await expect(page.locator('.nai-screen--library .nai-btn--pro', { hasText: /Upgrade to Pro/i })).toBeVisible();
  });

  test('library Pro variant drops the upsell strip', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=pro&state=near`);
    await expect(page.locator('[data-nai-screen="library"]')).toBeVisible();
    // Pro should NOT see the "Upgrade to Pro" CTA at the bottom of the library.
    await expect(page.locator('.nai-screen--library .nai-btn--pro')).toHaveCount(0);
  });

  test('library filter navigation and search controls keep stable migration hooks', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);

    const needsAlt = page.locator('.nai-filter-pill', { hasText: /Needs ALT/i });
    await expect(needsAlt).toHaveAttribute('data-bbai-library-filter', 'missing');
    await expect(needsAlt).toHaveAttribute('data-filter', 'missing');
    await expect(needsAlt).toHaveAttribute('data-bbai-filter-href', /filter=missing/);
    await expect(page.locator('#bbai-review-filter-tabs button[data-filter="missing"]')).toBeVisible();

    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&filter=missing`);
    await expect(page.locator('.nai-filter-pill--active', { hasText: /Needs ALT/i })).toBeVisible();

    const search = page.getByPlaceholder('Search filename');
    await expect(search).toBeVisible();
    await search.fill('coffee');
    await expect(search).toHaveValue('coffee');
  });

  test('library compatibility bridge exposes legacy row, filter, and selection hooks', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);

    await expect(page.locator('#bbai-library-search.nai-search__input')).toBeVisible();
    await expect(page.locator('#bbai-review-filter-tabs .nai-filter-pill[data-filter]')).toHaveCount(4);
    await expect(page.locator('#bbai-library-table-body .bbai-library-row.nai-lib-row')).toHaveCount(6);

    const firstRow = page.locator('.bbai-library-row').first();
    await expect(firstRow).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(firstRow).toHaveAttribute('data-id', /\d+/);
    await expect(firstRow).toHaveAttribute('data-status', /missing|weak|optimized/);
    await expect(firstRow).toHaveAttribute('data-alt-missing', /true|false/);
    await expect(firstRow.locator('.bbai-library-row-check.bbai-image-checkbox')).toHaveCount(1);

    const checkbox = firstRow.locator('.bbai-library-row-check');
    await checkbox.check();
    await expect(checkbox).toBeChecked();

    await expect(firstRow.locator('[data-action="preview-image"]')).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(firstRow.locator('[data-action="edit-alt-inline"]')).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(firstRow.locator('[data-action="regenerate-single"]')).toHaveAttribute('data-attachment-id', /\d+/);
  });

  test('library pagination controls preserve alt_page state', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&pages=2&alt_page=1`);

    await expect(page.locator('.nai-screen--library')).toContainText(/Page 1 of 2/i);
    const next = page.locator('.nai-screen--library .nai-btn', { hasText: /Next/i });
    await expect(next).toHaveAttribute('href', /alt_page=2/);

    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&pages=2&alt_page=2`);
    await expect(page.locator('.nai-screen--library')).toContainText(/Page 2 of 2/i);
    await expect(page.locator('.nai-screen--library .nai-btn', { hasText: /Previous/i })).toHaveAttribute('href', /alt_page=1/);
  });

  test('library locked credit state keeps generation CTAs guarded and upgrade visible', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&locked=1`);

    await expect(page.locator('[data-action="generate-selected"]')).toHaveAttribute('data-bbai-locked-cta', '1');
    await expect(page.locator('[data-action="regenerate-selected"]')).toHaveAttribute('data-bbai-locked-cta', '1');
    await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-bbai-locked-cta', '1');
    await expect(page.locator('.nai-screen--library [data-action="show-upgrade-modal"]', { hasText: /Upgrade to Pro/i })).toBeVisible();
  });

  test('dashboard and library consume the same exhausted entitlement state', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid&credits=0`);
    await expect(page.locator('[data-bbai-entitlement-exhausted]')).toBeVisible();
    await expect(page.locator('.nai-app')).toHaveAttribute('data-bbai-entitlement-remaining-value', '0');

    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&credits=0`);
    await expect(page.locator('[data-bbai-entitlement-exhausted]')).toBeVisible();
    await expect(page.locator('.nai-app')).toHaveAttribute('data-bbai-entitlement-remaining-value', '0');
    await expect(page.locator('[data-action="generate-selected"]')).toHaveAttribute('data-bbai-action', 'open-upgrade');
    await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-bbai-entitlement-locked', '1');
    await expect(page.locator('[data-action="edit-alt-inline"]').first()).not.toHaveAttribute('aria-disabled', 'true');
    await expect(page.locator('[data-action="preview-image"]').first()).toBeVisible();
  });

  test('dashboard daily limit preserves monthly balance and prompts for refresh or upgrade', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid&daily=exhausted`);

    await expect(page.locator('[data-bbai-entitlement-exhausted]')).toBeVisible();
    await expect(page.locator('[data-bbai-entitlement-notice-title]')).toContainText(/Today's allowance used/i);
    await expect(page.locator('.nai-hero__status [data-bbai-entitlement-daily-used]')).toHaveText('5');
    await expect(page.locator('.nai-app')).toHaveAttribute('data-bbai-entitlement-remaining-value', '23');
    await expect(page.locator('[data-bbai-nai-cta="start-pass"]')).toHaveCount(0);
    await expect(page.locator('[data-bbai-nai-cta="upgrade"]')).toBeVisible();
  });

  test('a final generation or quota denial locks library generation immediately', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);
    await expect(page.locator('[data-action="regenerate-single"]').first()).not.toHaveAttribute('data-bbai-entitlement-locked', '1');

    await page.evaluate(() => {
      (window as any).BBAIEntitlements.consume({
        data: {
          entitlement_state: {
            plan: 'free',
            plan_type: 'free',
            token_limit: 50,
            tokens_used_this_month: 50,
            total_tokens_used: 50,
            tokens_remaining: 0,
            can_generate: false,
            can_autopilot: false,
            is_logged_in: true,
            is_trial: false,
            is_unlimited: false,
            upgrade_required: true,
            quota_state: 'exhausted',
            message: 'Monthly credits exhausted.',
          },
        },
      }, 'generation_success');
    });
    await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-bbai-action', 'open-upgrade');

    await page.evaluate(() => {
      (window as any).BBAIEntitlements.consume({
        success: false,
        data: {
          code: 'quota_exceeded',
          entitlement_state: {
            plan_type: 'free',
            token_limit: 50,
            tokens_used_this_month: 50,
            tokens_remaining: 0,
            can_generate: false,
            can_autopilot: false,
            upgrade_required: true,
            quota_state: 'exhausted',
          },
        },
      }, 'quota_denial');
    });
    await expect(page.locator('[data-action="generate-selected"]')).toHaveAttribute('data-bbai-locked-cta', '1');
  });

  test('auth entitlement response updates remaining credit state without navigation', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=settings&plan=free`);
    const initialUrl = page.url();
    await page.evaluate(() => {
      (window as any).BBAIEntitlements.consume({
        data: {
          entitlement_state: {
            plan_type: 'free',
            token_limit: 50,
            tokens_used_this_month: 0,
            tokens_remaining: 50,
            can_generate: true,
            can_autopilot: false,
            is_logged_in: true,
            quota_state: 'active',
          },
        },
      }, 'register');
    });
    await expect(page.locator('[data-bbai-entitlement-remaining]')).toHaveText('50');
    await page.evaluate(() => {
      (window as any).BBAIEntitlements.merge({ can_autopilot: false }, 'partial_capability_update');
    });
    await expect(page.locator('[data-bbai-entitlement-remaining]')).toHaveText('50');
    expect(page.url()).toBe(initialUrl);
  });

  test('library empty state takes precedence over pagination and loading', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&pages=2`);
    await expect(page.locator('[data-bbai-library-pagination]')).toBeVisible();
    await page.evaluate(() => {
      const root = document.querySelector('[data-nai-screen="library"]') as HTMLElement;
      const body = document.querySelector('#bbai-library-table-body') as HTMLElement;
      const empty = document.createElement('div');
      empty.className = 'bbai-library-filter-empty';
      empty.textContent = 'No images';
      body.appendChild(empty);
      const loading = document.createElement('div');
      loading.setAttribute('data-bbai-library-loading', '');
      loading.textContent = 'Loading';
      root.appendChild(loading);
    });
    await expect(page.locator('[data-bbai-library-pagination]')).toBeHidden();
    await expect(page.locator('.bbai-library-filter-empty')).toBeHidden();
  });

  test('canonical exhausted response overrides a stale Library allowance replay', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid&pages=2`);
    await expect(page.locator('[data-action="regenerate-single"]').first()).not.toHaveAttribute('data-bbai-entitlement-locked', '1');

    await page.evaluate(() => {
      const body = document.querySelector('#bbai-library-table-body') as HTMLElement;
      const root = document.querySelector('[data-nai-screen="library"]') as HTMLElement;
      const empty = document.createElement('div');
      empty.className = 'bbai-library-filter-empty';
      empty.textContent = 'No images are missing';
      body.appendChild(empty);
      const spinner = document.createElement('div');
      spinner.setAttribute('data-bbai-library-loading', '');
      spinner.textContent = 'Loading';
      root.appendChild(spinner);
      (window as any).BBAIEntitlements.consume({
        data: {
          entitlement_state: {
            plan_type: 'free',
            token_limit: 50,
            tokens_used_this_month: 50,
            tokens_remaining: 0,
            can_generate: false,
            can_autopilot: false,
            quota_state: 'exhausted',
            upgrade_required: true,
          },
        },
      }, 'dashboard_state_truth');
    });

    await expect(page.locator('.nai-app')).toHaveAttribute('data-bbai-entitlement-remaining-value', '0');
    await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-bbai-entitlement-locked', '1');
    await expect(page.locator('[data-bbai-library-pagination]')).toBeHidden();
    await expect(page.locator('.bbai-library-filter-empty')).toBeHidden();
    await expect(page.locator('[data-bbai-library-loading]')).toBeVisible();
  });

  test('autopilot reflects canonical capability state', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=autopilot&plan=pro`);
    const toggle = page.locator('[data-bbai-entitlement-autopilot-control]');
    await expect(toggle).toHaveAttribute('aria-disabled', 'false');
    await page.evaluate(() => {
      (window as any).BBAIEntitlements.merge({ can_autopilot: false, can_generate: false, tokens_remaining: 0, quota_state: 'exhausted' });
    });
    await expect(toggle).toHaveAttribute('aria-disabled', 'true');
    await expect(page.locator('[data-bbai-entitlement-autopilot-blocked-copy]')).toBeVisible();
  });

  test('library review and modal hooks stay present before drawer migration', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);

    await expect(page.locator('[data-action="preview-image"]').first()).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(page.locator('[data-action="edit-alt-inline"]').first()).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(page.locator('[data-action="regenerate-single"]').first()).toHaveAttribute('data-attachment-id', /\d+/);
    await expect(page.locator('#bbai-library-selection-bar')).toBeVisible();
  });

  test('library mobile layout keeps nav, filters, rows, and upgrade CTA reachable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=library&plan=free&state=mid`);

    await expect(page.locator('.nai-topbar')).toBeVisible();
    await expect(page.locator('.nai-filter-row')).toBeVisible();
    await expect(page.locator('.nai-lib-row').first()).toBeVisible();
    await expect(page.locator('.nai-screen--library [data-action="show-upgrade-modal"]', { hasText: /Upgrade to Pro/i })).toBeVisible();
  });
});
