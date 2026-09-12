#!/usr/bin/env node
/**
 * Blocky MCP stdio proxy.
 *
 * Spe newline-delimited JSON-RPC su stdin/stdout (MCP stdio transport) e
 * inoltra tutto al sito WordPress /wp-json/blocky/v1/mcp con bearer key.
 * Nessun contenuto locale: il sito e' l'unica fonte di verita'.
 *
 * Env: BLOCKY_SITE_URL  es. https://example.com (senza slash finale)
 *      BLOCKY_API_KEY   bky_live_...  (scope mcp:access obbligatorio)
 */

const siteUrl = (process.env.BLOCKY_SITE_URL || '').replace(/\/+$/, '');
const apiKey = process.env.BLOCKY_API_KEY || '';

if (!siteUrl || !apiKey) {
  console.error('blocky-mcp: BLOCKY_SITE_URL and BLOCKY_API_KEY are required');
  process.exit(1);
}

const endpoint = siteUrl + '/wp-json/blocky/v1/mcp';

function log(msg) {
  console.error('[blocky-mcp]', msg);
}

async function forward(message) {
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      // Casing matters: Apache only exposes the canonical 'Authorization'
      // header to PHP (HTTP_AUTHORIZATION).
      Authorization: 'Bearer ' + apiKey,
    },
    body: JSON.stringify(message),
  });
  const text = await response.text();
  if (!response.ok && response.status !== 401) {
    throw new Error('HTTP ' + response.status + ': ' + text.slice(0, 300));
  }
  try {
    return JSON.parse(text);
  } catch {
    // Non-JSON (login wall, proxy HTML): segnala come errore RPC.
    return {
      jsonrpc: '2.0',
      id: message.id ?? null,
      error: { code: -32000, message: 'Unexpected non-JSON response: ' + text.slice(0, 200) },
    };
  }
}

let buffer = '';

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
  buffer += chunk;
  let newline = buffer.indexOf('\n');
  while (newline !== -1) {
    const line = buffer.slice(0, newline).trim();
    buffer = buffer.slice(newline + 1);
    if (line) handle(line);
    newline = buffer.indexOf('\n');
  }
});

function emit(message) {
  process.stdout.write(JSON.stringify(message) + '\n');
}

async function handle(line) {
  let message;
  try {
    message = JSON.parse(line);
  } catch {
    emit({ jsonrpc: '2.0', id: null, error: { code: -32700, message: 'Parse error' } });
    return;
  }

  // Notifiche (no id): inoltra ma non attende output di risposta.
  if (message.method === 'notifications/initialized') {
    return;
  }

  try {
    const result = await forward(message);
    emit(result);
  } catch (error) {
    log(String(error && error.message));
    emit({
      jsonrpc: '2.0',
      id: message.id ?? null,
      error: { code: -32000, message: String(error && error.message) },
    });
  }
}
