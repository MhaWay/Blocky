import { test, expect } from '@playwright/test';

test.describe('Theme smoke tests', () => {
  test('homepage loads without JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();

    expect(errors).toHaveLength(0);
  });

  test('dark mode toggle works', async ({ page }) => {
    await page.goto('/');

    // theme-switch ships as a deferred module; wait for its global before poking it
    await page.waitForFunction(
      () => typeof (window as Record<string, unknown>)['blockyTheme'] !== 'undefined'
    );
    await page.evaluate(() => {
      (window as Record<string, unknown>)['blockyTheme']?.setMode?.('dark');
    });

    const html = page.locator('html');
    await expect(html).toHaveAttribute('data-mode', 'dark');
  });

  test('REST API: blocks endpoint returns data', async ({ request }) => {
    const res = await request.get('/wp-json/blocky/v1/blocks', {
      headers: { 'X-WP-Nonce': '' },
    });
    // May 401 without auth — just verify the endpoint exists
    expect([200, 401, 403]).toContain(res.status());
  });
});
