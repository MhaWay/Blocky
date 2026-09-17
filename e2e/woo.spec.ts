import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import {
  builderPreviewFrame,
  ensureCanvasBlock,
  loginToBuilder,
  selectCanvasBlock,
} from './helpers/builder';

/*
 * WooCommerce subset (matrix item 5). All product markup is static and
 * add-to-cart uses WooCommerce's native ?add-to-cart URL — the test follows
 * the real click path into the cart page, no custom JS involved.
 */

const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://192.168.1.8:8888';
const CONTAINER = process.env.BLOCKY_E2E_CONTAINER ?? 'local-wp-wp-1';

function wp(args: string[]): string {
  return execFileSync('docker', ['exec', CONTAINER, 'wp', ...args, '--allow-root'], {
    encoding: 'utf-8',
  }).trim();
}

let postId = 0;
let widgetTeeId = 0;
let hoodieId = 0;

test.beforeAll(() => {
  wp(['eval-file', '/tmp/woo-products2.php']);
  const out = wp([
    'eval',
    'foreach (array("Widget Tee","Panel Hoodie") as $n) { $p = get_page_by_title($n, OBJECT, "product"); echo ($p ? $p->ID : 0), " "; }',
  ]);
  const ids = out.split(/\s+/).map(Number);
  widgetTeeId = ids[0] ?? 0;
  hoodieId = ids[1] ?? 0;
  postId = Number(
    wp([
      'post',
      'create',
      '--post_title=Woo E2E',
      '--post_type=page',
      '--post_status=publish',
      '--porcelain',
    ])
  );
});

test.afterAll(() => {
  if (postId) wp(['post', 'delete', String(postId), '--force']);
});

function toolbar(page: import('@playwright/test').Page) {
  return page.locator('header');
}

async function saveDocument(page: import('@playwright/test').Page) {
  await expect(toolbar(page).getByRole('button', { name: 'Save', exact: true })).toBeEnabled({
    timeout: 5000,
  });
  const saved = page.waitForResponse(
    (r) =>
      new URL(r.url()).pathname.endsWith('/documents/' + postId) && r.request().method() === 'POST',
    { timeout: 30000 }
  );
  await toolbar(page).getByRole('button', { name: 'Save', exact: true }).click();
  expect((await saved).status()).toBe(200);
}

test.describe('WooCommerce blocks', () => {
  test('product grid renders products and the real add-to-cart click reaches the cart', async ({
    page,
  }) => {
    test.skip(widgetTeeId === 0, 'products missing');
    await loginToBuilder(page, { postId });
    await ensureCanvasBlock(page, 'bky/product-grid');

    const frame = builderPreviewFrame(page);
    await expect(frame.getByText('Widget Tee').first()).toBeVisible();
    await expect(frame.locator('a[href*="add-to-cart="]').first()).toBeVisible();

    await saveDocument(page);

    await page.goto(SITE + '/?page_id=' + postId, { waitUntil: 'networkidle' });
    await expect(page.getByRole('link', { name: 'Widget Tee' }).first()).toBeVisible();
    await page
      .locator('article', { hasText: 'Widget Tee' })
      .getByRole('link', { name: 'Add to cart' })
      .click();
    // Woo's default add-to-cart does not redirect (and the hello theme prints
    // notices only inside Woo templates), so the classic verification point is
    // the cart page itself — the clicked product must be listed there.
    await page.goto(SITE + '/cart/', { waitUntil: 'networkidle' });
    await expect(page.getByRole('link', { name: 'Widget Tee' })).toBeVisible();
  });

  test('featured product shows the product selected by id', async ({ page }) => {
    test.skip(hoodieId === 0, 'products missing');
    await loginToBuilder(page, { postId });
    await ensureCanvasBlock(page, 'bky/product-featured');
    await selectCanvasBlock(page, 'bky/product-featured');

    await page.locator('aside label:has-text("Product ID") input').fill(String(hoodieId));

    const frame = builderPreviewFrame(page);
    await expect(frame.getByRole('heading', { name: 'Panel Hoodie', level: 2 })).toBeVisible({
      timeout: 10000,
    });

    await saveDocument(page);

    await page.goto(SITE + '/?page_id=' + postId, { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: 'Panel Hoodie', level: 2 })).toBeVisible();
  });
});
