import { defineConfig, devices } from '@playwright/test';

/**
 * WordPress + BeepBeep AI admin regression tests.
 * Requires BBAI_E2E_BASE_URL (e.g. http://127.0.0.1:8888).
 * Optional BBAI_E2E_STORAGE_STATE = path to playwright storageState.json after login.
 */
const baseURL = process.env.BBAI_E2E_BASE_URL || 'http://127.0.0.1:8888';
const storageState = process.env.BBAI_E2E_STORAGE_STATE || undefined;

export default defineConfig({
  testDir: '.',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    ...(storageState ? { storageState } : {}),
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
