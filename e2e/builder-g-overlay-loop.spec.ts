import { expect, test } from '@playwright/test';

import { builderPreviewFrame, ensureCanvasBlock, inspectorTab, loginToBuilder, selectCanvasBlock } from './helpers/builder';

const OVERLAY_BLOCKS: Array<{ type: string; label: string }> = [
  { type: 'bky/modal', label: 'Modal' },
  { type: 'bky/offcanvas', label: 'Offcanvas' },
  { type: 'bky/drawer', label: 'Drawer' },
  { type: 'bky/popover', label: 'Popover' },
  { type: 'bky/tooltip', label: 'Tooltip' },
  { type: 'bky/dialog-confirm', label: 'Dialog Confirm' },
  { type: 'bky/popup', label: 'Popup' },
  { type: 'bky/notification-toast', label: 'Notification Toast' },
  { type: 'bky/cookie-banner', label: 'Cookie Banner' },
  { type: 'bky/lightbox', label: 'Lightbox' },
  { type: 'bky/command-palette', label: 'Command Palette' },
];

test.describe('Builder G overlay loop', () => {
  test('overlay panel previews every overlay block and resolves trigger warnings with guided targets', async ({ page }) => {
    await loginToBuilder(page, { expectInspector: true });

    const frame = builderPreviewFrame(page);
    for (const block of [...OVERLAY_BLOCKS, { type: 'bky/modal-trigger', label: 'Modal Trigger' }]) {
      await ensureCanvasBlock(page, block.type, frame);
    }

    const overlayPanel = page.locator('section').filter({ hasText: 'Select, preview, and validate trigger coverage for overlay blocks.' }).first();
    await expect(overlayPanel).toBeVisible();
    await expect(overlayPanel.getByText(String(OVERLAY_BLOCKS.length), { exact: true })).toBeVisible();

    const modalRow = overlayPanel.locator('div').filter({ hasText: 'Modal' }).first();
    await expect(modalRow.getByText('No manual trigger or automatic open rule detected for this overlay.')).toBeVisible();

    for (const block of OVERLAY_BLOCKS) {
      await selectCanvasBlock(page, block.type, frame);

      const row = overlayPanel.locator('div').filter({ hasText: block.label }).first();
      await expect(row).toBeVisible();
      await row.getByRole('button', { name: 'Preview' }).click();
      await expect(row.getByRole('button', { name: 'Previewing' })).toBeVisible();

      const overlayRoot = frame.locator(`[data-bky-type="${block.type}"][data-bky-editor-overlay-preview="true"]`).first();
      await expect(overlayRoot).toBeVisible();

      await row.getByRole('button', { name: 'Previewing' }).click();
      await expect(frame.locator(`[data-bky-type="${block.type}"][data-bky-editor-overlay-preview="true"]`)).toHaveCount(0);
    }

    await selectCanvasBlock(page, 'bky/modal-trigger', frame);
    await inspectorTab(page, 'Content').click();
    const triggerTargetSelect = page.locator('label').filter({ hasText: 'Target Overlay ID' }).locator('select');
    await expect(triggerTargetSelect).toBeVisible();

    const modalOption = await triggerTargetSelect.locator('option').evaluateAll(options => {
      const values = options.map(option => (option as HTMLOptionElement).value);
      return values.find(value => value.startsWith('modal-')) ?? '';
    });
    expect(modalOption).not.toBe('');
    await triggerTargetSelect.selectOption(modalOption);

    await expect(modalRow.getByText('No manual trigger or automatic open rule detected for this overlay.')).toHaveCount(0);

    await ensureCanvasBlock(page, 'bky/button', frame);
    await selectCanvasBlock(page, 'bky/button', frame);
    await inspectorTab(page, 'Advanced').click();
    await page.getByRole('button', { name: 'Add Interaction' }).click();
    await expect(page.locator('label').filter({ hasText: 'Target' }).locator('select')).toBeVisible();
  });
});