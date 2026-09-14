import { expect, test } from '@playwright/test';

// Gutenberg → Blocky conversion button test. Fixture page (create once):
//   wp post create --post_type=page --post_title='Gutenberg Convert' --post_name=gutenberg-convert --post_status=publish
// Its post_content is the standard heading+paragraph+list blocks fixture; the
// conversion itself is idempotent (re-derived from post_content on every run).
const FIXTURE_POST_ID = Number(process.env.BLOCKY_CONVERT_POST_ID ?? 115);
const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://127.0.0.1:8888';
const PATH = '/gutenberg-convert/';

test.describe('Gutenberg to Blocky conversion', () => {
  test('header button converts the page and the frontend renders the document', async ({
    page,
  }) => {
    await page.goto(SITE + '/wp-login.php', { waitUntil: 'load' });
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('admin123');
    await page.locator('#wp-submit').click();
    await page.waitForURL(/wp-admin/, { timeout: 20000 });

    await page.goto(SITE + '/wp-admin/post.php?post=' + FIXTURE_POST_ID + '&action=edit', {
      waitUntil: 'domcontentloaded',
      timeout: 60000,
    });
    const button = page.locator('.blocky-open-builder');
    await expect(button, 'Edit with Blocky button in the editor header').toBeVisible({
      timeout: 30000,
    });
    await expect(button).toHaveText('Edit with Blocky');

    await Promise.all([page.waitForURL(/page=blocky-builder/, { timeout: 40000 }), button.click()]);

    await page.goto(SITE + PATH, { waitUntil: 'load' });
    await expect(page.getByRole('heading', { name: 'Hello from Gutenberg' })).toBeVisible();
    await expect(page.getByText('A converted paragraph.')).toBeVisible();
    await expect(page.getByText('Alpha')).toBeVisible();
  });
});
