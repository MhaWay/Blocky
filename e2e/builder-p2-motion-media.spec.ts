import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 motion and media blocks', () => {
  test('motion and media blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/animated-headline', content: ['Prefix', 'Words', 'Suffix', 'Effect', 'Interval (seconds)'], layout: ['Alignment'], style: ['Tone'] },
      { type: 'bky/marquee', content: ['Items', 'Speed (seconds)', 'Pause On Hover'], layout: ['Direction'], style: ['Tone'] },
      { type: 'bky/lottie', content: ['Animation URL', 'Poster Image', 'Autoplay', 'Loop', 'Playback Speed'], layout: ['Aspect Ratio'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});