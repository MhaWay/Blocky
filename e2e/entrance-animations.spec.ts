import { expect, test } from '@playwright/test';
import {
  builderPreviewFrame,
  inspectorTab,
  loginToBuilder,
  selectCanvasBlock,
} from './helpers/builder';

// Entrance animations fixture page. Create it once:
//   wp post create --post_type=page --post_title='Anim Test' --post_name=anim-test --post_status=publish
// The REST seeding below rewrites its document on every run.
const FIXTURE_POST_ID = Number(process.env.BLOCKY_ANIM_POST_ID ?? 30);
const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY ?? '';
const PATH = '/anim-test/';

const DOC = {
  root: 'root',
  nodes: {
    root: {
      id: 'root',
      type: 'bky/section',
      props: {},
      slots: { default: ['tall', 'below', 'onload'] },
      variants: {},
    },
    tall: {
      id: 'tall',
      type: 'bky/section',
      props: {},
      slots: { default: ['s0', 's1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9'] },
      variants: {},
    },
    s0: { id: 's0', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s1: { id: 's1', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s2: { id: 's2', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s3: { id: 's3', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s4: { id: 's4', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s5: { id: 's5', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s6: { id: 's6', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s7: { id: 's7', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s8: { id: 's8', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    s9: { id: 's9', type: 'bky/spacer', props: { size: 'xl' }, slots: {}, variants: {} },
    below: {
      id: 'below',
      type: 'bky/heading',
      props: {
        text: 'Animated on scroll',
        level: 2,
        animation: { preset: 'fade-up', trigger: 'scroll', speed: 'fast', delay: 0, repeat: false },
      },
      slots: {},
      variants: {},
    },
    onload: {
      id: 'onload',
      type: 'bky/text',
      props: {
        text: 'Animated on load',
        animation: { preset: 'fade-in', trigger: 'load', speed: 'fast' },
      },
      slots: {},
      variants: {},
    },
  },
};

test.describe('Entrance animations', () => {
  test.beforeAll(async ({ request }) => {
    if (!KEY) throw new Error('BLOCKY_API_KEY is required to seed the animations fixture');
    const res = await request.post(SITE + '/wp-json/blocky/v1/documents/' + FIXTURE_POST_ID, {
      headers: { authorization: 'Bearer ' + KEY },
      data: { document: DOC },
    });
    expect(res.ok()).toBeTruthy();
  });

  test('scroll trigger hides then reveals, load trigger reveals immediately', async ({ page }) => {
    await page.goto(SITE + PATH, { waitUntil: 'load' });
    await page.waitForSelector('[data-bky-anim]'); // document rendered
    await expect(page.locator('html')).toHaveClass(/bky-anim-ready/, { timeout: 15000 });

    const target = page.locator('[data-bky-anim="fade-up"]');
    await expect(target).toHaveAttribute('data-bky-anim-trigger', 'scroll');
    await expect(target).toHaveCSS('opacity', '0');

    await expect(page.locator('[data-bky-anim="fade-in"]')).toHaveClass(/bky-anim-in/);
    await expect(page.locator('[data-bky-anim="fade-in"]')).toHaveCSS('opacity', '1');

    await target.scrollIntoViewIfNeeded();
    await expect(target).toHaveClass(/bky-anim-in/, { timeout: 5000 });
    await expect(target).toHaveCSS('opacity', '1', { timeout: 5000 });
  });

  test('reduced-motion visitors always see content', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(SITE + PATH);
    const target = page.locator('[data-bky-anim="fade-up"]');
    await expect(target).toHaveCSS('opacity', '1');
  });

  test('inspector entrance controls drive the canvas', async ({ page }) => {
    await loginToBuilder(page, { postId: FIXTURE_POST_ID, expectInspector: true });
    const frame = builderPreviewFrame(page);
    await selectCanvasBlock(page, 'bky/heading', frame);
    await inspectorTab(page, 'Animations').click();
    await expect(page.getByText('Entrance Animation', { exact: true })).toBeVisible();

    const preset = page.locator('label').filter({ hasText: 'Preset' }).locator('select');
    await preset.waitFor();
    await preset.selectOption('zoom-in');
    await expect(frame.locator('[data-bky-anim="zoom-in"]').first()).toBeVisible({
      timeout: 10000,
    });
  });
});
