import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 basic blocks', () => {
  test('first P1 batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/icon', content: ['Icon', 'Link'], style: ['Size'] },
      { type: 'bky/icon-box', content: ['Title', 'Text'], style: ['Icon Size'] },
      { type: 'bky/icon-list', content: ['Items'], layout: ['Spacing'] },
      { type: 'bky/image-box', content: ['Image'], layout: ['Image Width'], style: ['Aspect Ratio'] },
      { type: 'bky/alert', content: ['Message'], style: ['Tone'] },
      { type: 'bky/progress-bar', content: ['Value', 'Striped'] },
      { type: 'bky/counter', content: ['Value', 'Caption'] },
      { type: 'bky/star-rating', content: ['Max Stars'], style: ['Tone'] },
      { type: 'bky/anchor', content: ['Anchor ID', 'Editor Label'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});