import { expect, test } from '@playwright/test';
import {
  builderPreviewFrame,
  ensureCanvasBlock,
  loginToBuilder,
  selectCanvasBlock,
} from './helpers/builder';

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

test.describe('Builder F5 descriptor list editor', () => {
  test.beforeEach(async () => {
    await resetFixture();
  });

  test('slider items render as structured rows that serialize and persist', async ({ page }) => {
    await loginToBuilder(page, { postId: FIXTURE_POST_ID });
    const frame = builderPreviewFrame(page);

    await ensureCanvasBlock(page, 'bky/slider', frame);
    await selectCanvasBlock(page, 'bky/slider', frame);

    // Descriptor from the schema: two default slides, four typed fields each.
    const rows = page.locator('[data-list-row]');
    await expect(rows).toHaveCount(2);
    await expect(page.getByText('Button label').first()).toBeVisible();
    await expect(page.getByText('Link URL').first()).toBeVisible();

    await page.getByRole('button', { name: /Add item/ }).click();
    await expect(rows).toHaveCount(3);

    await rows.nth(2).locator('input').first().fill('Offerta speciale');

    await page.keyboard.press('Control+s');
    await expect(page.getByText('Saved').first()).toBeVisible({ timeout: 15000 });

    // Reload: the saved pipe-protocol line must round-trip through the parser.
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await ensureCanvasBlock(page, 'bky/slider', frame);
    await selectCanvasBlock(page, 'bky/slider', frame);
    await expect(page.locator('[data-list-row]')).toHaveCount(3);
    await expect
      .poll(
        async () => {
          return page.locator('[data-list-row]').nth(2).locator('input').first().inputValue();
        },
        { timeout: 8000 }
      )
      .toBe('Offerta speciale');
  });
});
