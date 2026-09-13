import { test, expect } from '@playwright/test';
import { loginToBuilder } from './helpers/builder';

/**
 * Sidebar 'Pages' tab: WP-admin-style page overview with create/open/trash.
 * Read-only assertions only - this spec must never mutate shared fixtures.
 */
test.describe('Builder F9 sidebar pages tab', () => {
  test.beforeEach(async ({ page }) => {
    await loginToBuilder(page);
  });

  test('lists every WP page with badges and supports search', async ({ page }) => {
    const sidebar = page.locator('aside').first();
    await sidebar.getByRole('button', { name: 'Pages', exact: true }).click();

    await expect(sidebar.getByRole('button', { name: 'Add Page' })).toBeVisible();

    const rows = sidebar.locator('ul li');
    await expect(rows.filter({ hasText: 'Audit Grid' })).toBeVisible();

    // Built-with-Blocky badge shows on a page that has a document.
    await expect(
      rows.filter({ hasText: 'Audit Grid' }).locator('[title="Built with Blocky"]')
    ).toBeVisible();

    await sidebar.getByPlaceholder(/search pages/i).fill('MCP');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('MCP Target');
  });

  test('templates tab offers quick creation for every kind', async ({ page }) => {
    const sidebar = page.locator('aside').first();
    await sidebar.getByRole('button', { name: 'Templates', exact: true }).click();

    await expect(
      sidebar.getByText('Reusable parts and layouts. Click one to edit it.')
    ).toBeVisible();

    for (const kind of ['Base', 'Header', 'Footer', 'Menu', 'Sidebar', 'Article']) {
      await expect(sidebar.getByRole('button', { name: '+ ' + kind })).toBeVisible();
    }
    await expect(sidebar.getByPlaceholder(/search templates/i)).toBeVisible();
  });

  test('components and the data-field block are discoverable', async ({ page }) => {
    const sidebar = page.locator('aside').first();

    await sidebar.getByRole('button', { name: 'Templates', exact: true }).click();
    await expect(sidebar.getByRole('button', { name: '+ Component' })).toBeVisible();

    await sidebar.getByRole('button', { name: 'Blocks', exact: true }).click();
    await sidebar.getByPlaceholder(/search blocks/i).fill('data field');
    await expect(
      sidebar
        .locator('button')
        .filter({ hasText: /Data Field/ })
        .first()
    ).toBeVisible();
  });
});
