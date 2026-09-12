import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 gallery and interactive blocks', () => {
  test('fourth P1 batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/image-gallery', content: ['Items'], layout: ['Columns', 'Spacing'], style: ['Aspect Ratio'] },
      { type: 'bky/basic-gallery', content: ['Items'], layout: ['Columns', 'Spacing'] },
      { type: 'bky/tabs', content: ['Items', 'Active Index'] },
      { type: 'bky/accordion', content: ['Items', 'Open First Item'] },
      { type: 'bky/toggle', content: ['Title', 'Content', 'Open by Default'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});