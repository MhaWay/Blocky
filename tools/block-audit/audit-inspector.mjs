import { chromium } from '@playwright/test';
import fs from 'node:fs';

const SITE = 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY ?? '';
const OUT = '/tmp/audit-shots';
fs.mkdirSync(OUT, { recursive: true });

const catalog = await (await fetch(SITE + '/wp-json/blocky/v1/blocks', {
  headers: { authorization: 'Bearer ' + KEY },
})).json();
const types = (Array.isArray(catalog) ? catalog : catalog.blocks);
console.log('types:', types.length);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
let currentErrors = [];
page.on('console', m => { if (m.type() === 'error') currentErrors.push(m.text().slice(0, 160)); });
page.on('pageerror', e => currentErrors.push('PAGEERROR ' + String(e).slice(0, 160)));

await page.goto(SITE + '/wp-admin/admin.php?page=blocky-builder&post_id=12', { waitUntil: 'networkidle' });
if (page.url().includes('wp-login.php')) {
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('admin123');
  await page.locator('#wp-submit').click();
  await page.waitForURL(/blocky-builder/);
  await page.waitForLoadState('networkidle');
}
await page.waitForSelector('.blocky-builder-root');
await page.waitForTimeout(1500);

const results = [];
for (const { type } of types) {
  currentErrors = [];
  const nodeId = await page.evaluate((t) => {
    const d = window.BlockyBuilderDebug;
    const before = new Set(Object.keys(d.store.getState().document?.nodes ?? {}));
    d.store.getState().insertBlock(t);
    const after = Object.keys(d.store.getState().document?.nodes ?? {})
      .filter(k => !before.has(k));
    const id = after[0] ?? null;
    if (id) d.ui.getState().selectNode(id);
    return id;
  }, type);
  await page.waitForTimeout(600);
  const fallback = await page.getByText('Select a block on the canvas').isVisible().catch(() => false);
  const slug = type.replace(/[^a-z0-9-]/gi, '_');
  await page.screenshot({ path: OUT + '/insp-' + slug + '.png' });
  results.push({ type, inspector: nodeId && !fallback ? 'ok' : (nodeId ? 'FALLBACK_CONTROLS' : 'NO_INSERT'), errors: currentErrors.length });
  await page.evaluate((id) => {
    const d = window.BlockyBuilderDebug;
    if (id) d.store.getState().removeBlock(id);
    d.ui.getState().selectNode(null);
  }, nodeId);
  await page.waitForTimeout(150);
}
await browser.close();
fs.writeFileSync(OUT + '/inspector-results.json', JSON.stringify(results, null, 2));
const bad = results.filter(r => r.inspector !== 'ok' || r.errors > 0);
console.log('INSPECTOR AUDIT ok=' + (results.length - bad.length) + '/' + results.length);
for (const r of bad) console.log('BAD', JSON.stringify(r));
