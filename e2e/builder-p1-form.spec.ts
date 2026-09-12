import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 form blocks', () => {
  test('form batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/form', content: ['Action URL', 'Method', 'Form Name', 'Encoding'], layout: ['Spacing', 'Alignment'], style: ['Background', 'Border', 'Radius', 'Shadow'] },
      { type: 'bky/form-field-text', content: ['Label', 'Name', 'Placeholder', 'Input Type', 'Help Text'], layout: ['Width'] },
      { type: 'bky/form-field-textarea', content: ['Label', 'Name', 'Placeholder', 'Rows'], layout: ['Width'] },
      { type: 'bky/form-field-select', content: ['Label', 'Name', 'Options', 'Placeholder'], layout: ['Width'] },
      { type: 'bky/form-field-radio', content: ['Label', 'Name', 'Options', 'Default Value'], layout: ['Choice Layout', 'Width'] },
      { type: 'bky/form-field-checkbox', content: ['Label', 'Name', 'Options', 'Default Values'], layout: ['Choice Layout', 'Width'] },
      { type: 'bky/form-field-date', content: ['Label', 'Name', 'Input Type', 'Minimum', 'Maximum'], layout: ['Width'] },
      { type: 'bky/form-field-file', content: ['Label', 'Name', 'Accepted Types', 'Allow Multiple'], layout: ['Width'] },
      { type: 'bky/form-field-hidden', content: ['Name', 'Value'] },
      { type: 'bky/form-field-honeypot', content: ['Name', 'Label'] },
      { type: 'bky/form-submit', content: ['Label', 'Full Width'], layout: ['Alignment', 'Size'], style: ['Style', 'Radius', 'Shadow'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});