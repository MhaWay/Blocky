import { expect, test } from '@playwright/test';
import { builderPreviewFrame, ensureCanvasBlock, loginToBuilder } from './helpers/builder';

const FIXTURE_POST_ID = Number(process.env.BLOCKY_E2E_POST_ID ?? 11);
const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY ?? '';

const EMPTY_DOC = {
  root: 'root',
  nodes: { root: { id: 'root', type: 'bky/section', props: {} } },
};

async function resetFixture(): Promise<void> {
  if (!KEY) throw new Error('BLOCKY_API_KEY is required to reset the fixture post');
  const res = await fetch(`${SITE}/wp-json/blocky/v1/documents/${FIXTURE_POST_ID}`, {
    method: 'POST',
    headers: { authorization: `Bearer ${KEY}`, 'content-type': 'application/json' },
    body: JSON.stringify({ document: EMPTY_DOC }),
  });
  if (!res.ok) throw new Error(`fixture reset failed: ${res.status}`);
}

test.describe('Builder F3 undo/redo and shortcuts', () => {
  test.beforeEach(async () => {
    await resetFixture();
  });

  test('undo removes the last inserted block and redo restores it', async ({ page }) => {
    await loginToBuilder(page, { postId: FIXTURE_POST_ID });
    const frame = builderPreviewFrame(page);

    await ensureCanvasBlock(page, 'bky/heading', frame);
    await expect(frame.locator('[data-bky-type="bky/heading"]').first()).toBeVisible();
    await page.waitForTimeout(700); /* leave the coalescing window */

    await page.keyboard.press('Control+z');
    await expect(frame.locator('[data-bky-type="bky/heading"]')).toHaveCount(0, { timeout: 8000 });

    await page.keyboard.press('Control+Shift+z');
    await expect(frame.locator('[data-bky-type="bky/heading"]').first()).toBeVisible();
  });

  test('ctrl+s saves the document and clears the dirty flag', async ({ page }) => {
    await loginToBuilder(page, { postId: FIXTURE_POST_ID });
    const frame = builderPreviewFrame(page);
    await ensureCanvasBlock(page, 'bky/text', frame);
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();

    await page.keyboard.press('Control+s');
    await expect(page.getByText('Saved', { exact: true })).toBeVisible({ timeout: 20000 });
  });
});
