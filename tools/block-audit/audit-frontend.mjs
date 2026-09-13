import { chromium } from '@playwright/test';
import fs from 'node:fs';

const SITE = 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY ?? '';
const H = { authorization: 'Bearer ' + KEY, 'content-type': 'application/json' };
const OUT = '/tmp/audit-shots';
fs.mkdirSync(OUT, { recursive: true });
const SVG = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="#7c8ea6"/><text x="300" y="210" font-size="40" text-anchor="middle" fill="white">Sample</text></svg>'
);

function itemFrom(schema) {
  const item = {};
  const props = schema.properties ?? {};
  const keys = schema.required && schema.required.length ? schema.required : Object.keys(props).slice(0, 6);
  for (const k of keys) item[k] = sampleFor(k, props[k]);
  return item;
}

function sampleFor(name, schema) {
  const s = schema ?? {};
  if (Array.isArray(s.enum)) return s.enum[0];
  if (s.type === 'boolean') return false;
  if (s.type === 'integer' || s.type === 'number') return s.minimum ?? 1;
  if (s.type === 'array') {
    if (s.items && s.items.type === 'object') return [itemFrom(s.items)];
    if (s.items && Array.isArray(s.items.enum)) return [s.items.enum[0]];
    return [SVG];
  }
  if (s.type === 'object') return itemFrom(s);
  const n = name.toLowerCase();
  if (/(url|href|link)/.test(n)) return 'https://example.com';
  if (/(src|image|img|media|video|poster)/.test(n)) return SVG;
  if (/(title|label|name|heading)/.test(n)) return 'Titolo di esempio';
  return 'Esempio Blocky per il controllo ' + name + '.';
}

function genProps(schema) {
  const props = {};
  const req = (schema && schema.required) ?? [];
  for (const name of req) props[name] = sampleFor(name, schema && schema.properties ? schema.properties[name] : undefined);
  return props;
}

function buildDoc(type, props) {
  return {
    root: 'root',
    nodes: {
      root: { id: 'root', type: 'bky/section', props: {}, slots: { default: ['n1'] }, variants: {} },
      n1: { id: 'n1', type: type, props: props, slots: {}, variants: {} },
    },
  };
}

const catalog = await (await fetch(SITE + '/wp-json/blocky/v1/blocks', { headers: H })).json();
const types = Array.isArray(catalog) ? catalog : catalog.blocks;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
let errors = [];
page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 140)); });
page.on('pageerror', e => errors.push('PAGEERROR ' + String(e).slice(0, 140)));

const results = [];
for (const b of types) {
  errors = [];
  let res;
  for (let attempt = 0; attempt < 4; attempt += 1) {
    await new Promise(r => setTimeout(r, 1050));
    res = await fetch(SITE + '/wp-json/blocky/v1/documents/12', { method: 'POST', headers: H, body: JSON.stringify({ document: buildDoc(b.type, genProps(b.schema)) }) });
    if (res.status === 429) { await new Promise(r => setTimeout(r, 62000)); continue; }
    break;
  }
  const okSave = res.ok;
  await page.goto(SITE + '/audit-grid/', { waitUntil: 'networkidle' });
  const content2 = page.locator('.entry-content').first();
  const slug = b.type.replace(/[^a-z0-9-]/gi, '_');
  await content2.screenshot({ path: OUT + '/front-' + slug + '.png' }).catch(() => {});
  results.push({ type: b.type, save: okSave, errors: errors.length, err: errors.slice(0, 2).join(' ;; ') });
}
await browser.close();
fs.writeFileSync(OUT + '/frontend-results.json', JSON.stringify(results, null, 2));
const bad = results.filter(r => !r.save || r.errors > 0);
console.log('FRONT ok=' + (results.length - bad.length) + '/' + results.length);
for (const r of bad) console.log('BAD', JSON.stringify(r));
