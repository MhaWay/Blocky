import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 dynamic post blocks', () => {
  test('dynamic post blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/author-box', content: ['Title', 'Show Avatar', 'Show Bio', 'Show Archive Link'], layout: ['Avatar Size', 'Layout'], style: ['Padding', 'Background', 'Border', 'Radius'] },
      { type: 'bky/comments', content: ['Title', 'Comments Per Page', 'Show Avatar', 'Show Date', 'Order'] },
      { type: 'bky/comment-form', content: ['Title Reply', 'Button Label', 'Show Notes'] },
      { type: 'bky/post-navigation', content: ['Previous Label', 'Next Label', 'Show Labels'], layout: ['Layout'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});