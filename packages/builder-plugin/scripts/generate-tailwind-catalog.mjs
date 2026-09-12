import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');

const outTs = resolve(root, 'src/generated/tailwindUtilityCatalog.ts');
const outPresetsTs = resolve(root, 'src/generated/blockPresetCatalog.ts');
const outCss = resolve(root, 'src/styles/generated/tailwind-utility-safelist.css');

const spacing = ['0', '1', '2', '3', '4', '5', '6', '8', '10', '12', '16', '20', '24', '32', '40', '48', '56', '64'];
const sizing = ['0', '1', '2', '3', '4', '5', '6', '8', '10', '12', '16', '20', '24', '32', '40', '48', '56', '64', 'auto', 'full', 'screen', 'min', 'max', 'fit'];
const fractions = ['1/2', '1/3', '2/3', '1/4', '2/4', '3/4', '1/5', '2/5', '3/5', '4/5', '1/6', '2/6', '3/6', '4/6', '5/6'];
const tokens = [
  'surface-base', 'surface-elevated', 'surface-overlay', 'surface-sunken',
  'border-subtle', 'border-base', 'border-strong',
  'text-base', 'text-muted', 'text-faint', 'text-inverse', 'text-on-accent',
  'accent-base', 'accent-hover', 'accent-subtle', 'accent-text',
  'feedback-success', 'feedback-warning', 'feedback-danger',
];

const variants = [
  { id: 'base', label: 'Base', prefix: '' },
  { id: 'hover', label: 'Hover', prefix: 'hover:' },
  { id: 'focus', label: 'Focus', prefix: 'focus:' },
  { id: 'focus-visible', label: 'Focus Visible', prefix: 'focus-visible:' },
  { id: 'active', label: 'Active', prefix: 'active:' },
  { id: 'disabled', label: 'Disabled', prefix: 'disabled:' },
  { id: 'sm', label: 'Small Screen', prefix: 'sm:' },
  { id: 'md', label: 'Medium Screen', prefix: 'md:' },
  { id: 'lg', label: 'Large Screen', prefix: 'lg:' },
  { id: 'xl', label: 'XL Screen', prefix: 'xl:' },
  { id: '2xl', label: '2XL Screen', prefix: '2xl:' },
  { id: 'dark', label: 'Dark Mode', prefix: 'dark:' },
  { id: 'motion-safe', label: 'Motion Safe', prefix: 'motion-safe:' },
  { id: 'motion-reduce', label: 'Motion Reduce', prefix: 'motion-reduce:' },
  { id: 'group-hover', label: 'Group Hover', prefix: 'group-hover:' },
  { id: 'peer-checked', label: 'Peer Checked', prefix: 'peer-checked:' },
];

const colorRoles = [
  { id: 'background', label: 'Background', utility: 'bg' },
  { id: 'text', label: 'Text', utility: 'text' },
  { id: 'border', label: 'Border', utility: 'border' },
  { id: 'outline', label: 'Outline', utility: 'outline' },
  { id: 'ring', label: 'Ring', utility: 'ring' },
  { id: 'decoration', label: 'Decoration', utility: 'decoration' },
];

const blockPresets = [
  preset('layout-flex', 'Flex', 'layout', 'bky/container', 'F', ['flex', 'layout', 'stack', 'row', 'column', 'wrap'], {
    props: { padding: 'base', twClasses: ['flex', 'flex-row', 'items-stretch', 'gap-4'] },
  }),
  preset('layout-section-centered', 'Centered Section', 'layout', 'bky/section', 'S', ['section', 'center', 'hero'], {
    props: { paddingY: 'xl', paddingX: 'base', horizontalAlign: 'center', verticalAlign: 'center', minHeight: 'screen50', gap: 'base' },
  }),
  preset('layout-card-surface', 'Surface Card', 'layout', 'bky/card', 'C', ['card', 'surface'], {
    props: { padding: 'base', background: 'elevated', border: true, shadow: 'sm', overflow: 'hidden' },
  }),
];

const boxModelVariableClasses = [
  'mt-[var(--bky-space-margin-top)]',
  'mr-[var(--bky-space-margin-right)]',
  'mb-[var(--bky-space-margin-bottom)]',
  'ml-[var(--bky-space-margin-left)]',
  'pt-[var(--bky-space-padding-top)]',
  'pr-[var(--bky-space-padding-right)]',
  'pb-[var(--bky-space-padding-bottom)]',
  'pl-[var(--bky-space-padding-left)]',
];

const groups = [
  group('layout', 'Layout', [
    utility('display', 'Display', ['block', 'inline-block', 'inline', 'flex', 'inline-flex', 'grid', 'inline-grid', 'contents', 'hidden']),
    utility('position', 'Position', ['static', 'relative', 'absolute', 'fixed', 'sticky']),
    utility('inset', 'Inset', expand(['inset', 'inset-x', 'inset-y', 'top', 'right', 'bottom', 'left'], ['0', '1', '2', '3', '4', '6', '8', '10', '12', '16', 'auto', 'full'])),
    utility('overflow', 'Overflow', expand(['overflow', 'overflow-x', 'overflow-y'], ['auto', 'hidden', 'visible', 'scroll', 'clip'])),
    utility('z-index', 'Z Index', ['z-0', 'z-10', 'z-20', 'z-30', 'z-40', 'z-50', 'z-auto']),
  ]),
  group('spacing', 'Spacing', [
    utility('padding', 'Padding', expand(['p', 'px', 'py', 'pt', 'pr', 'pb', 'pl'], spacing)),
    utility('margin', 'Margin', expand(['m', 'mx', 'my', 'mt', 'mr', 'mb', 'ml'], [...spacing, 'auto'])),
    utility('gap', 'Gap', expand(['gap', 'gap-x', 'gap-y'], spacing)),
    utility('space', 'Space Between', expand(['space-x', 'space-y'], spacing)),
  ]),
  group('sizing', 'Sizing', [
    utility('width', 'Width', [...expand(['w', 'min-w', 'max-w'], sizing), ...expand(['w'], fractions)]),
    utility('height', 'Height', [...expand(['h', 'min-h', 'max-h'], sizing), ...expand(['h'], fractions)]),
    utility('container', 'Container', ['container']),
  ]),
  group('typography', 'Typography', [
    utility('font-family', 'Font Family', ['font-body', 'font-display', 'font-mono']),
    utility('font-size', 'Font Size', expand(['text'], ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl'])),
    utility('font-weight', 'Font Weight', ['font-thin', 'font-light', 'font-normal', 'font-medium', 'font-semibold', 'font-bold', 'font-extrabold', 'font-black']),
    utility('line-height', 'Line Height', expand(['leading'], ['none', 'tight', 'snug', 'normal', 'relaxed', 'loose'])),
    utility('letter-spacing', 'Letter Spacing', expand(['tracking'], ['tighter', 'tight', 'normal', 'wide', 'wider', 'widest'])),
    utility('text-align', 'Text Align', expand(['text'], ['left', 'center', 'right', 'start', 'end', 'justify'])),
    utility('text-color', 'Text Color', expand(['text'], tokens)),
    utility('text-decoration', 'Text Decoration', ['underline', 'overline', 'line-through', 'no-underline', 'decoration-solid', 'decoration-double', 'decoration-dotted', 'decoration-dashed', 'decoration-wavy']),
    utility('whitespace', 'Whitespace', expand(['whitespace'], ['normal', 'nowrap', 'pre', 'pre-line', 'pre-wrap', 'break-spaces'])),
  ]),
  group('flex-grid', 'Flex & Grid', [
    utility('flex', 'Flex', ['flex-1', 'flex-auto', 'flex-initial', 'flex-none', 'flex-row', 'flex-row-reverse', 'flex-col', 'flex-col-reverse', 'flex-wrap', 'flex-nowrap', 'flex-wrap-reverse']),
    utility('items', 'Align Items', expand(['items'], ['start', 'center', 'end', 'stretch', 'baseline'])),
    utility('justify', 'Justify Content', expand(['justify'], ['start', 'center', 'end', 'between', 'around', 'evenly', 'stretch'])),
    utility('content', 'Align Content', expand(['content'], ['start', 'center', 'end', 'between', 'around', 'evenly', 'stretch'])),
    utility('self', 'Align Self', expand(['self'], ['auto', 'start', 'center', 'end', 'stretch', 'baseline'])),
    utility('grid-columns', 'Grid Columns', expand(['grid-cols'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', 'none', 'subgrid'])),
    utility('grid-rows', 'Grid Rows', expand(['grid-rows'], ['1', '2', '3', '4', '5', '6', 'none', 'subgrid'])),
    utility('span', 'Column & Row Span', [...expand(['col-span', 'row-span'], ['1', '2', '3', '4', '5', '6', 'full']), ...expand(['col-start', 'col-end', 'row-start', 'row-end'], ['1', '2', '3', '4', '5', '6', 'auto'])]),
  ]),
  group('visual', 'Visual', [
    utility('background', 'Background', expand(['bg'], tokens)),
    utility('border-width', 'Border Width', ['border', 'border-0', 'border-2', 'border-4', 'border-8', 'border-t', 'border-r', 'border-b', 'border-l']),
    utility('border-color', 'Border Color', expand(['border'], tokens)),
    utility('border-style', 'Border Style', ['border-solid', 'border-dashed', 'border-dotted', 'border-double', 'border-hidden', 'border-none']),
    utility('radius', 'Radius', ['rounded', 'rounded-none', 'rounded-sm', 'rounded-base', 'rounded-md', 'rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-full', 'rounded-card', 'rounded-button', 'rounded-input', 'rounded-badge', 'rounded-image']),
    utility('shadow', 'Shadow', ['shadow-none', 'shadow-sm', 'shadow', 'shadow-md', 'shadow-lg', 'shadow-xl', 'shadow-2xl']),
    utility('opacity', 'Opacity', expand(['opacity'], ['0', '5', '10', '20', '25', '30', '40', '50', '60', '70', '75', '80', '90', '95', '100'])),
  ]),
  group('media', 'Media', [
    utility('object-fit', 'Object Fit', expand(['object'], ['contain', 'cover', 'fill', 'none', 'scale-down'])),
    utility('object-position', 'Object Position', expand(['object'], ['bottom', 'center', 'left', 'left-bottom', 'left-top', 'right', 'right-bottom', 'right-top', 'top'])),
    utility('aspect', 'Aspect Ratio', ['aspect-auto', 'aspect-square', 'aspect-video']),
  ]),
  group('motion', 'Motion', [
    utility('transition', 'Transition', ['transition', 'transition-all', 'transition-colors', 'transition-opacity', 'transition-shadow', 'transition-transform', 'transition-none']),
    utility('duration', 'Duration', expand(['duration'], ['75', '100', '150', '200', '300', '500', '700', '1000'])),
    utility('delay', 'Delay', expand(['delay'], ['0', '75', '100', '150', '200', '300', '500', '700', '1000'])),
    utility('ease', 'Easing', ['ease-linear', 'ease-in', 'ease-out', 'ease-in-out']),
    utility('transform', 'Transform', ['transform', 'transform-none', 'scale-0', 'scale-50', 'scale-75', 'scale-90', 'scale-95', 'scale-100', 'scale-105', 'scale-110', 'scale-125', 'scale-150']),
  ]),
];

function group(id, label, utilities) {
  return { id, label, utilities };
}

function utility(id, label, classes) {
  return { id, label, classes: [...new Set(classes)].sort() };
}

function preset(id, label, category, type, icon, keywords, defaults) {
  return { id, label, category, type, icon, keywords, ...defaults };
}

function expand(prefixes, values) {
  return prefixes.flatMap(prefix => values.map(value => `${prefix}-${value}`));
}

function candidateClasses() {
  const baseClasses = groups.flatMap(group => group.utilities.flatMap(item => item.classes));
  const runtimeColorClasses = colorRoles.flatMap(role => variants.map(variant => {
    const variableName = `--bky-tw-${variant.id}-${role.id}`;
    return `${variant.prefix}${role.utility}-[var(${variableName})]`;
  }));
  const runtimeGradientClasses = variants.map(variant => {
    const variableName = `--bky-tw-${variant.id}-background-gradient`;
    return `${variant.prefix}bg-[linear-gradient(var(${variableName}))]`;
  });
  const prefixed = variants
    .filter(variant => variant.prefix !== '')
    .flatMap(variant => baseClasses.map(className => `${variant.prefix}${className}`));

  const presetClasses = blockPresets.flatMap(item => Array.isArray(item.props?.twClasses) ? item.props.twClasses : []);
  const candidates = [...new Set([...baseClasses, ...prefixed, ...runtimeColorClasses, ...runtimeGradientClasses, ...boxModelVariableClasses, ...presetClasses])];
  const important = candidates.map(addImportantModifier);

  return [...new Set([...candidates, ...important])].sort();
}

function addImportantModifier(className) {
  const lastVariantSeparator = className.lastIndexOf(':');
  if (lastVariantSeparator === -1) return `!${className}`;
  return `${className.slice(0, lastVariantSeparator + 1)}!${className.slice(lastVariantSeparator + 1)}`;
}

function cssStringLiteral(value) {
  return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function chunk(items, size) {
  const chunks = [];
  for (let index = 0; index < items.length; index += size) {
    chunks.push(items.slice(index, index + size));
  }
  return chunks;
}

async function main() {
  const classes = candidateClasses();

  await mkdir(dirname(outTs), { recursive: true });
  await mkdir(dirname(outCss), { recursive: true });

  const ts = [
    '// Auto-generated by scripts/generate-tailwind-catalog.mjs. Do not edit by hand.',
    '',
    'export interface TailwindVariantOption {',
    '  id: string;',
    '  label: string;',
    '  prefix: string;',
    '}',
    '',
    'export interface TailwindUtilityItem {',
    '  id: string;',
    '  label: string;',
    '  classes: string[];',
    '}',
    '',
    'export interface TailwindUtilityGroup {',
    '  id: string;',
    '  label: string;',
    '  utilities: TailwindUtilityItem[];',
    '}',
    '',
    'export interface TailwindColorRole {',
    '  id: string;',
    '  label: string;',
    '  utility: string;',
    '}',
    '',
    `export const tailwindVariantOptions = ${JSON.stringify(variants, null, 2)} satisfies TailwindVariantOption[];`,
    '',
    `export const tailwindUtilityGroups = ${JSON.stringify(groups, null, 2)} satisfies TailwindUtilityGroup[];`,
    '',
    `export const tailwindColorRoles = ${JSON.stringify(colorRoles, null, 2)} satisfies TailwindColorRole[];`,
    '',
  ].join('\n');

  const presetsTs = [
    '// Auto-generated by scripts/generate-tailwind-catalog.mjs. Do not edit by hand.',
    '',
    'export interface BlockPresetDefinition {',
    '  id: string;',
    '  label: string;',
    '  category: string;',
    '  type: string;',
    '  icon?: string;',
    '  keywords?: string[];',
    '  props?: Record<string, unknown>;',
    '  variants?: Record<string, string>;',
    '}',
    '',
    `export const blockPresetDefinitions = ${JSON.stringify(blockPresets, null, 2)} satisfies BlockPresetDefinition[];`,
    '',
  ].join('\n');

  const cssLines = [
    '/* Auto-generated by scripts/generate-tailwind-catalog.mjs. Do not edit by hand. */',
    '/* Makes builder-managed Tailwind classes available inside the preview iframe. */',
    '',
    ...chunk(classes, 80).map(classChunk => `@source inline("${cssStringLiteral(classChunk.join(' '))}");`),
    '',
  ];

  await writeFile(outTs, ts, 'utf8');
  await writeFile(outPresetsTs, presetsTs, 'utf8');
  await writeFile(outCss, cssLines.join('\n'), 'utf8');

  console.log(`Generated ${groups.length} groups, ${blockPresets.length} presets, ${classes.length} Tailwind class candidates.`);
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});