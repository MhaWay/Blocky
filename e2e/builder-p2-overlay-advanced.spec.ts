import { test } from '@playwright/test';

import { assertBuilderInspectorChecks, builderPreviewFrame, type BuilderInspectorCheck, loginToBuilder } from './helpers/builder';

test.describe('Builder P2 advanced overlay blocks', () => {
  test('advanced overlay blocks insert and expose key controls', async ({ page }) => {
    await loginToBuilder(page);

    const frame = builderPreviewFrame(page);
    const checks: BuilderInspectorCheck[] = [
      { type: 'bky/popup', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On', 'Open by Default', 'ESC to Close', 'Outside Click Closes', 'Trap Focus'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/notification-toast', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On', 'Open by Default', 'ESC to Close', 'Outside Click Closes', 'Trap Focus'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/cookie-banner', content: ['Overlay ID', 'Title', 'Description', 'Cookie Name', 'Accept Label', 'Reject Label', 'Cookie Duration Days', 'Open On', 'Frequency', 'Close On'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
      { type: 'bky/lightbox', content: ['Overlay ID', 'Trigger Label', 'Media URL', 'Media Type', 'Caption', 'Thumbnail URL'], layout: ['Aspect Ratio'] },
      { type: 'bky/command-palette', content: ['Overlay ID', 'Title', 'Description', 'Open On', 'Frequency', 'Close On', 'Open by Default', 'ESC to Close', 'Outside Click Closes', 'Trap Focus'], layout: ['Placement', 'Size'], style: ['Backdrop'] },
    ];

    await assertBuilderInspectorChecks(page, checks, frame);
  });
});