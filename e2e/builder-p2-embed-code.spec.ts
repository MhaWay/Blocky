import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 embed and code blocks', () => {
  test('embed and code blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/embed-google-maps', content: ['Provider', 'Location Query', 'Latitude', 'Longitude', 'Zoom', 'Title', 'Allow Fullscreen'], layout: ['Height', 'Aspect Ratio'] },
      { type: 'bky/embed-iframe', content: ['URL', 'Title', 'Sandbox', 'Allow', 'Lazy Load', 'Allow Fullscreen'], layout: ['Height', 'Aspect Ratio'] },
      { type: 'bky/code-highlight', content: ['Code', 'Language', 'Caption', 'Show Line Numbers'], style: ['Tone'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});