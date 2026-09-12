import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P3 sitemap and table of contents blocks', () => {
  test('sitemap and table of contents insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/sitemap', content: ['Title', 'Include Pages', 'Include Posts', 'Posts Per Page', 'Show Descriptions', 'Order By'] },
      { type: 'bky/table-of-contents', content: ['Title', 'Minimum Heading Level', 'Maximum Heading Level', 'Ordered List'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});