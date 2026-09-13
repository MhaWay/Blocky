const SITE = 'http://127.0.0.1:8888';
const KEY = process.env.BLOCKY_API_KEY;
const POST = 12;
const H = { authorization: `Bearer ${KEY}`, 'content-type': 'application/json' };

function genProps(schema) {
  const props = {};
  for (const name of schema.required ?? []) {
    const p = schema.properties?.[name];
    if (!p) continue;
    if (Array.isArray(p.enum)) props[name] = p.enum[0];
    else if (p.type === 'boolean') props[name] = false;
    else if (p.type === 'integer') props[name] = 1;
    else if (p.type === 'number') props[name] = 1;
    else if (p.type === 'array') props[name] = [];
    else props[name] = 'Esempio Blocky';
  }
  return props;
}
function buildDoc(type, props) {
  return {
    root: 'root',
    nodes: {
      root: { id: 'root', type: 'bky/section', props: {}, slots: { default: ['n1'] }, variants: {} },
      n1: { id: 'n1', type, props, slots: {}, variants: {} },
    },
  };
}
function norm(html) {
  return html
    .replace(/ data-bky-[a-z-]+="[^"]*"/g, '')
    .replace(/ draggable="true"/g, '')
    .replace(/ ?bky-edit-[a-z-]+/g, '')
    .replace(/\s+/g, ' ')
    .replace(/>\s+</g, '><')
    .trim();
}

const catalog = await (await fetch(SITE + '/wp-json/blocky/v1/blocks', { headers: H })).json();
const types = (Array.isArray(catalog) ? catalog : catalog.blocks);
const results = [];
for (const b of types) {
  const type = b.type;
  let res;
  for (let attempt = 0; attempt < 4; attempt += 1) {
    await new Promise((r) => setTimeout(r, 1050));
    res = await fetch(SITE + '/wp-json/blocky/v1/documents/' + POST, { method: 'POST', headers: H, body: JSON.stringify({ document: buildDoc(type, genProps(b.schema ?? {})) }) });
    if (res.status === 429) { await new Promise((r) => setTimeout(r, 62000)); continue; }
    break;
  }
  const body = await res.json().catch(() => ({}));
  let state = 'NOT_SAVED';
  let note = '';
  if (res.ok && typeof body.html === 'string') {
    const page = await fetch(SITE + '/audit-grid/', { headers: { 'cache-control': 'no-cache' } });
    const html = await page.text();
    if (/Fatal error|Uncaught/i.test(html)) state = 'PHP_FATAL';
    else {
      const save = norm(body.html);
      const pageN = norm(html);
      const probe = save.slice(0, 120);
      const probeEnd = save.slice(-80);
      if (pageN.includes(probe) && pageN.includes(probeEnd)) state = 'ok';
      else {
        state = 'MISMATCH';
        note = probe.slice(0, 60);
      }
    }
  }
  results.push({ type, state, note });
}
const bad = results.filter((r) => r.state !== 'ok');
console.log('RENDER ok=' + (results.length - bad.length) + '/' + results.length);
for (const r of bad) console.log('BAD', r.state, r.type, r.note);

