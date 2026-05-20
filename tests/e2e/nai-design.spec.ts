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

import { test, expect } from '@playwright/test';
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
    await expect(page.locator('[data-nai-screen="dashboard"]')).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Today's pass/i })).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Library health/i })).toBeVisible();
    await expect(page.locator('.nai-eyebrow', { hasText: /Library coverage/i })).toBeVisible();
  });

  test('dashboard hero shows queue + primary CTA when work remains (Free + mid library)', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=free&state=mid`);
    // Free hero CTA reads "Start today's pass".
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Start today's pass/i })).toBeVisible();
    // Footer metrics show monthly usage row.
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Monthly usage/i })).toBeVisible();
  });

  test('dashboard Pro variant drops quota framing in favour of automation status', async ({ page }) => {
    await page.goto(`${BASE}/tests/preview/nai-preview.php?screen=dashboard&plan=pro&state=near`);
    // Pro hero CTA reads "Run optimisation pass".
    await expect(page.locator('.nai-hero .nai-btn--primary', { hasText: /Run optimisation pass/i })).toBeVisible();
    // Pro footer metrics: "Improvements this month" replaces "Monthly usage".
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Improvements this month/i })).toBeVisible();
    await expect(page.locator('.nai-footer-metrics .nai-footer-metrics__label', { hasText: /Monthly usage/i })).toHaveCount(0);
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
});
