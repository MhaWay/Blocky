import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 interactive marketing blocks', () => {
  test('interactive blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/flip-box', content: ['Front Title', 'Front Text', 'Back Title', 'Back Text', 'Button Label', 'Button URL'], layout: ['Height'], style: ['Tone'] },
      { type: 'bky/hotspot', content: ['Image', 'Points'], style: ['Tone'] },
      { type: 'bky/before-after-slider', content: ['Before Image', 'After Image', 'Starting Position'], layout: ['Aspect Ratio'] },
      { type: 'bky/countdown', content: ['Target Date', 'Show Labels'], layout: ['Layout'], style: ['Tone'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});