import type { FunctionComponent } from 'preact';
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { useBlockRegistry } from '../store/blockRegistry';
import { useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import type { BlockDefinition, BuilderDocument, BuilderNode } from '../sdk/types';
import { t } from '../i18n';

const PANEL_WIDTH = 320;
const PANEL_HEIGHT = 420;
const VIEWPORT_MARGIN = 16;

interface OutlineFloatProps {
  hidden?: boolean;
}

export const OutlineFloat: FunctionComponent<OutlineFloatProps> = ({ hidden = false }) => {
  const definitions = useBlockRegistry(s => s.definitions);
  const document = useDocumentStore(s => s.document);
  const selectedId = useUiStore(s => s.selectedNodeId);
  const selectNode = useUiStore(s => s.selectNode);
  const outlinePosition = useUiStore(s => s.outlinePosition);
  const setOutlinePosition = useUiStore(s => s.setOutlinePosition);
  const setOutlineVisible = useUiStore(s => s.setOutlineVisible);

  const definitionMap = useMemo(
    () => new Map(definitions.map(definition => [definition.type, definition])),
    [definitions],
  );
  const dragOffsetRef = useRef({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);
  const [collapsedNodes, setCollapsedNodes] = useState<Record<string, boolean>>({});

  useEffect(() => {
    const handlePointerMove = (event: PointerEvent) => {
      if (!isDragging) return;
      setOutlinePosition(clampPosition({
        x: event.clientX - dragOffsetRef.current.x,
        y: event.clientY - dragOffsetRef.current.y,
      }));
    };

    const handlePointerUp = () => {
      setIsDragging(false);
    };

    const handleResize = () => {
      setOutlinePosition(clampPosition(outlinePosition));
    };

    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp);
    window.addEventListener('resize', handleResize);
    return () => {
      window.removeEventListener('pointermove', handlePointerMove);
      window.removeEventListener('pointerup', handlePointerUp);
      window.removeEventListener('resize', handleResize);
    };
  }, [isDragging, outlinePosition, setOutlinePosition]);

  if (hidden) return null;

  const rootNode = document ? document.nodes[document.root] : null;

  return (
    <div
      class="fixed z-30 overflow-hidden rounded-card border border-border-base bg-surface-elevated shadow-2xl"
      style={{
        left: `${outlinePosition.x}px`,
        top: `${outlinePosition.y}px`,
        width: `${PANEL_WIDTH}px`,
        height: `${PANEL_HEIGHT}px`,
      }}
    >
      <button
        type="button"
        onClick={() => setOutlineVisible(false)}
        class="absolute right-1 top-1 z-10 flex h-7 w-7 items-center justify-center rounded-input text-text-muted transition-colors hover:bg-surface-overlay hover:text-text-base"
        aria-label={t('outline.close', 'Close outline')}
        title={t('outline.close', 'Close outline')}
      >
        ×
      </button>
      <div
        class={`flex cursor-move items-center border-b border-border-subtle px-3 py-2 ${isDragging ? 'bg-accent-subtle text-accent-text' : 'bg-surface-base text-text-base'}`}
        onPointerDown={event => {
          dragOffsetRef.current = {
            x: event.clientX - outlinePosition.x,
            y: event.clientY - outlinePosition.y,
          };
          setIsDragging(true);
        }}
      >
        <div
          class="pr-8 text-[11px] font-semibold uppercase tracking-wide text-text-base"
          style={{ color: 'var(--bky-color-text-base)' }}
        >
          {t('outline.title', 'Page Outline')}
        </div>
      </div>

      <div class="h-[calc(100%-41px)] overflow-y-auto p-2">
        {!document && <div class="p-3 text-sm text-text-faint">{t('outline.noDocument', 'No document loaded.')}</div>}
        {document && !rootNode && <div class="p-3 text-sm text-text-faint">{t('outline.documentUnavailable', 'Document tree unavailable.')}</div>}
        {document && rootNode && (
          <div class="space-y-1">
            <OutlineNodeRow
              node={rootNode}
              document={document}
              definitionMap={definitionMap}
              selectedId={selectedId}
              depth={0}
              onSelect={selectNode}
              collapsedNodes={collapsedNodes}
              onToggleCollapsed={nodeId => setCollapsedNodes(state => ({
                ...state,
                [nodeId]: !state[nodeId],
              }))}
            />
          </div>
        )}
      </div>
    </div>
  );
};

interface OutlineNodeRowProps {
  node: BuilderNode;
  document: BuilderDocument;
  definitionMap: Map<string, BlockDefinition>;
  selectedId: string | null;
  depth: number;
  onSelect: (id: string | null) => void;
  collapsedNodes: Record<string, boolean>;
  onToggleCollapsed: (id: string) => void;
  slotLabel?: string;
}

const OutlineNodeRow: FunctionComponent<OutlineNodeRowProps> = ({
  node,
  document,
  definitionMap,
  selectedId,
  depth,
  onSelect,
  collapsedNodes,
  onToggleCollapsed,
  slotLabel,
}) => {
  const definition = definitionMap.get(node.type);
  const label = definition ? labelFor(definition) : fallbackNodeLabel(node.type);
  const subtitle = nodePreviewLabel(node);
  const childEntries = Object.entries(node.slots).filter(([, children]) => (children ?? []).length > 0);
  const hasChildren = childEntries.length > 0;
  const isCollapsed = collapsedNodes[node.id] === true;
  const isSelected = selectedId === node.id;

  return (
    <div class="space-y-1">
      <div
        class={`flex w-full items-start gap-2 rounded-input px-2 py-2 transition-colors ${isSelected
          ? 'bg-accent-subtle text-accent-text'
          : 'text-text-muted hover:bg-surface-base hover:text-text-base'}`}
        style={{ paddingLeft: `${depth * 14 + 8}px` }}
      >
        <span class="mt-0.5 flex h-6 w-4 shrink-0 items-center justify-center">
          {hasChildren ? (
            <button
              type="button"
              aria-label={isCollapsed ? t('outline.expandChildren', 'Expand children') : t('outline.collapseChildren', 'Collapse children')}
              aria-expanded={!isCollapsed}
              class="flex h-4 w-4 items-center justify-center rounded-[4px] text-text-faint transition-colors hover:bg-surface-overlay hover:text-text-base"
              onClick={() => onToggleCollapsed(node.id)}
            >
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                {isCollapsed
                  ? <path d="M3 2l4 3-4 3" />
                  : <path d="M2 3l3 4 3-4" />}
              </svg>
            </button>
          ) : null}
        </span>
        <button
          type="button"
          onClick={() => onSelect(node.id)}
          class="flex min-w-0 flex-1 items-start gap-2 text-left"
        >
          <span class={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-input text-[11px] font-bold ${isSelected
            ? 'bg-accent-base text-text-on-accent'
            : 'bg-surface-base text-accent-base'}`}>
            {nodeGlyph(node.type)}
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
              <span class="truncate text-sm font-medium">{label}</span>
              {slotLabel && <span class="rounded-badge border border-border-subtle bg-surface-base px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-text-muted">{slotLabel}</span>}
            </span>
            {subtitle && <span class="mt-0.5 block truncate text-xs opacity-80">{subtitle}</span>}
          </span>
        </button>
      </div>

      {hasChildren && !isCollapsed && (
        <div class="space-y-1">
          {childEntries.map(([slotName, childIds]) =>
            (childIds ?? []).map(childId => {
              const childNode = document.nodes[childId];
              if (!childNode) return null;
              return (
                <OutlineNodeRow
                  key={childId}
                  node={childNode}
                  document={document}
                  definitionMap={definitionMap}
                  selectedId={selectedId}
                  depth={depth + 1}
                  onSelect={onSelect}
                  collapsedNodes={collapsedNodes}
                  onToggleCollapsed={onToggleCollapsed}
                  slotLabel={childEntries.length > 1 || slotName !== 'default' ? slotName : undefined}
                />
              );
            }),
          )}
        </div>
      )}
    </div>
  );
};

function labelFor(definition: BlockDefinition): string {
  if (definition.label) return definition.label;
  const slug = definition.type.split('/')[1] ?? definition.type;
  return slug.charAt(0).toUpperCase() + slug.slice(1);
}

function fallbackNodeLabel(type: string): string {
  const slug = type.split('/')[1] ?? type;
  return slug.charAt(0).toUpperCase() + slug.slice(1);
}

function nodePreviewLabel(node: BuilderNode): string {
  const candidates = [node.props['text'], node.props['label'], node.props['title'], node.props['content'], node.props['quote']]
    .filter((value): value is string => typeof value === 'string' && value.trim() !== '');
  if (candidates.length === 0) return node.id;
  return candidates[0]!.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function nodeGlyph(type: string): string {
  const icons: Record<string, string> = {
    'bky/heading': 'H',
    'bky/text': 'T',
    'bky/button': 'B',
    'bky/image': 'I',
    'bky/section': '§',
    'bky/rows': '≣',
    'bky/grid': '▦',
    'bky/container': '□',
    'bky/columns': '⊞',
    'bky/card': 'C',
    'bky/divider': '—',
    'bky/spacer': '↕',
    'bky/list': 'L',
    'bky/quote': 'Q',
    'bky/video': '▶',
    'bky/html': '</>',
    'bky/wp-post-title': 'WP',
    'bky/wp-post-content': 'WP',
    'bky/wp-featured-image': 'WP',
    'bky/wp-template-part': 'WP',
    'bky/wp-shortcode': '[…]',
    'bky/wp-hook': '⚡',
    'bky/theme-toggle': '🎨',
  };
  return icons[type] || type.split('/')[1]?.[0]?.toUpperCase() || '?';
}

function clampPosition(position: { x: number; y: number }): { x: number; y: number } {
  const maxX = Math.max(VIEWPORT_MARGIN, window.innerWidth - PANEL_WIDTH - VIEWPORT_MARGIN);
  const maxY = Math.max(VIEWPORT_MARGIN, window.innerHeight - PANEL_HEIGHT - VIEWPORT_MARGIN);
  return {
    x: Math.min(Math.max(VIEWPORT_MARGIN, position.x), maxX),
    y: Math.min(Math.max(VIEWPORT_MARGIN, position.y), maxY),
  };
}