/**
 * Blocky Token Build Script
 * Reads DTCG JSON sources → produces:
 *   build/tokens.css          (CSS variables for light mode)
 *   build/tokens.dark.css     (CSS variable overrides for dark mode)
 *   build/tokens.tailwind.css (@theme block for Tailwind 4)
 *   build/tokens.php          (PHP array for server-side resolver)
 *   build/tokens.ts           (TypeScript constants + types)
 *   build/theme.json          (WordPress theme.json compatible)
 */

import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const srcDir = join(root, 'src');
const buildDir = join(root, 'build');

// ── Helpers ───────────────────────────────────────────────────────────────────

type TokenValue = { $value: string; $type: string; $description?: string };
type TokenNode = TokenValue | Record<string, TokenNode>;

function isTokenValue(v: unknown): v is TokenValue {
  return typeof v === 'object' && v !== null && '$value' in v;
}

function flatten(
  obj: Record<string, TokenNode>,
  prefix = ''
): Record<string, TokenValue> {
  const result: Record<string, TokenValue> = {};
  for (const [k, v] of Object.entries(obj)) {
    if (k.startsWith('$')) continue;
    const key = prefix ? `${prefix}-${k}` : k;
    if (isTokenValue(v)) {
      result[key] = v;
    } else {
      Object.assign(result, flatten(v as Record<string, TokenNode>, key));
    }
  }
  return result;
}

async function loadJson(path: string): Promise<Record<string, unknown>> {
  try {
    const text = await readFile(path, 'utf-8');
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    return {};
  }
}

function resolveReference(value: string, allTokens: Record<string, TokenValue>): string {
  const refMatch = /^\{(.+)\}$/.exec(value);
  if (!refMatch?.[1]) return value;
  const refKey = refMatch[1].replace(/\./g, '-');
  const resolved = allTokens[refKey];
  if (!resolved) {
    console.warn(`⚠ Unresolved token reference: ${value}`);
    return value;
  }
  return resolveReference(resolved.$value, allTokens);
}

function tokenToCssVar(key: string): string {
  return `--bky-${key}`;
}

function tokenToTailwindKey(key: string): string {
  // color-surface-base → surface-base  (drop "color-" prefix)
  // layout-gutter → gutter
  return key.replace(/^color-/, '').replace(/^layout-/, '').replace(/^radius-/, 'radius-').replace(/^font-/, 'font-');
}

// ── Load all source tokens ─────────────────────────────────────────────────────

async function loadAll(): Promise<{
  base: Record<string, TokenValue>;
  dark: Record<string, TokenValue>;
}> {
  const coreFiles = ['color', 'spacing', 'typography', 'radius'];
  const semanticFiles = ['color', 'spacing', 'typography'];

  let merged: Record<string, TokenNode> = {};

  // Core
  for (const f of coreFiles) {
    const data = await loadJson(join(srcDir, 'core', `${f}.json`));
    const { $schema: _s, ...tokens } = data;
    Object.assign(merged, tokens);
  }

  // Semantic (overrides + additions)
  for (const f of semanticFiles) {
    const data = await loadJson(join(srcDir, 'semantic', `${f}.json`));
    const { $schema: _s, ...tokens } = data;
    deepMerge(merged, tokens as Record<string, TokenNode>);
  }

  const base = flatten(merged as Record<string, TokenNode>);

  // Resolve references in base
  for (const [k, v] of Object.entries(base)) {
    base[k] = { ...v, $value: resolveReference(v.$value, base) };
  }

  // Dark overrides
  const darkData = await loadJson(join(srcDir, 'brands', 'default', 'dark.json'));
  const { $description: _d, ...darkTokens } = darkData;
  const darkFlat = flatten(darkTokens as Record<string, TokenNode>);

  // Resolve dark references (against base first, then itself)
  const darkMerged = { ...base, ...darkFlat };
  for (const [k, v] of Object.entries(darkFlat)) {
    darkFlat[k] = { ...v, $value: resolveReference(v.$value, darkMerged) };
  }

  return { base, dark: darkFlat };
}

function deepMerge(
  target: Record<string, TokenNode>,
  source: Record<string, TokenNode>
): void {
  for (const [k, v] of Object.entries(source)) {
    if (typeof v === 'object' && !isTokenValue(v) && typeof target[k] === 'object' && !isTokenValue(target[k])) {
      deepMerge(target[k] as Record<string, TokenNode>, v as Record<string, TokenNode>);
    } else {
      target[k] = v;
    }
  }
}

// ── Generators ────────────────────────────────────────────────────────────────

function generateCss(
  tokens: Record<string, TokenValue>,
  selector = ':root',
  indent = '  '
): string {
  const lines: string[] = [`${selector} {`];
  for (const [k, v] of Object.entries(tokens)) {
    lines.push(`${indent}${tokenToCssVar(k)}: ${v.$value};`);
  }
  lines.push('}');
  return lines.join('\n');
}

function generateTailwindTheme(base: Record<string, TokenValue>): string {
  const lines: string[] = [
    '/* Auto-generated — do not edit */',
    '/* Tailwind CSS 4 @theme block — maps token CSS vars to Tailwind utilities */',
    '@theme {',
    '  /* Reset Tailwind defaults to force token usage */',
    '  --color-*: initial;',
    '',
  ];

  const colorTokens = Object.entries(base).filter(([k]) => k.startsWith('color-'));
  const radiusTokens = Object.entries(base).filter(([k]) => k.startsWith('radius-'));
  const layoutTokens = Object.entries(base).filter(([k]) => k.startsWith('layout-'));
  const fontTokens = Object.entries(base).filter(([k]) => k.startsWith('font-'));
  const textTokens = Object.entries(base).filter(([k]) => k.startsWith('text-'));

  if (colorTokens.length) {
    lines.push('  /* Colours */');
    for (const [k] of colorTokens) {
      const twKey = k.replace(/^color-/, '').replace(/-/g, '-');
      lines.push(`  --color-${twKey}: var(${tokenToCssVar(k)});`);
    }
    lines.push('');
  }

  if (radiusTokens.length) {
    lines.push('  /* Radius */');
    for (const [k] of radiusTokens) {
      const twKey = k.replace(/^radius-/, '');
      lines.push(`  --radius-${twKey}: var(${tokenToCssVar(k)});`);
    }
    lines.push('');
  }

  if (layoutTokens.length) {
    lines.push('  /* Layout */');
    for (const [k] of layoutTokens) {
      const twKey = k.replace(/^layout-/, '');
      lines.push(`  --spacing-${twKey}: var(${tokenToCssVar(k)});`);
    }
    lines.push('');
  }

  if (fontTokens.length) {
    lines.push('  /* Fonts */');
    for (const [k] of fontTokens) {
      const twKey = k.replace(/^font-/, '');
      lines.push(`  --font-${twKey}: var(${tokenToCssVar(k)});`);
    }
    lines.push('');
  }

  if (textTokens.length) {
    lines.push('  /* Text sizes */');
    for (const [k] of textTokens) {
      const twKey = k.replace(/^text-/, '');
      lines.push(`  --text-${twKey}: var(${tokenToCssVar(k)});`);
    }
    lines.push('');
  }

  lines.push('}');
  return lines.join('\n');
}

function generatePhp(base: Record<string, TokenValue>): string {
  const lines: string[] = [
    '<?php',
    '/**',
    ' * Auto-generated Blocky token array — do not edit.',
    ' * @package Blocky',
    ' */',
    '',
    'return [',
  ];
  for (const [k, v] of Object.entries(base)) {
    const safeKey = k.replace(/'/g, "\\'");
    const safeVal = v.$value.replace(/'/g, "\\'");
    lines.push(`    '${safeKey}' => '${safeVal}',`);
  }
  lines.push('];');
  return lines.join('\n');
}

function generateTs(base: Record<string, TokenValue>): string {
  const lines: string[] = [
    '/* Auto-generated Blocky tokens — do not edit */',
    '',
    '/** CSS variable name for a given token key */',
    'export function tokenVar(key: TokenKey): string {',
    '  return `var(--bky-${key})`;',
    '}',
    '',
    '/** All resolved token values (light mode) */',
    'export const tokens = {',
  ];
  for (const [k, v] of Object.entries(base)) {
    const safeKey = k.replace(/-([a-z])/g, (_, c: string) => c.toUpperCase());
    lines.push(`  '${k}': '${v.$value}',`);
  }
  lines.push('} as const;');
  lines.push('');
  lines.push('export type TokenKey = keyof typeof tokens;');
  return lines.join('\n');
}

function generateThemeJson(base: Record<string, TokenValue>): string {
  const palette: Record<string, string> = {};
  for (const [k, v] of Object.entries(base)) {
    if (!k.startsWith('color-')) continue;
    const slug = k.replace(/^color-/, '').replace(/-/g, '-');
    palette[slug] = v.$value;
  }

  const themeJson = {
    $schema: 'https://schemas.wp.org/trunk/theme.json',
    version: 3,
    settings: {
      appearanceTools: true,
      color: {
        palette: Object.entries(palette).map(([slug, color]) => ({
          slug,
          color,
          name: slug
            .split('-')
            .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' '),
        })),
      },
      typography: {
        fontFamilies: [
          { slug: 'body', name: 'Body', fontFamily: base['font-body']?.$value ?? 'sans-serif' },
          {
            slug: 'display',
            name: 'Display',
            fontFamily: base['font-display']?.$value ?? 'sans-serif',
          },
          { slug: 'mono', name: 'Mono', fontFamily: base['font-mono']?.$value ?? 'monospace' },
        ],
      },
      spacing: {
        units: ['px', 'rem', 'em', '%'],
      },
      layout: {
        contentSize: base['layout-container-max']?.$value ?? '1280px',
        wideSize: '1536px',
      },
    },
    styles: {
      color: {
        background: `var(--bky-color-surface-base)`,
        text: `var(--bky-color-text-base)`,
      },
      typography: {
        fontFamily: `var(--bky-font-body)`,
        fontSize: `var(--bky-text-base)`,
        lineHeight: '1.6',
      },
    },
  };

  return JSON.stringify(themeJson, null, 2);
}

// ── Main ──────────────────────────────────────────────────────────────────────

async function main() {
  await mkdir(buildDir, { recursive: true });

  console.log('📦 Loading tokens...');
  const { base, dark } = await loadAll();

  console.log(`  Found ${Object.keys(base).length} base tokens`);
  console.log(`  Found ${Object.keys(dark).length} dark override tokens`);

  // CSS variables — light (base)
  const cssLight = [
    '/* Auto-generated — do not edit */',
    '/* Blocky design tokens — light mode (default) */',
    '',
    generateCss(base, ':root'),
  ].join('\n');
  await writeFile(join(buildDir, 'tokens.css'), cssLight);
  console.log('  ✓ tokens.css');

  // CSS variables — dark mode overrides
  const cssDark = [
    '/* Auto-generated — do not edit */',
    '/* Blocky design tokens — dark mode overrides */',
    '',
    generateCss(dark, ':root[data-mode="dark"]'),
    '',
    '@media (prefers-color-scheme: dark) {',
    generateCss(dark, ':root[data-mode="auto"]', '    '),
    '}',
  ].join('\n');
  await writeFile(join(buildDir, 'tokens.dark.css'), cssDark);
  console.log('  ✓ tokens.dark.css');

  // Tailwind @theme
  const tailwindTheme = generateTailwindTheme(base);
  await writeFile(join(buildDir, 'tokens.tailwind.css'), tailwindTheme);
  console.log('  ✓ tokens.tailwind.css');

  // PHP
  await writeFile(join(buildDir, 'tokens.php'), generatePhp(base));
  console.log('  ✓ tokens.php');

  // TypeScript
  await writeFile(join(buildDir, 'tokens.ts'), generateTs(base));
  console.log('  ✓ tokens.ts');

  // theme.json
  await writeFile(join(buildDir, 'theme.json'), generateThemeJson(base));
  console.log('  ✓ theme.json');

  console.log('\n✅ Token build complete → packages/tokens/build/');
}

main().catch((err) => {
  console.error('❌ Token build failed:', err);
  process.exit(1);
});
