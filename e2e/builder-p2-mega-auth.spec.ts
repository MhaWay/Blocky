import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 auth blocks', () => {
  test('auth forms insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/login-form', content: ['Form ID', 'Redirect URL', 'Title', 'Button Label', 'Show Remember Me', 'Show Lost Password', 'Show Register Link'], style: ['Spacing', 'Padding', 'Background', 'Border', 'Radius'] },
      { type: 'bky/register-form', content: ['Form ID', 'Redirect URL', 'Title', 'Submit Label', 'Success Message', 'Login After Register'], style: ['Spacing', 'Padding', 'Background', 'Border', 'Radius'] },
      { type: 'bky/contact-form', content: ['Form ID', 'Title', 'Success Message', 'Name Label', 'Email Label', 'Subject Label', 'Message Label', 'Submit Label'], style: ['Spacing', 'Padding', 'Background', 'Border', 'Radius'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});