import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P3 featured posts and taxonomy list blocks', () => {
  test('featured posts and taxonomy list expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/featured-posts', content: ['Title', 'Posts Per Page', 'Layout', 'Show Excerpt', 'Sticky Posts Only'] },
      { type: 'bky/taxonomy-list', content: ['Title', 'Taxonomy', 'Layout', 'Show Count', 'Limit'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});