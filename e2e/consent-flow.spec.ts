import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const SITE = process.env.BLOCKY_E2E_SITE ?? 'http://192.168.1.8:8888';
const CONTAINER = process.env.BLOCKY_E2E_CONTAINER ?? 'local-wp-wp-1';

function docker(args: string[]): string {
  return execFileSync('docker', ['exec', CONTAINER, ...args], { encoding: 'utf-8' }).trim();
}

function setConsent(value: string): void {
  docker(['wp', 'option', 'update', 'blocky_allow_engine_download', value, '--allow-root']);
}

function consentValue(): string {
  return docker(['wp', 'option', 'get', 'blocky_allow_engine_download', '--allow-root']);
}

function runRebuildProbe(): { consent: string; rebuild: boolean; status: string } {
  // Suites always run from the repo root (see playwright.config.ts).
  execFileSync('docker', [
    'cp',
    join(process.cwd(), 'e2e/fixtures/rebuild-probe.php'),
    CONTAINER + ':/tmp/e2e-rebuild-probe.php',
  ]);
  return JSON.parse(docker(['php', '/tmp/e2e-rebuild-probe.php']));
}

async function loginAdmin(page: Page): Promise<void> {
  await page.goto(SITE + '/wp-login.php');
  if (page.url().includes('wp-login.php')) {
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin123');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });
  }
}

test.describe('Setup consent flow (human path)', () => {
  test.afterAll(() => {
    setConsent('yes');
  });

  test('without consent: dashboard notice points to Setup and the compiler is blocked', async ({
    page,
  }) => {
    setConsent('');
    await loginAdmin(page);
    await page.goto(SITE + '/wp-admin/index.php');
    await expect(page.getByRole('link', { name: 'Open GG Setup' })).toBeVisible();
    const verdict = runRebuildProbe();
    expect(verdict.rebuild).toBe(false);
    expect(verdict.status).toBe('consent_needed');
  });

  test('human ticks the checkbox and saves: option flips, notice disappears, compiler runs', async ({
    page,
  }) => {
    setConsent('');
    await loginAdmin(page);
    await page.goto(SITE + '/wp-admin/admin.php?page=blocky-setup');
    await expect(page.getByRole('heading', { name: 'GG Setup' })).toBeVisible();
    const box = page.locator('input[name="blocky_allow_engine"]');
    await expect(box).not.toBeChecked();
    await box.check();
    await page.getByRole('button', { name: 'Save settings' }).click();
    await expect(page.getByText('Compiler access allowed')).toBeVisible();
    expect(consentValue()).toBe('yes');
    await page.goto(SITE + '/wp-admin/index.php');
    await expect(page.getByRole('link', { name: 'Open GG Setup' })).toHaveCount(0);
    const verdict = runRebuildProbe();
    expect(verdict.rebuild).toBe(true);
  });
});
