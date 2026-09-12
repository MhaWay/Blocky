import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 site blocks', () => {
  test('second P1 batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/social-icons', content: ['Items'], layout: ['Spacing'], style: ['Tone'] },
      { type: 'bky/share-buttons', content: ['Networks', 'Share Title'], style: ['Radius'] },
      { type: 'bky/search-form', content: ['Placeholder', 'Button Label'], style: ['Button Tone'] },
      { type: 'bky/nav-menu', content: ['Menu Location', 'Fallback Items'], layout: ['Alignment'] },
      { type: 'bky/breadcrumbs', content: ['Separator', 'Show Current Page'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});