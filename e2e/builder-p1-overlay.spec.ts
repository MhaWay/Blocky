import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P1 overlay blocks', () => {
  test('overlay batch inserts and exposes key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/modal', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/offcanvas', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/drawer', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/modal-trigger', content: ['Label', 'Target Overlay ID', 'Action', 'Full Width'], layout: ['Alignment', 'Size'], style: ['Style', 'Radius', 'Shadow'] },
      { type: 'bky/popover', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/tooltip', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/dialog-confirm', content: ['Overlay ID', 'Title', 'Description', 'Cancel Label', 'Confirm Label', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});