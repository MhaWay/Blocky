import type { FunctionComponent } from 'preact';
import { useUiStore } from '../store/ui';
import { useDocumentStore } from '../store/document';
import { t } from '../i18n';

export const Footer: FunctionComponent = () => {
  const leftVisible      = useUiStore(s => s.leftPanelVisible);
  const rightVisible     = useUiStore(s => s.rightPanelVisible);
  const toggleLeft       = useUiStore(s => s.toggleLeftPanel);
  const toggleRight      = useUiStore(s => s.toggleRightPanel);
  const deviceMode       = useUiStore(s => s.deviceMode);
  const setDeviceMode    = useUiStore(s => s.setDeviceMode);
  const previewThemeMode = useUiStore(s => s.previewThemeMode);
  const togglePreviewThemeMode = useUiStore(s => s.togglePreviewThemeMode);
  const outlineVisible   = useUiStore(s => s.outlineVisible);
  const toggleOutline    = useUiStore(s => s.toggleOutline);

  const document         = useDocumentStore(s => s.document);
  const isDirty          = useDocumentStore(s => s.isDirty);
  const nodeCount        = document ? Math.max(0, Object.keys(document.nodes).length - 1) : 0;

  return (
    <footer
      class="flex h-9 items-center justify-between border-t border-border-subtle bg-surface-elevated px-3 text-xs text-text-muted"
      style={{ height: 'var(--builder-footer-height, 36px)' }}
    >
      {/* Left: panel toggles */}
      <div class="flex items-center gap-1">
        <PanelToggle
          active={leftVisible}
          onClick={toggleLeft}
          label={t('footer.toggleBlocksPanel', 'Toggle Blocks panel')}
          side="left"
        />
        <PanelToggle
          active={rightVisible}
          onClick={toggleRight}
          label={t('footer.toggleInspectorPanel', 'Toggle Inspector panel')}
          side="right"
        />
        <span class="ml-2 text-text-muted/70">
          {nodeCount === 1 ? t('footer.blockCount', '%s block', [nodeCount]) : t('footer.blocksCount', '%s blocks', [nodeCount])}
        </span>
      </div>

      {/* Center: device mode */}
      <div class="flex items-center gap-1">
        <DeviceBtn label={t('footer.desktop', 'Desktop')} mode="desktop" current={deviceMode} onClick={setDeviceMode} />
        <DeviceBtn label={t('footer.tablet', 'Tablet')}  mode="tablet"  current={deviceMode} onClick={setDeviceMode} />
        <DeviceBtn label={t('footer.mobile', 'Mobile')}  mode="mobile"  current={deviceMode} onClick={setDeviceMode} />
      </div>

      {/* Right: state */}
      <div class="flex items-center gap-3">
        <button
          type="button"
          onClick={toggleOutline}
          title={t('footer.toggleOutline', 'Toggle outline')}
          aria-label={t('footer.toggleOutline', 'Toggle outline')}
          aria-pressed={outlineVisible}
          class={
            'flex h-7 items-center gap-1 rounded-input px-2 transition-colors ' +
            (outlineVisible
              ? 'bg-accent-subtle text-accent-text'
              : 'text-text-muted hover:bg-surface-base hover:text-text-base')
          }
        >
          <span aria-hidden="true">☰</span>
          <span class="hidden sm:inline">{t('footer.outline', 'Outline')}</span>
        </button>
        <button
          type="button"
          onClick={togglePreviewThemeMode}
          title={t('footer.togglePreviewTheme', 'Toggle preview theme')}
          aria-label={t('footer.togglePreviewTheme', 'Toggle preview theme')}
          aria-pressed={previewThemeMode === 'dark'}
          class={
            'flex h-7 items-center gap-1 rounded-input px-2 transition-colors ' +
            (previewThemeMode === 'dark'
              ? 'bg-accent-subtle text-accent-text'
              : 'text-text-muted hover:bg-surface-base hover:text-text-base')
          }
        >
          <span aria-hidden="true">{previewThemeMode === 'dark' ? '🌙' : '☀️'}</span>
          <span class="hidden sm:inline">{previewThemeMode === 'dark' ? t('footer.previewDark', 'Preview Dark') : t('footer.previewLight', 'Preview Light')}</span>
        </button>
        <span class={isDirty ? 'text-accent-text' : ''}>
          {isDirty ? t('toolbar.unsavedChanges', 'Unsaved changes') : document ? t('footer.saved', 'Saved') : t('footer.noDocument', 'No document')}
        </span>
      </div>
    </footer>
  );
};

interface PanelToggleProps {
  active:  boolean;
  onClick: () => void;
  label:   string;
  side:    'left' | 'right';
}

const PanelToggle: FunctionComponent<PanelToggleProps> = ({ active, onClick, label, side }) => {
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      aria-label={label}
      aria-pressed={active}
      class={
        'flex h-7 items-center justify-center rounded-input px-2 transition-colors ' +
        (active
          ? 'bg-accent-subtle text-accent-text'
          : 'text-text-muted hover:bg-surface-base hover:text-text-base')
      }
    >
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="1.5" y="2.5" width="13" height="11" rx="1" />
        {side === 'left'
          ? <line x1="5.5" y1="2.5" x2="5.5" y2="13.5" />
          : <line x1="10.5" y1="2.5" x2="10.5" y2="13.5" />}
        {active && (side === 'left'
          ? <rect x="1.5" y="2.5" width="4" height="11" fill="currentColor" opacity="0.25" />
          : <rect x="10.5" y="2.5" width="4" height="11" fill="currentColor" opacity="0.25" />)}
      </svg>
    </button>
  );
};

interface DeviceBtnProps {
  mode:    'desktop' | 'tablet' | 'mobile';
  current: 'desktop' | 'tablet' | 'mobile';
  label:   string;
  onClick: (mode: 'desktop' | 'tablet' | 'mobile') => void;
}

const DeviceBtn: FunctionComponent<DeviceBtnProps> = ({ mode, current, label, onClick }) => {
  const active = mode === current;
  return (
    <button
      type="button"
      onClick={() => onClick(mode)}
      title={label}
      aria-label={label}
      aria-pressed={active}
      class={
        'flex h-7 items-center justify-center rounded-input px-2 transition-colors ' +
        (active
          ? 'bg-accent-subtle text-accent-text'
          : 'text-text-muted hover:bg-surface-base hover:text-text-base')
      }
    >
      <DeviceIcon mode={mode} />
    </button>
  );
};

const DeviceIcon: FunctionComponent<{ mode: 'desktop' | 'tablet' | 'mobile' }> = ({ mode }) => {
  if (mode === 'desktop') {
    return (
      <svg width="16" height="14" viewBox="0 0 16 14" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="1.5" y="1.5" width="13" height="8.5" rx="1" />
        <line x1="5" y1="13" x2="11" y2="13" />
        <line x1="8" y1="10" x2="8" y2="13" />
      </svg>
    );
  }
  if (mode === 'tablet') {
    return (
      <svg width="12" height="14" viewBox="0 0 12 14" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="1.5" y="1" width="9" height="12" rx="1.2" />
        <circle cx="6" cy="11.5" r="0.4" fill="currentColor" />
      </svg>
    );
  }
  return (
    <svg width="9" height="14" viewBox="0 0 9 14" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="1" y="1" width="7" height="12" rx="1.2" />
      <circle cx="4.5" cy="11.5" r="0.4" fill="currentColor" />
    </svg>
  );
};
