import { defineConfig, devices } from '@playwright/test';

// Same origin as the WP siteurl: cross-host logins silently drop redirect_to
const SITE = process.env['BLOCKY_E2E_SITE'] ?? 'http://127.0.0.1:8888';

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  retries: process.env['CI'] ? 2 : 0,
  reporter: process.env['CI'] ? [['github'], ['html']] : 'list',

  use: {
    baseURL: SITE,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  // Start wp-env before running tests
  webServer: {
    command: 'pnpm wp-env start',
    url: SITE,
    reuseExistingServer: !process.env['CI'],
    timeout: 60_000,
  },
});
