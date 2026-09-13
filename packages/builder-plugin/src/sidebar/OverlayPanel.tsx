import type { FunctionComponent } from 'preact';
import { useMemo } from 'preact/hooks';
import { useBlockRegistry } from '../store/blockRegistry';
import { useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import { supportsManagedOverlayId } from '../overlays/identity';
import { t } from '../i18n';
import type { BuilderDocument, BuilderNode } from '../sdk/types';

// Overlays that open by their own nature (on load, on media click, on keys):
// they never need a trigger and must not warn.
const AUTOMATIC_BY_NATURE = new Set(['bky/cookie-banner', 'bky/lightbox', 'bky/command-palette']);
// Timed overlays count as covered when they carry an automatic open rule.
const TIMED_OVERLAY_TYPES = new Set(['bky/popup', 'bky/notification-toast']);

interface OverlayRow {
  node: BuilderNode;
  label: string;
  overlayId: string;
  covered: boolean;
}

function strProp(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

function hasOpenInteraction(node: BuilderNode, overlayId: string): boolean {
  const raw = node.props['interactions'];
  if (!Array.isArray(raw)) return false;

  return raw.some((entry) => {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return false;
    const record = entry as Record<string, unknown>;
    const action = strProp(record['action']);
    if (action !== 'overlay.open' && action !== 'overlay.toggle') return false;
    return strProp(record['target']) === overlayId;
  });
}

function hasAutomaticRule(node: BuilderNode): boolean {
  if (AUTOMATIC_BY_NATURE.has(node.type)) return true;
  if (!TIMED_OVERLAY_TYPES.has(node.type)) return false;

  const trigger = strProp(node.props['trigger']);
  if (trigger !== '' && trigger !== 'manual' && trigger !== 'click') return true;
  return typeof node.props['delay'] === 'number' && node.props['delay'] >= 0;
}

function buildRows(document: BuilderDocument, labelFor: (type: string) => string): OverlayRow[] {
  const nodes = Object.values(document.nodes);
  const overlays = nodes.filter((node) => supportsManagedOverlayId(node.type));

  return overlays.map((node) => {
    const overlayId = strProp(node.props['overlayId']);
    const covered =
      overlayId === ''
        ? false
        : nodes.some(
            (other) =>
              other.id !== node.id &&
              (strProp(other.props['targetOverlayId']) === overlayId ||
                hasOpenInteraction(other, overlayId))
          ) || hasAutomaticRule(node);

    return { node, label: labelFor(node.type), overlayId, covered };
  });
}

export const OverlayPanel: FunctionComponent = () => {
  const document = useDocumentStore((s) => s.document);
  const definitions = useBlockRegistry((s) => s.definitions);
  const previewOverlayNodeId = useUiStore((s) => s.previewOverlayNodeId);
  const setPreviewOverlay = useUiStore((s) => s.setPreviewOverlay);
  const selectNode = useUiStore((s) => s.selectNode);

  const rows = useMemo(() => {
    if (!document) return [];
    const labelFor = (type: string): string => {
      const def = definitions.find((candidate) => candidate.type === type);
      return def?.label ?? type.replace('bky/', '');
    };
    return buildRows(document, labelFor);
  }, [document, definitions]);

  if (rows.length === 0) return null;

  return (
    <section
      class="border-t border-border-subtle p-3"
      aria-label={t('sidebar.overlayCoverageAria', 'Overlay coverage')}
    >
      <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">
        {t('sidebar.overlayCoverageTitle', 'Overlay coverage')}
      </h3>
      <p class="mt-1 text-xs text-text-faint">
        {t(
          'sidebar.overlayCoverageDescription',
          'Select, preview, and validate trigger coverage for overlay blocks.'
        )}
      </p>
      <p class="mt-2 text-xs text-text-muted">
        <span class="font-semibold text-text-base">{String(rows.length)}</span>{' '}
        {t('sidebar.overlayCoverageCount', 'overlay blocks on this page.')}
      </p>
      {rows.map((row) => {
        const previewing = previewOverlayNodeId === row.node.id;
        const rowClasses = 'mt-2 rounded-input border border-border-base bg-surface-base px-2 py-2';
        const buttonClasses = previewing
          ? 'shrink-0 rounded-input border px-2 py-0.5 text-xs transition-colors border-accent-base bg-accent-subtle text-accent-text'
          : 'shrink-0 rounded-input border px-2 py-0.5 text-xs transition-colors border-border-base text-text-muted hover:border-accent-base hover:text-text-base';

        return (
          <div key={row.node.id} class={rowClasses}>
            <div class="flex items-center justify-between gap-2">
              <button
                type="button"
                class="truncate text-xs font-semibold text-text-base hover:text-accent-text"
                onClick={() => selectNode(row.node.id)}
                title={row.overlayId || undefined}
              >
                {row.label}
              </button>
              <button
                type="button"
                aria-pressed={previewing}
                class={buttonClasses}
                onClick={() => setPreviewOverlay(previewing ? null : row.node.id)}
              >
                {previewing
                  ? t('sidebar.overlayPreviewing', 'Previewing')
                  : t('sidebar.overlayPreview', 'Preview')}
              </button>
            </div>
            {!row.covered && (
              <p class="mt-1 text-xs text-text-muted">
                {t(
                  'sidebar.overlayNoTrigger',
                  'No manual trigger or automatic open rule detected for this overlay.'
                )}
              </p>
            )}
          </div>
        );
      })}
    </section>
  );
};
