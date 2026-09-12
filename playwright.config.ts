import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir:  './e2e',
  timeout:  30_000,
  retries:  process.env['CI'] ? 2 : 0,
  reporter: process.env['CI'] ? [['github'], ['html']] : 'list',

  use: {
    baseURL:      'http://127.0.0.1:8888',
    trace:        'on-first-retry',
    screenshot:   'only-on-failure',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],

  // Start wp-env before running tests
  webServer: {
    command: 'pnpm wp-env start',
    url:     'http://127.0.0.1:8888',
    reuseExistingServer: !process.env['CI'],
    timeout: 60_000,
  },
});
