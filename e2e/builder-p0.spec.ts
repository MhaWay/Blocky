import { test, expect } from '@playwright/test';

import { builderPreviewFrame, ensureCanvasBlock, inspectorTab, loginToBuilder, selectCanvasBlock } from './helpers/builder';

test.describe('Builder P0 verification', () => {
  test('builder P0 foundations are interactively available', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', error => pageErrors.push(error.message));

    await loginToBuilder(page, { expectInspector: true });

    const frame = builderPreviewFrame(page);
    await selectCanvasBlock(page, 'bky/section', frame);

    for (const tab of ['Content', 'Layout', 'Style', 'Animations', 'Advanced', 'Classes']) {
      await expect(inspectorTab(page, tab)).toBeVisible();
    }

    await inspectorTab(page, 'Style').click();
    await expect(page.getByText('Breakpoint scope')).toBeVisible();

    const backgroundOptions = page
      .locator('label')
      .filter({ hasText: 'Background' })
      .locator('select option');
    await expect(backgroundOptions).toHaveCount(4);
    await expect(backgroundOptions).toHaveText(['Transparent', 'Custom', 'Gradient', 'Image']);

    await inspectorTab(page, 'Classes').click();
    await expect(page.getByText('Variant prefix')).toBeVisible();
    for (const stateName of ['Base', 'Hover', 'Focus', 'Active', 'Disabled']) {
      await expect(page.locator(`button[aria-label="${stateName}"]`)).toBeVisible();
    }

    await inspectorTab(page, 'Advanced').click();
    await expect(page.getByText('Interactions')).toBeVisible();
    await page.getByRole('button', { name: 'Add Interaction' }).click();
    await expect(page.getByText('Conditions', { exact: true })).toBeVisible();
    await expect(page.getByText('Modifiers', { exact: true })).toBeVisible();
    await expect(page.getByText('Device', { exact: true })).toBeVisible();
    await expect(page.getByText('Login State', { exact: true })).toBeVisible();
    await expect(page.getByText('Debounce (ms)', { exact: true })).toBeVisible();
    await expect(page.getByText('Throttle (ms)', { exact: true })).toBeVisible();
    await expect(page.getByText('Prevent Default', { exact: true })).toBeVisible();
    await expect(page.getByText('Stop Propagation', { exact: true })).toBeVisible();

    await ensureCanvasBlock(page, 'bky/button', frame);
    await selectCanvasBlock(page, 'bky/button', frame);
    await inspectorTab(page, 'Content').click();
    await expect(page.getByText('Open in new tab')).toBeVisible();
    await expect(page.locator('label').filter({ hasText: 'Open in new tab' }).locator('input[type="checkbox"]')).toBeVisible();

    await ensureCanvasBlock(page, 'bky/image', frame);
    await selectCanvasBlock(page, 'bky/image', frame);
    await inspectorTab(page, 'Content').click();
    for (const label of ['Alt Text', 'Image Size', 'Object Fit', 'Loading', 'Decoding', 'Focal Point']) {
      await expect(page.getByText(label)).toBeVisible();
    }

    await selectCanvasBlock(page, 'bky/section', frame);
    await inspectorTab(page, 'Style').click();
    const backgroundSelect = page.locator('label').filter({ hasText: 'Background' }).locator('select');
    await backgroundSelect.selectOption('Image');
    await expect(page.getByText('Focal Point')).toBeVisible();

    expect(pageErrors).toHaveLength(0);
  });

  test('frontend runtime exposes overlay and action APIs', async ({ page }) => {
    await loginToBuilder(page, { expectInspector: true });

    const frontendUrl = await page.getByRole('link', { name: 'View' }).getAttribute('href');
    expect(frontendUrl).toBeTruthy();

    await page.goto(frontendUrl!);

    const runtimeShape = await page.evaluate(() => ({
      hasRuntime: typeof (window as Window & { Blocky?: unknown }).Blocky === 'object',
      hasOverlays: typeof (window as Window & { Blocky?: { overlays?: { open?: unknown } } }).Blocky?.overlays?.open === 'function',
      hasActions: typeof (window as Window & { Blocky?: { actions?: { run?: unknown } } }).Blocky?.actions?.run === 'function',
    }));

    expect(runtimeShape).toEqual({
      hasRuntime: true,
      hasOverlays: true,
      hasActions: true,
    });
  });
});