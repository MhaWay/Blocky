import { expect, test } from '@playwright/test';
import { builderPreviewFrame, loginToBuilder } from './helpers/builder';

const FIXTURE_POST_ID = Number(process.env.BLOCKY_E2E_POST_ID ?? 11);
const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY ?? '';

// A representative, rich but chunk-free document (no code-highlight/lottie).
const PERF_DOC = {
  root: 'root',
  nodes: {
    root: {
      id: 'root',
      type: 'bky/section',
      props: {},
      slots: { default: ['a', 'b', 'c', 'd'] },
      variants: {},
    },
    a: {
      id: 'a',
      type: 'bky/heading',
      props: { text: 'Performance budget page', level: 1 },
      slots: {},
      variants: {},
    },
    b: {
      id: 'b',
      type: 'bky/text',
      props: {
        text: 'A paragraph with enough words to be realistic about text weight on a builder page.',
      },
      slots: {},
      variants: {},
    },
    c: { id: 'c', type: 'bky/slider', props: {}, slots: {}, variants: {} },
    d: { id: 'd', type: 'bky/divider', props: {}, slots: {}, variants: {} },
  },
};

async function seedFixture(): Promise<void> {
  if (!KEY) throw new Error('BLOCKY_API_KEY is required to seed the perf fixture');
  const res = await fetch(`${SITE}/wp-json/blocky/v1/documents/${FIXTURE_POST_ID}`, {
    method: 'POST',
    headers: { authorization: `Bearer ${KEY}`, 'content-type': 'application/json' },
    body: JSON.stringify({ document: PERF_DOC }),
  });
  if (!res.ok) throw new Error(`perf fixture seed failed: ${res.status}`);
}

test.describe('F6 frontend size budgets', () => {
  test('a rich builder page ships within the CSS/JS/request budgets', async ({ page }) => {
    await seedFixture();

    await page.goto(`${SITE}/mcp-target/`, { waitUntil: 'networkidle' });

    const assets = await page.evaluate(() => {
      const out = { css: 0, js: 0, cssFiles: 0, jsFiles: 0, lazyChunks: 0, total: 0 };
      for (const entry of performance.getEntriesByType('resource')) {
        const bytes = entry.encodedBodySize;
        out.total += bytes;
        if (entry.initiatorType === 'css' || entry.name.endsWith('.css')) {
          out.css += bytes;
          out.cssFiles += 1;
        } else if (entry.name.endsWith('.js')) {
          out.js += bytes;
          out.jsFiles += 1;
          if (/assets\/(index|lottie|_commonjs)/.test(entry.name)) out.lazyChunks += 1;
        }
      }
      return out;
    });

    // Raw budgets; gz budgets run in tools/ci/size-budget.sh.
    expect(assets.cssFiles, 'css file count').toBeLessThanOrEqual(4);
    expect(assets.jsFiles, 'js file count').toBeLessThanOrEqual(2);
    expect(assets.css, 'raw CSS bytes').toBeLessThanOrEqual(340_000);
    expect(assets.js, 'raw JS bytes (runtime entry)').toBeLessThanOrEqual(24_000);
    // No lazy chunk may load on a page that has no lazy feature.
    expect(assets.lazyChunks, 'lazy chunks on chunk-free page').toBe(0);

    // Content is actually present, so the budget cannot pass on a blank page.
    await expect(page.getByRole('heading', { name: 'Performance budget page' })).toBeVisible();
    await expect(page.getByText('Highlight your offer')).toBeVisible();
  });
});
