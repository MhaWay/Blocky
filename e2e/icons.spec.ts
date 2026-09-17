import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import { builderPreviewFrame, loginToBuilder } from './helpers/builder';

/*
 * Icon library (gap 1 of docs/research/12): bundled Lucide set picked from the
 * inspector, plus user SVG uploads sanitized server-side. Hostile SVGs are
 * rejected with a visible error; benign ones lose event handlers but keep art.
 */

const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://192.168.1.8:8888';
const CONTAINER = process.env.BLOCKY_E2E_CONTAINER ?? 'local-wp-wp-1';

function wp(args: string[]): string {
  return execFileSync('docker', ['exec', CONTAINER, 'wp', ...args, '--allow-root'], {
    encoding: 'utf-8',
  }).trim();
}

let postId = 0;

test.beforeAll(() => {
  postId = Number(
    wp([
      'post',
      'create',
      '--post_title=Icons E2E',
      '--post_type=page',
      '--post_status=publish',
      '--porcelain',
    ])
  );
});

test.afterAll(() => {
  if (postId) wp(['post', 'delete', String(postId), '--force']);
});

async function addIconBlock(page: import('@playwright/test').Page): Promise<void> {
  await page.locator('input[placeholder="Search blocks…"]').fill('Icon');
  await page.getByText('Icon', { exact: true }).first().click();
  const icon = builderPreviewFrame(page).locator('[data-bky-type="bky/icon"]').first();
  await expect(icon).toBeVisible();
  await icon.click();
}

test.describe('Icon library', () => {
  test('pick a bundled Lucide icon, save, inline SVG renders on the frontend', async ({ page }) => {
    await loginToBuilder(page, { postId });
    await addIconBlock(page);

    await page.getByRole('button').filter({ hasText: '★' }).first().click();
    await page.locator('input[placeholder="Search icons…"]').fill('arrow-up');
    await page.locator('button[title="arrow-up"]').first().click();

    await expect(page.getByText('lucide-arrow-up').first()).toBeVisible();
    await expect(builderPreviewFrame(page).locator('svg.lucide-arrow-up').first()).toBeVisible();

    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled({
      timeout: 5000,
    });
    const saved = page.waitForResponse(
      (r) =>
        new URL(r.url()).pathname.endsWith('/documents/' + postId) &&
        r.request().method() === 'POST',
      { timeout: 30000 }
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(200);

    await page.goto(SITE + '/?page_id=' + postId, { waitUntil: 'networkidle' });
    await expect(
      page.locator('span[data-bky-icon="lucide-arrow-up"] svg.lucide-arrow-up')
    ).toHaveCount(1);
  });

  test('hostile SVG is rejected visibly, benign SVG survives sanitizing', async ({ page }) => {
    await loginToBuilder(page, { postId });
    await builderPreviewFrame(page).locator('[data-bky-type="bky/icon"]').first().click();

    // Re-open the picker and try the malicious fixture first.
    await page.getByRole('button').filter({ hasText: 'lucide-arrow-up' }).first().click();
    await page.locator('input[type="file"]').setInputFiles('e2e/fixtures/icon-evil.svg');
    await expect(page.getByRole('alert')).toContainText('rejected', { timeout: 10000 });

    // Now the benign one: it must upload, minus event handlers and styles.
    await page.locator('input[type="file"]').setInputFiles('e2e/fixtures/icon-ok.svg');
    await expect(page.getByText(/^upload-\d+$/).first()).toBeVisible({ timeout: 10000 });

    const saved = page.waitForResponse(
      (r) =>
        new URL(r.url()).pathname.endsWith('/documents/' + postId) &&
        r.request().method() === 'POST',
      { timeout: 30000 }
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(200);

    await page.goto(SITE + '/?page_id=' + postId, { waitUntil: 'networkidle' });
    const host = page.locator('span[data-bky-icon^="upload-"] svg').first();
    await expect(host).toHaveCount(1);
    const html = await host.evaluate((el) => el.outerHTML);
    expect(html).toContain('E2EOkIcon');
    expect(html.toLowerCase()).not.toContain('onload');
    expect(html.toLowerCase()).not.toContain('style=');
    expect(html.toLowerCase()).not.toContain('script');
  });
});
