import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import { builderPreviewFrame, loginToBuilder } from './helpers/builder';

/*
 * Reusable section patterns (matrix item 4, v1 self-hosted): insert a bundled
 * pattern from the sidebar, persist it, and see it render on the frontend;
 * save the current page as a user pattern, see it after a reload, delete it.
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
  wp(['option', 'delete', 'blocky_user_patterns']);
  postId = Number(
    wp([
      'post',
      'create',
      '--post_title=Patterns E2E',
      '--post_type=page',
      '--post_status=publish',
      '--porcelain',
    ])
  );
});

test.afterAll(() => {
  if (postId) wp(['post', 'delete', String(postId), '--force']);
  wp(['option', 'delete', 'blocky_user_patterns']);
});

function toolbar(page: import('@playwright/test').Page) {
  return page.locator('header');
}
function panel(page: import('@playwright/test').Page) {
  return page.locator('aside');
}

test.describe('Section patterns', () => {
  test('insert a bundled pattern, save, frontend renders it', async ({ page }) => {
    await loginToBuilder(page, { postId });
    await page.getByRole('button', { name: /^Patterns$/i }).click();
    await panel(page).getByRole('button', { name: 'Feature grid' }).click();

    const frame = builderPreviewFrame(page);
    await expect(frame.getByText('Why teams switch')).toBeVisible();
    await expect(frame.locator('svg.lucide-zap').first()).toBeVisible();

    await expect(toolbar(page).getByRole('button', { name: 'Save', exact: true })).toBeEnabled({
      timeout: 5000,
    });
    const saved = page.waitForResponse(
      (r) =>
        new URL(r.url()).pathname.endsWith('/documents/' + postId) &&
        r.request().method() === 'POST',
      { timeout: 30000 }
    );
    await toolbar(page).getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(200);

    await page.goto(SITE + '/?page_id=' + postId, { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: 'Why teams switch' })).toBeVisible();
    await expect(page.locator('svg.lucide-zap').first()).toBeVisible();
  });

  test('save the page as a pattern, survives reload, deletes', async ({ page }) => {
    await loginToBuilder(page, { postId });
    await page.getByRole('button', { name: /^Patterns$/i }).click();

    await panel(page).getByPlaceholder('Name this page as a pattern…').fill('My saved page');
    await panel(page).getByRole('button', { name: 'Save', exact: true }).click();
    await expect(panel(page).getByText('Pattern saved.')).toBeVisible();
    await expect(
      panel(page).getByRole('button', { name: 'My saved page', exact: true })
    ).toBeVisible();

    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /^Patterns$/i }).click();
    await expect(
      panel(page).getByRole('button', { name: 'My saved page', exact: true })
    ).toBeVisible();

    await panel(page).getByRole('button', { name: 'Delete My saved page' }).click();
    await expect(
      panel(page).getByRole('button', { name: 'My saved page', exact: true })
    ).toHaveCount(0);
  });
});
