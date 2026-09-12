import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P3 divider and spacer enhancements', () => {
  test('divider and spacer expose fancy and responsive controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/divider', content: ['Style', 'Ornament', 'Tone', 'Width'] },
      { type: 'bky/spacer', content: ['Mobile Size', 'Tablet Size', 'Desktop Size'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});