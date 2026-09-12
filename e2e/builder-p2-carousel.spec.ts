import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 carousel blocks', () => {
  test('carousel blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/image-carousel', content: ['Items', 'Show Captions', 'Auto Play', 'Interval (seconds)'], layout: ['Slides Visible', 'Spacing'], style: ['Aspect Ratio'] },
      { type: 'bky/content-carousel', content: ['Items', 'Auto Play', 'Interval (seconds)'], layout: ['Slides Visible', 'Spacing', 'Alignment'], style: ['Tone'] },
      { type: 'bky/slider', content: ['Items', 'Auto Play', 'Interval (seconds)'], layout: ['Slides Visible', 'Spacing', 'Alignment'], style: ['Tone'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});