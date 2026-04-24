import { defineConfig } from '@playwright/test';

// NOTE: Tests must run serially due to shared WP state (auth, options, DB)
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30000,

  use: {
    baseURL: 'http://localhost:8888',
    headless: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },

  reporter: [['list'], ['html', { open: 'never' }]],

  testIgnore: [
    '**/.claude/**',
    '**/.cursor/**',
    '**/.wp-env/**',
    '**/node_modules/**',
    '**/worktrees/**',
  ],
});
