import { test, expect } from '@playwright/test';

// Skips when BBAI_E2E_BASE_URL is unset (default clone / CI without WP).
const BASE = process.env.BBAI_E2E_BASE_URL || 'http://localhost:8888';
const WP_USER = process.env.BBAI_E2E_ADMIN_USER || 'admin';
const WP_PASS = process.env.BBAI_E2E_ADMIN_PASS || 'password';

async function wpLogin(page: any) {
  await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);
  }
  await expect(page).toHaveURL(/wp-admin/);
}

test('Connect account via normal auth flow (register)', async ({ page }) => {
  test.skip(!process.env.BBAI_E2E_BASE_URL, 'Set BBAI_E2E_BASE_URL to your local WP base (no trailing slash)');
  test.setTimeout(180_000);

  const consoleLines: string[] = [];
  page.on('console', (msg: any) => consoleLines.push(msg.text()));

  await wpLogin(page);

  const email = `bbai.local+${Date.now()}@example.com`;
  const password = `BbaiLocal!${Date.now()}aA`;

  // Open dashboard and click the normal "Create free account" CTA.
  await page.goto(`${BASE}/wp-admin/admin.php?page=bbai`, { waitUntil: 'domcontentloaded' });
  const registerCta = page
    .locator('[data-auth-tab="register"][data-action="show-dashboard-auth"], [data-auth-tab="register"][data-action="show-auth-modal"]')
    .first();

  if (await registerCta.count()) {
    await registerCta.click();
  } else {
    // Fallback: invoke the same UI hook the inline boot code uses.
    await page.evaluate(() => {
      if (typeof (window as any).showAuthModal === 'function') {
        (window as any).showAuthModal('register', 'register');
      } else if ((window as any).authModal && typeof (window as any).authModal.show === 'function') {
        (window as any).authModal.show({ context: 'register' });
        if (typeof (window as any).authModal.showRegisterForm === 'function') {
          (window as any).authModal.showRegisterForm('register');
        }
      }
    });
  }

  // Wait for modal to become visible.
  const modal = page.locator('#alttext-auth-modal');
  await expect(modal).toBeVisible({ timeout: 20000 });

  // Some builds show login first; click "Create one".
  const showRegister = page.locator('#show-register');
  if (await showRegister.count()) {
    await showRegister.click().catch(() => {});
  }

  const resolveRegisterForm = async () => {
    const candidates = [
      page.locator('form[aria-label="Create a new BeepBeep AI account"]'),
      page.locator('#alttext-register-form form'),
      page.locator('form#register-form'),
    ];
    for (const c of candidates) {
      if (await c.count()) {
        return c.first();
      }
    }
    return page.locator('form#register-form'); // will fail with a clearer error below
  };

  const registerForm = await resolveRegisterForm();
  await expect(registerForm).toBeVisible({ timeout: 30000 });

  const emailInput = registerForm.getByLabel('Email');
  const passInput = registerForm.getByLabel('Password');
  const confirmInput = registerForm.getByLabel('Confirm Password');

  await expect(emailInput).toBeVisible({ timeout: 30000 });
  await expect(emailInput).toBeEditable({ timeout: 30000 });

  // Use click+type to avoid occasional modal focus/overlay flakiness.
  await emailInput.click();
  await emailInput.type(email, { delay: 10 });
  await passInput.click();
  await passInput.type(password, { delay: 10 });
  await confirmInput.click();
  await confirmInput.type(password, { delay: 10 });

  await Promise.all([
    // Successful signup redirects back to dashboard.
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {}),
    registerForm.getByRole('button', { name: /create account/i }).click(),
  ]);

  // If still on same page, give it a moment for JS redirect.
  await page.waitForTimeout(2000);

  // Confirm connected account.
  const hasConnected = await page.evaluate(() => {
    const root =
      document.querySelector<HTMLElement>('[data-bbai-dashboard-root]') ||
      document.querySelector<HTMLElement>('#bbai-dashboard-root') ||
      document.body;
    return (root.getAttribute('data-bbai-has-connected-account') || '0') === '1';
  });

  // If not connected, surface any visible auth error message.
  if (!hasConnected) {
    const errorText = await page.evaluate(() => {
      const el =
        document.querySelector('.alttext-auth-message.alttext-auth-message--error') ||
        document.querySelector('.alttext-auth-error') ||
        document.querySelector('[data-alttext-auth-error]');
      return (el && (el as HTMLElement).innerText) ? (el as HTMLElement).innerText.trim() : '';
    });
    throw new Error(
      `Account did not connect after registration. Visible error: ${errorText || '(none)'}\nConsole:\n${consoleLines.join('\n')}`
    );
  }

  expect(hasConnected).toBe(true);
});

