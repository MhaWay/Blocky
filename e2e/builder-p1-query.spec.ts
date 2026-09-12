import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 query blocks', () => {
  test('third P1 batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/posts-list', content: ['Post Type', 'Posts Per Page', 'Show Excerpt'] },
      { type: 'bky/posts-grid', content: ['Post Type', 'Posts Per Page'], layout: ['Columns'] },
      { type: 'bky/archive-posts', content: ['Fallback Posts Per Page', 'Show Excerpt'] },
      { type: 'bky/pagination', content: ['Previous Label', 'Next Label'], layout: ['Alignment'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});