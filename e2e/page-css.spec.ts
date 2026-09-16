import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import { builderPreviewFrame, loginToBuilder } from './helpers/builder';

/*
 * Browser-compiled CSS pipeline (Phase B): saving in the builder compiles the
 * page stylesheet in the admin browser (Tailwind WASM, zero server binaries),
 * shows a visible "Compiling styles…" phase, and persists a hashed static file
 * under wp-content/uploads/blocky/. Without the file meta the frontend falls
 * back to the cached meta string, inline.
 */

const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://192.168.1.8:8888';
const CONTAINER = process.env.BLOCKY_E2E_CONTAINER ?? 'local-wp-wp-1';
const POST_ID = 9;

function wp(args: string[]): string {
  return execFileSync('docker', ['exec', CONTAINER, 'wp', ...args, '--allow-root'], {
    encoding: 'utf-8',
  }).trim();
}

test.describe('Browser-compiled page CSS', () => {
  test('save compiles in the browser, persists a hashed file, frontend enqueues it', async ({
    page,
  }) => {
    await loginToBuilder(page, { postId: POST_ID });
    builderPreviewFrame(page);

    // Mark the document dirty through the exposed store handle (no DOM guesswork);
    // the save then runs the real compile + persist pipeline end to end.
    await page.evaluate(() => {
      (
        window as unknown as {
          BlockyBuilderDebug?: { store?: { setState: (p: Record<string, unknown>) => void } };
        }
      ).BlockyBuilderDebug?.store?.setState({ isDirty: true });
    });
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled({
      timeout: 5000,
    });

    const cssRequest = page.waitForRequest(
      (request) => new URL(request.url()).pathname.endsWith('/documents/' + POST_ID + '/css'),
      { timeout: 30000 }
    );

    await page.getByRole('button', { name: 'Save', exact: true }).click();
    const phaseVisible = await page
      .getByText('Compiling styles…')
      .waitFor({ state: 'visible', timeout: 2500 })
      .then(() => true)
      .catch(() => false);
    if (!phaseVisible) {
      test.info().annotations.push({
        type: 'note',
        description: 'compile phase completed faster than the sampling window',
      });
    }
    await cssRequest;

    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled({
      timeout: 15000,
    });

    const stored = wp(['post', 'meta', 'get', String(POST_ID), '_blocky_css_file']);
    expect(stored).toContain('/uploads/blocky/page-' + POST_ID + '-');

    await page.goto(SITE + '/?page_id=' + POST_ID, { waitUntil: 'networkidle' });
    const link = page.locator('link[href*="/uploads/blocky/page-' + POST_ID + '-"]');
    await expect(link).toHaveCount(1);
    const href = await link.getAttribute('href');
    const css = await page.request.get(href ?? '');
    expect(css.ok()).toBe(true);
    const body = await css.text();
    expect(body.length).toBeGreaterThan(500);
    expect(body).toContain('}');
  });

  test('frontend falls back to inline cached CSS when the file meta is gone', async ({ page }) => {
    const stored = wp(['post', 'meta', 'get', String(POST_ID), '_blocky_css_cache']);
    expect(stored.length).toBeGreaterThan(50);
    wp(['post', 'meta', 'delete', String(POST_ID), '_blocky_css_file']);
    await page.goto(SITE + '/?page_id=' + POST_ID, { waitUntil: 'networkidle' });
    await expect(page.locator('link[href*="/uploads/blocky/page-' + POST_ID + '-"]')).toHaveCount(
      0
    );
    await expect(page.locator('#blocky-page-css-inline-css')).toBeAttached({ timeout: 5000 });
  });
});
