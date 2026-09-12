import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 marketing and pricing blocks', () => {
  test('marketing and pricing blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/call-to-action', content: ['Image', 'Eyebrow', 'Title', 'Text', 'Primary Label', 'Primary URL', 'Secondary Label', 'Secondary URL'], layout: ['Layout', 'Alignment', 'Media Position'], style: ['Tone', 'Padding'] },
      { type: 'bky/testimonial', content: ['Image', 'Quote', 'Author', 'Role'], layout: ['Layout'], style: ['Tone'] },
      { type: 'bky/price-table', content: ['Title', 'Subtitle', 'Price', 'Currency', 'Cadence', 'Features', 'Button Label', 'Button URL', 'Featured Plan'], layout: ['Alignment'], style: ['Tone'] },
      { type: 'bky/price-list', content: ['Items', 'Show Dividers'], style: ['Tone'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});