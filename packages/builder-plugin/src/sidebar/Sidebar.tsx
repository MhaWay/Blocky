import * as preact from 'preact';
import type { FunctionComponent } from 'preact';
import { useMemo, useState } from 'preact/hooks';
import { useBlockRegistry } from '../store/blockRegistry';
import { useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import type { BlockDefinition, BlockInsertPreset } from '../sdk/types';
import { blockPresetDefinitions } from '../generated/blockPresetCatalog';
import type { BlockPresetDefinition } from '../generated/blockPresetCatalog';
import { ThemePanel } from '../theme/ThemePanel';
import { OverlayPanel } from './OverlayPanel';
import { t } from '../i18n';

type SidebarTab = 'layout' | 'content' | 'wordpress';

const HIDDEN_BLOCK_TYPES = new Set(['bky/mega-menu']);

const CONTENT_CATEGORIES = [
  { id: 'essentials', label: t('sidebar.categoryEssentials', 'Essentials') },
  { id: 'media', label: t('sidebar.categoryMedia', 'Media') },
  { id: 'navigation', label: t('sidebar.categoryNavigation', 'Navigation') },
  { id: 'marketing', label: t('sidebar.categoryMarketing', 'Marketing') },
  { id: 'interactive', label: t('sidebar.categoryInteractive', 'Interactive') },
  { id: 'forms', label: t('sidebar.categoryForms', 'Forms') },
  { id: 'overlays', label: t('sidebar.categoryOverlays', 'Overlays') },
  { id: 'dynamic', label: t('sidebar.categoryDynamic', 'Dynamic') },
  { id: 'advanced', label: t('sidebar.categoryAdvanced', 'Advanced') },
];

type LibraryItem =
  | { kind: 'block'; id: string; type: string; definition: BlockDefinition }
  | { kind: 'preset'; id: string; type: string; preset: BlockPresetDefinition };

export const Sidebar: FunctionComponent = () => {
  const definitions = useBlockRegistry((s) => s.definitions);
  const document = useDocumentStore((s) => s.document);
  const insertBlock = useDocumentStore((s) => s.insertBlock);
  const selectedId = useUiStore((s) => s.selectedNodeId);
  const activePanel = useUiStore((s) => s.activePanel);
  const setActivePanel = useUiStore((s) => s.setActivePanel);
  const [query, setQuery] = useState('');
  const [activeTab, setActiveTab] = useState<SidebarTab>('layout');
  const [openCategories, setOpenCategories] = useState<Record<string, boolean>>({
    essentials: true,
    media: true,
    navigation: false,
    marketing: false,
    interactive: false,
    forms: false,
    overlays: false,
    dynamic: false,
    advanced: false,
  });

  const selectedDefinition = selectedId
    ? definitions.find((definition) => definition.type === document?.nodes[selectedId]?.type)
    : null;
  const selectedIsContainer = selectedDefinition?.editorConfig?.isContainer === true;

  const allItems = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    const blockItems: LibraryItem[] = definitions
      .filter((definition) => !HIDDEN_BLOCK_TYPES.has(definition.type))
      .filter((definition) => matchesQuery(definition, normalizedQuery))
      .map((definition) => ({
        kind: 'block',
        id: definition.type,
        type: definition.type,
        definition,
      }));
    const presetItems: LibraryItem[] = blockPresetDefinitions
      .filter((preset) => matchesPresetQuery(preset, normalizedQuery))
      .map((preset) => ({ kind: 'preset', id: preset.id, type: preset.type, preset }));
    return [...presetItems, ...blockItems];
  }, [definitions, query]);

  const isSearching = query.trim() !== '';

  const layoutItems = useMemo(
    () =>
      allItems
        .filter((i) => categoryForItem(i) === 'layout')
        .slice()
        .sort(compareLayoutItems),
    [allItems]
  );
  const wordpressItems = useMemo(
    () => allItems.filter((i) => categoryForItem(i) === 'wordpress'),
    [allItems]
  );
  const contentByGroup = useMemo(
    () =>
      CONTENT_CATEGORIES.map((cat) => ({
        ...cat,
        blocks: allItems.filter((i) => categoryForItem(i) === cat.id),
      })).filter((cat) => cat.blocks.length > 0),
    [allItems]
  );

  const addFromLibrary = (item: LibraryItem) => {
    const preset = presetPayload(item);
    if (selectedId) {
      insertBlock(
        item.type,
        selectedId,
        selectedIsContainer ? 'inside' : 'after',
        undefined,
        preset
      );
      delete window.BlockyBuilderDrag;
      return;
    }
    insertBlock(item.type, undefined, undefined, undefined, preset);
    delete window.BlockyBuilderDrag;
  };

  return (
    <aside
      class="flex flex-col border-r border-border-subtle bg-surface-elevated"
      style={{ width: 'var(--builder-sidebar-width)' }}
    >
      <div class="grid grid-cols-2 gap-1 border-b border-border-subtle p-2">
        {(['blocks', 'theme'] as const).map((panel) => (
          <button
            key={panel}
            type="button"
            onClick={() => setActivePanel(panel)}
            class={`rounded-input px-2 py-1.5 text-xs font-semibold uppercase tracking-wide transition-colors ${
              activePanel === panel
                ? 'bg-accent-base text-text-on-accent'
                : 'text-text-muted hover:bg-surface-overlay hover:text-text-base'
            }`}
          >
            {panel === 'blocks' ? t('sidebar.blocks', 'Blocks') : t('sidebar.theme', 'Theme')}
          </button>
        ))}
      </div>

      {activePanel === 'theme' && <ThemePanel />}

      {activePanel === 'blocks' && (
        <>
          {/* Search */}
          <div class="border-b border-border-subtle px-4 py-3">
            <label class="block">
              <span class="sr-only">{t('sidebar.searchBlocks', 'Search blocks')}</span>
              <input
                type="search"
                value={query}
                onInput={(event) => setQuery((event.target as HTMLInputElement).value)}
                placeholder={t('sidebar.searchBlocksPlaceholder', 'Search blocks…')}
                class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base
                   placeholder:text-text-faint focus:border-accent-base focus:outline-none"
              />
            </label>
          </div>

          {/* Tab bar (hidden while searching) */}
          {!isSearching && (
            <div class="flex border-b border-border-subtle">
              {(['layout', 'content', 'wordpress'] as SidebarTab[]).map((tab) => (
                <button
                  key={tab}
                  type="button"
                  onClick={() => setActiveTab(tab)}
                  class={`flex-1 py-2.5 text-xs font-semibold uppercase tracking-wide transition-colors ${
                    activeTab === tab
                      ? 'border-b-2 border-accent-base bg-accent-subtle text-accent-text'
                      : 'text-text-muted hover:text-text-base'
                  }`}
                >
                  {tab === 'layout'
                    ? t('sidebar.layout', 'Layout')
                    : tab === 'content'
                      ? t('sidebar.content', 'Content')
                      : t('sidebar.wordpress', 'WordPress')}
                </button>
              ))}
            </div>
          )}

          <div class="flex-1 overflow-y-auto p-3">
            {/* Search results (all tabs) */}
            {isSearching &&
              (allItems.length === 0 ? (
                <p class="px-2 py-4 text-sm text-text-faint">
                  {t('sidebar.noBlocksFound', 'No blocks found.')}
                </p>
              ) : (
                <div class="grid grid-cols-2 gap-2">
                  {allItems.map((item) => (
                    <BlockButton key={item.id} item={item} onAdd={addFromLibrary} />
                  ))}
                </div>
              ))}

            {/* Layout tab — flat grid */}
            {!isSearching &&
              activeTab === 'layout' &&
              (layoutItems.length === 0 ? (
                <p class="px-2 py-4 text-sm text-text-faint">
                  {t('sidebar.noLayoutBlocks', 'No layout blocks.')}
                </p>
              ) : (
                <div class="grid grid-cols-2 gap-2">
                  {layoutItems.map((item) => (
                    <BlockButton key={item.id} item={item} onAdd={addFromLibrary} />
                  ))}
                </div>
              ))}

            {/* Content tab — sub-category accordion */}
            {!isSearching &&
              activeTab === 'content' &&
              (contentByGroup.length === 0 ? (
                <p class="px-2 py-4 text-sm text-text-faint">
                  {t('sidebar.noContentBlocks', 'No content blocks.')}
                </p>
              ) : (
                contentByGroup.map((cat) => {
                  const isOpen = openCategories[cat.id] !== false;
                  return (
                    <section
                      key={cat.id}
                      class="mb-3 rounded-card border border-border-subtle bg-surface-base"
                    >
                      <button
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-text-muted"
                        aria-expanded={isOpen}
                        onClick={() =>
                          setOpenCategories((prev) => ({ ...prev, [cat.id]: !isOpen }))
                        }
                      >
                        <span>{cat.label}</span>
                        <span aria-hidden="true">{isOpen ? '−' : '+'}</span>
                      </button>
                      {isOpen && (
                        <div class="grid grid-cols-2 gap-2 border-t border-border-subtle p-2">
                          {cat.blocks.map((item) => (
                            <BlockButton key={item.id} item={item} onAdd={addFromLibrary} />
                          ))}
                        </div>
                      )}
                    </section>
                  );
                })
              ))}

            {/* WordPress tab — flat grid */}
            {!isSearching &&
              activeTab === 'wordpress' &&
              (wordpressItems.length === 0 ? (
                <p class="px-2 py-4 text-sm text-text-faint">
                  {t('sidebar.noWordPressBlocks', 'No WordPress blocks available.')}
                </p>
              ) : (
                <div class="grid grid-cols-2 gap-2">
                  {wordpressItems.map((item) => (
                    <BlockButton key={item.id} item={item} onAdd={addFromLibrary} />
                  ))}
                </div>
              ))}
          </div>
          {activeTab === 'layout' && <OverlayPanel />}
        </>
      )}
    </aside>
  );
};

interface BlockButtonProps {
  item: LibraryItem;
  onAdd: (item: LibraryItem) => void;
}

const BlockButton: FunctionComponent<BlockButtonProps> = ({ item, onAdd }) => (
  <button
    type="button"
    draggable
    title={descriptionFor(item) || labelForItem(item)}
    onClick={() => onAdd(item)}
    onPointerDown={() => {
      window.BlockyBuilderDrag = dragPayload(item.type, presetPayload(item));
    }}
    onDragStart={(event) => startBlockDrag(event, item.type, presetPayload(item))}
    onDragEnd={() => {
      delete window.BlockyBuilderDrag;
    }}
    class="flex min-h-24 flex-col items-center justify-center gap-2 rounded-card border border-border-subtle
           bg-surface-elevated p-3 text-center text-xs font-medium text-text-base
           hover:border-accent-base hover:bg-accent-subtle focus:border-accent-base focus:outline-none transition-colors"
  >
    <BlockIcon item={item} />
    <span>{labelForItem(item)}</span>
  </button>
);

function startBlockDrag(
  event: preact.JSX.TargetedDragEvent<HTMLButtonElement>,
  type: string,
  preset?: BlockInsertPreset
): void {
  window.BlockyBuilderDrag = dragPayload(type, preset);
  event.dataTransfer?.setData('application/blocky-block-type', type);
  if (preset)
    event.dataTransfer?.setData('application/blocky-block-preset', JSON.stringify(preset));
  event.dataTransfer?.setData('text/plain', type);
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'copy';
  }
}

function BlockIcon({ item }: { item: LibraryItem }): preact.JSX.Element {
  const glyph = blockGlyph(item.type);
  const icon = item.kind === 'preset' ? item.preset.icon : item.definition.icon;
  const literalIcon = shouldUseLiteralBlockIcon(item.type) ? icon : null;
  return (
    <span
      class="flex h-8 w-8 items-center justify-center rounded-input bg-surface-elevated
                 text-sm font-bold text-accent-base"
    >
      {glyph || literalIcon || item.type.split('/')[1]?.[0]?.toUpperCase() || '?'}
    </span>
  );
}

function shouldUseLiteralBlockIcon(type: string): boolean {
  return type.startsWith('bky/wp-') || type === 'bky/theme-toggle' || type === 'bky/html';
}

function blockGlyph(type: string): preact.JSX.Element | string | null {
  const strokeProps = {
    stroke: 'currentColor',
    'stroke-width': '1.5',
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    fill: 'none',
  } as const;

  switch (type) {
    case 'bky/heading':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path {...strokeProps} d="M4 4v10M14 4v10M4 9h10" />
        </svg>
      );
    case 'bky/text':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path {...strokeProps} d="M4 5h10M4 9h8M4 13h10" />
        </svg>
      );
    case 'bky/button':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3.5" y="5" width="11" height="8" rx="2" />
        </svg>
      );
    case 'bky/image':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3.5" y="4" width="11" height="10" rx="1.5" />
          <circle cx="7" cy="7" r="1.2" fill="currentColor" />
          <path {...strokeProps} d="M5 12l2.5-2.5L10 12l1.5-1.5L13.5 12" />
        </svg>
      );
    case 'bky/section':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="2.5" y="3" width="13" height="12" rx="2" />
          <path {...strokeProps} d="M5.5 6h7M5.5 9h5M5.5 12h7" opacity="0.7" />
        </svg>
      );
    case 'bky/rows':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3" y="3.5" width="12" height="3" rx="1" />
          <rect {...strokeProps} x="3" y="7.5" width="12" height="3" rx="1" />
          <rect {...strokeProps} x="3" y="11.5" width="12" height="3" rx="1" />
        </svg>
      );
    case 'bky/grid':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3" y="3" width="5" height="5" rx="1" />
          <rect {...strokeProps} x="10" y="3" width="5" height="5" rx="1" />
          <rect {...strokeProps} x="3" y="10" width="5" height="5" rx="1" />
          <rect {...strokeProps} x="10" y="10" width="5" height="5" rx="1" />
        </svg>
      );
    case 'bky/container':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3.5" y="4" width="11" height="10" rx="1.5" />
        </svg>
      );
    case 'bky/columns':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3" y="4" width="3.5" height="10" rx="1" />
          <rect {...strokeProps} x="7.25" y="4" width="3.5" height="10" rx="1" />
          <rect {...strokeProps} x="11.5" y="4" width="3.5" height="10" rx="1" />
        </svg>
      );
    case 'bky/card':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3" y="3.5" width="12" height="11" rx="2" />
          <path {...strokeProps} d="M5.5 7h7M5.5 10h5" opacity="0.7" />
        </svg>
      );
    case 'bky/divider':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path {...strokeProps} d="M3 9h12" />
        </svg>
      );
    case 'bky/spacer':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path {...strokeProps} d="M9 3v12M6.5 5.5L9 3l2.5 2.5M6.5 12.5L9 15l2.5-2.5" />
        </svg>
      );
    case 'bky/list':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <circle cx="4.5" cy="5.5" r="1" fill="currentColor" />
          <circle cx="4.5" cy="9" r="1" fill="currentColor" />
          <circle cx="4.5" cy="12.5" r="1" fill="currentColor" />
          <path {...strokeProps} d="M7 5.5h6M7 9h6M7 12.5h6" />
        </svg>
      );
    case 'bky/quote':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path
            {...strokeProps}
            d="M6.5 6.5H5a2 2 0 0 0-2 2v2h3.5v2H4a1 1 0 0 1-1-1V9a4 4 0 0 1 4-4h-.5Zm7 0H12a2 2 0 0 0-2 2v2h3.5v2H11a1 1 0 0 1-1-1V9a4 4 0 0 1 4-4h-.5Z"
          />
        </svg>
      );
    case 'bky/video':
      return (
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <rect {...strokeProps} x="3.5" y="4.5" width="8" height="9" rx="1.5" />
          <path {...strokeProps} d="M11.5 7l3-1.5v7l-3-1.5" />
        </svg>
      );
    case 'bky/html':
      return '</>';
    case 'bky/wp-post-title':
    case 'bky/wp-post-content':
    case 'bky/wp-featured-image':
    case 'bky/wp-template-part':
      return 'WP';
    case 'bky/wp-shortcode':
      return '[…]';
    case 'bky/wp-hook':
      return '⚡';
    case 'bky/theme-toggle':
      return '🎨';
    default:
      return null;
  }
}

function labelForItem(item: LibraryItem): string {
  if (item.kind === 'preset') return item.preset.label;
  return labelFor(item.definition);
}

function labelFor(definition: BlockDefinition): string {
  if (definition.label) return definition.label;
  const slug = definition.type.split('/')[1] ?? definition.type;
  return slug.charAt(0).toUpperCase() + slug.slice(1);
}

function descriptionFor(item: LibraryItem): string | undefined {
  if (item.kind === 'block') return item.definition.description;
  return item.preset.keywords?.join(', ');
}

function categoryForItem(item: LibraryItem): string {
  if (item.kind === 'preset') return item.preset.category;
  return categoryFor(item.definition);
}

function presetPayload(item: LibraryItem): BlockInsertPreset | undefined {
  if (item.kind !== 'preset') return undefined;
  const payload: BlockInsertPreset = {};
  if (item.preset.props) payload.props = item.preset.props;
  if (item.preset.variants) payload.variants = item.preset.variants;
  return Object.keys(payload).length ? payload : undefined;
}

function dragPayload(
  type: string,
  preset?: BlockInsertPreset
): NonNullable<Window['BlockyBuilderDrag']> {
  return preset ? { blockType: type, preset } : { blockType: type };
}

function categoryFor(definition: BlockDefinition): string {
  const familyMap: Record<string, string> = {
    'bky/section': 'layout',
    'bky/rows': 'layout',
    'bky/container': 'layout',
    'bky/grid': 'layout',
    'bky/columns': 'layout',
    'bky/card': 'layout',

    'bky/heading': 'essentials',
    'bky/text': 'essentials',
    'bky/button': 'essentials',
    'bky/list': 'essentials',
    'bky/quote': 'essentials',
    'bky/divider': 'essentials',
    'bky/spacer': 'essentials',
    'bky/icon': 'essentials',
    'bky/alert': 'essentials',

    'bky/image': 'media',
    'bky/video': 'media',
    'bky/image-gallery': 'media',
    'bky/basic-gallery': 'media',
    'bky/image-carousel': 'media',
    'bky/lottie': 'media',
    'bky/embed-google-maps': 'media',
    'bky/embed-iframe': 'media',
    'bky/lightbox': 'media',

    'bky/anchor': 'navigation',
    'bky/social-icons': 'navigation',
    'bky/share-buttons': 'navigation',
    'bky/search-form': 'navigation',
    'bky/nav-menu': 'navigation',
    'bky/breadcrumbs': 'navigation',
    'bky/table-of-contents': 'navigation',
    'bky/back-to-top': 'navigation',

    'bky/icon-box': 'marketing',
    'bky/icon-list': 'marketing',
    'bky/image-box': 'marketing',
    'bky/progress-bar': 'marketing',
    'bky/counter': 'marketing',
    'bky/star-rating': 'marketing',
    'bky/content-carousel': 'marketing',
    'bky/slider': 'marketing',
    'bky/call-to-action': 'marketing',
    'bky/testimonial': 'marketing',
    'bky/price-table': 'marketing',
    'bky/price-list': 'marketing',
    'bky/countdown': 'marketing',
    'bky/animated-headline': 'marketing',
    'bky/marquee': 'marketing',
    'bky/sticky-bar': 'marketing',

    'bky/flip-box': 'interactive',
    'bky/hotspot': 'interactive',
    'bky/before-after-slider': 'interactive',
    'bky/scroll-progress': 'interactive',
    'bky/tabs': 'interactive',
    'bky/accordion': 'interactive',
    'bky/toggle': 'interactive',

    'bky/login-form': 'forms',
    'bky/register-form': 'forms',
    'bky/contact-form': 'forms',
    'bky/form': 'forms',
    'bky/form-field-text': 'forms',
    'bky/form-field-textarea': 'forms',
    'bky/form-field-select': 'forms',
    'bky/form-field-radio': 'forms',
    'bky/form-field-checkbox': 'forms',
    'bky/form-field-date': 'forms',
    'bky/form-field-file': 'forms',
    'bky/form-field-hidden': 'forms',
    'bky/form-field-honeypot': 'forms',
    'bky/form-submit': 'forms',

    'bky/popup': 'overlays',
    'bky/notification-toast': 'overlays',
    'bky/cookie-banner': 'overlays',
    'bky/command-palette': 'overlays',
    'bky/modal': 'overlays',
    'bky/offcanvas': 'overlays',
    'bky/drawer': 'overlays',
    'bky/modal-trigger': 'overlays',
    'bky/popover': 'overlays',
    'bky/tooltip': 'overlays',
    'bky/dialog-confirm': 'overlays',

    'bky/posts-list': 'dynamic',
    'bky/posts-grid': 'dynamic',
    'bky/featured-posts': 'dynamic',
    'bky/taxonomy-list': 'dynamic',
    'bky/archive-posts': 'dynamic',
    'bky/pagination': 'dynamic',
    'bky/author-box': 'dynamic',
    'bky/comments': 'dynamic',
    'bky/comment-form': 'dynamic',
    'bky/post-navigation': 'dynamic',
    'bky/sitemap': 'dynamic',

    'bky/html': 'advanced',
    'bky/code-highlight': 'advanced',
    'bky/wp-post-title': 'wordpress',
    'bky/wp-post-content': 'wordpress',
    'bky/wp-featured-image': 'wordpress',
    'bky/wp-template-part': 'wordpress',
    'bky/wp-shortcode': 'wordpress',
    'bky/wp-hook': 'wordpress',
    'bky/theme-toggle': 'wordpress',
  };

  const family = familyMap[definition.type];
  if (family) return family;

  if (definition.category === 'layout' || definition.category === 'wordpress')
    return definition.category;
  if (definition.category === 'media') return 'media';
  if (definition.category === 'advanced') return 'advanced';
  if (definition.category === 'content') return 'essentials';
  return 'essentials';
}

function matchesQuery(definition: BlockDefinition, query: string): boolean {
  if (!query) return true;
  const haystack = [
    definition.type,
    labelFor(definition),
    definition.description ?? '',
    ...(definition.keywords ?? []),
  ]
    .join(' ')
    .toLowerCase();
  return haystack.includes(query);
}

function matchesPresetQuery(preset: BlockPresetDefinition, query: string): boolean {
  if (!query) return true;
  const haystack = [preset.type, preset.label, preset.category, ...(preset.keywords ?? [])]
    .join(' ')
    .toLowerCase();
  return haystack.includes(query);
}

function compareLayoutItems(a: LibraryItem, b: LibraryItem): number {
  const order = layoutOrderFor(a) - layoutOrderFor(b);
  if (order !== 0) return order;
  return labelForItem(a).localeCompare(labelForItem(b));
}

function layoutOrderFor(item: LibraryItem): number {
  const key = item.kind === 'preset' ? item.preset.id : item.type;
  const order: Record<string, number> = {
    'layout-rows': 10,
    'bky/rows': 10,
    'bky/columns': 20,
    'bky/grid': 30,
    'layout-flex': 40,
    'bky/section': 50,
    'layout-section-centered': 60,
    'bky/container': 70,
    'bky/card': 80,
    'layout-card-surface': 90,
  };
  return order[key] ?? 1000;
}
