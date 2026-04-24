import { Page } from '@playwright/test';

export async function forceLoggedOut(page: Page) {
  await page.context().clearCookies();

  await page.evaluate(() => {
    localStorage.clear();
    sessionStorage.clear();
  }).catch(() => {});

  try {
    await page.goto('/wp-login.php?action=logout', { waitUntil: 'domcontentloaded' });
  } catch {
    // ignore — WP may redirect or reject without nonce
  }

  await page.addInitScript(() => {
    try {
      delete (window as any).BBAI_DASH;
      delete (window as any).bbaiResolvedCredits;
    } catch {}
  });
}
