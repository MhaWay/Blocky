import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import { builderPreviewFrame, loginToBuilder } from './helpers/builder';

/*
 * Human-mode permission flows for the per-post check (wp.org review):
 * an author opening someone else's page in the builder gets a 403 from the
 * document endpoint and must see a visible error, not a broken canvas;
 * the same author saving their own page runs the full compile pipeline.
 */

const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://192.168.1.8:8888';
const CONTAINER = process.env.BLOCKY_E2E_CONTAINER ?? 'local-wp-wp-1';
const LOGIN = 'permtestauthor';
const PASS = 'Hz7!permtest';
const ADMIN_POST = 9;

function wp(args: string[]): string {
  return execFileSync('docker', ['exec', CONTAINER, 'wp', ...args, '--allow-root'], {
    encoding: 'utf-8',
  }).trim();
}

let authorPost = 0;

test.beforeAll(() => {
  wp(['user', 'delete', LOGIN, '--yes']);
  const id = wp([
    'user',
    'create',
    LOGIN,
    'perm@example.test',
    '--role=author',
    '--user_pass=' + PASS,
    '--porcelain',
  ]);
  authorPost = Number(
    wp([
      'post',
      'create',
      '--post_title=Perms Own Page',
      '--post_type=page',
      '--post_status=publish',
      '--post_author=' + id,
      '--porcelain',
    ])
  );
});

test.afterAll(() => {
  wp(['post', 'delete', String(authorPost), '--force']);
  wp(['user', 'delete', LOGIN, '--yes']);
});

test.describe('Author permissions', () => {
  test('opening another author page shows an error instead of the document', async ({ page }) => {
    await page.goto(SITE + '/wp-login.php');
    await page.locator('#user_login').fill(LOGIN);
    await page.locator('#user_pass').fill(PASS);
    await page.locator('#wp-submit').click();
    await page.waitForURL(/wp-admin/, { timeout: 15000 });

    const docResponse = page.waitForResponse(
      (r) => new URL(r.url()).pathname.includes('/documents/' + ADMIN_POST),
      { timeout: 20000 }
    );
    await page.goto(SITE + '/wp-admin/admin.php?page=blocky-builder&post_id=' + ADMIN_POST, {
      waitUntil: 'domcontentloaded',
    });
    const res = await docResponse;
    expect(res.status()).toBe(403);
    await expect(page.getByRole('alert')).toContainText('do not have permission', {
      timeout: 10000,
    });
    await page.screenshot({ path: '/tmp/author-blocked.png' });
  });

  test('author compiles and saves their own page end to end', async ({ page }) => {
    await loginToBuilder(page, { postId: authorPost, attempts: 1 });
    const frame = builderPreviewFrame(page);
    await expect(frame.locator('[data-bky-type]').first()).toBeVisible({ timeout: 15000 });

    await page.evaluate(() => {
      (
        window as unknown as {
          BlockyBuilderDebug?: { store?: { setState: (p: Record<string, unknown>) => void } };
        }
      ).BlockyBuilderDebug?.store?.setState({ isDirty: true });
    });
    const cssResponse = page.waitForResponse(
      (r) => new URL(r.url()).pathname.endsWith('/documents/' + String(authorPost) + '/css'),
      { timeout: 30000 }
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const res = await cssResponse;
    expect(res.status()).toBe(200);
    expect(wp(['post', 'meta', 'get', String(authorPost), '_blocky_css_file'])).toContain(
      '/uploads/blocky/page-'
    );
  });
});
