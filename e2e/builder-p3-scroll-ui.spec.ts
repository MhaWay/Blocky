import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P3 scroll UI blocks', () => {
  test('scroll progress, sticky bar, and back-to-top expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/scroll-progress', content: ['Position', 'Height', 'Tone', 'Show Track'] },
      { type: 'bky/sticky-bar', content: ['Title', 'Text', 'Button Label', 'Button URL', 'Position', 'Show After', 'Dismissible', 'Tone'] },
      { type: 'bky/back-to-top', content: ['Label', 'Show Label', 'Position', 'Show After', 'Size', 'Tone'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});