import { test, expect } from '@playwright/test';

// Skips when BBAI_E2E_BASE_URL is unset (default clone / CI without WP).
const BASE = process.env.BBAI_E2E_BASE_URL || 'http://localhost:8888';
const ADMIN_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';

async function login(page: any) {
  await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', ADMIN_USER);
    await page.fill('#user_pass', ADMIN_PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);
  }
  await expect(page).toHaveURL(/wp-admin/);
}

test('Phase 1 validation: Library vs Dashboard stability', async ({ page }) => {
  test.skip(!process.env.BBAI_E2E_BASE_URL, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');
  test.setTimeout(90_000);
  const consoleLines: string[] = [];
  page.on('console', (msg: any) => consoleLines.push(msg.text()));

  await login(page);

  // 1) ALT Library counts.
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai-library`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.bbai-filter-group__count');

  const libraryCounts = await page.$$eval('#bbai-review-filter-tabs .bbai-filter-group__item', (items: Element[]) => {
    const out: Record<string, number> = {};
    const readInt = (s: string) => parseInt((s || '0').replace(/[^\d]/g, ''), 10) || 0;
    for (const item of items) {
      const label = (item.querySelector('.bbai-filter-group__label')?.textContent || '').trim().toLowerCase();
      const count = readInt(item.querySelector('.bbai-filter-group__count')?.textContent || '0');
      const key =
        label === 'all' ? 'total'
        : label === 'missing' ? 'missing'
        : (label.includes('review') || label.includes('needs')) ? 'needs_review'
        : label.includes('optimized') ? 'optimized'
        : '';
      if (key) out[key] = count;
    }
    return out;
  });

  // Expected keys: total, missing, needs_review, optimized
  expect(typeof libraryCounts.total).toBe('number');
  expect(typeof libraryCounts.missing).toBe('number');
  expect(typeof libraryCounts.needs_review).toBe('number');
  expect(typeof libraryCounts.optimized).toBe('number');

  // 2) Hard refresh dashboard: simulate by new navigation + immediate snapshot.
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('[data-bbai-dashboard-root],[data-bbai-missing-count],#bbai-dashboard-root', { timeout: 15000 }).catch(() => {});

  const readDashboardCounts = async () => {
    return await page.evaluate(() => {
      const root =
        document.querySelector<HTMLElement>('[data-bbai-dashboard-root]') ||
        document.querySelector<HTMLElement>('#bbai-dashboard-root') ||
        document.querySelector<HTMLElement>('[data-bbai-missing-count]') ||
        document.querySelector<HTMLElement>('.bbai-dashboard') ||
        document.body;

      const get = (name: string) => {
        const v =
          root.getAttribute(`data-bbai-${name}`) ||
          root.getAttribute(`data-${name}`) ||
          (root as any).dataset?.[name.replace(/-([a-z])/g, (_, c) => c.toUpperCase())] ||
          '';
        const n = parseInt(String(v).replace(/[^\d]/g, ''), 10);
        return Number.isFinite(n) ? n : 0;
      };

      return {
        total: get('total-count'),
        missing: get('missing-count'),
        needs_review: get('weak-count'),
        optimized: get('optimized-count'),
      };
    });
  };

  const dashFirst = await readDashboardCounts();
  await page.waitForTimeout(5500);
  const dashAfter5s = await readDashboardCounts();

  // 3) Confirm first paint matches library and no change after 5 seconds.
  expect(dashFirst).toEqual({
    total: libraryCounts.total,
    missing: libraryCounts.missing,
    needs_review: libraryCounts.needs_review,
    optimized: libraryCounts.optimized,
  });
  expect(dashAfter5s).toEqual(dashFirst);

  // Forbidden console markers.
  const forbidden = ['fallback_state', 'using_fallback', 'local_mixed_attention'];
  const bad = consoleLines.filter((l) => forbidden.some((k) => l.includes(k)));
  expect(bad, `Forbidden console markers found:\n${bad.join('\n')}`).toEqual([]);
});

