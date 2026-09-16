import type { FunctionComponent } from 'preact';
import { useEffect, useLayoutEffect, useRef, useState } from 'preact/hooks';
import { redo, undo, useDocumentStore } from '../store/document';
import { t } from '../i18n';

const API_BASE = window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/';
const NONCE = window.BlockyBuilderConfig?.nonce ?? '';
const BUILDER_BRAND_COOKIE = 'bky_builder_brand';
const BUILDER_MODE_COOKIE = 'bky_builder_mode';

interface ThemeVariant {
  id: string;
  brand: string;
  mode: string;
  label?: string;
}

export const Toolbar: FunctionComponent = () => {
  const postId = useDocumentStore((s) => s.postId);
  const isDirty = useDocumentStore((s) => s.isDirty);
  const isSaving = useDocumentStore((s) => s.isSaving);
  const historyDepth = useDocumentStore((s) => s.historyDepth);
  const futureDepth = useDocumentStore((s) => s.futureDepth);
  const save = useDocumentStore((s) => s.save);
  const publishCurrentPost = useDocumentStore((s) => s.publishCurrentPost);
  const currentPost = useDocumentStore((s) => s.currentPost);
  const isPageLibraryOpen = useDocumentStore((s) => s.isPageLibraryOpen);
  const openPageLibrary = useDocumentStore((s) => s.openPageLibrary);
  const loadPageLibrary = useDocumentStore((s) => s.loadPageLibrary);

  return (
    <header
      class="flex h-12 items-center justify-between border-b border-border-subtle bg-surface-elevated px-4"
      style={{ height: 'var(--builder-toolbar-height)' }}
    >
      <div class="flex min-w-0 items-center gap-3">
        <a
          href="/wp-admin/"
          class="flex items-center gap-1 rounded-button px-2 py-1.5 text-sm text-text-muted hover:bg-surface-overlay hover:text-text-base"
          title={t('toolbar.backToWordPress', 'Back to WordPress')}
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M19 12H5M12 5l-7 7 7 7" />
          </svg>
          <span class="hidden sm:inline">WordPress</span>
        </a>
        <span class="font-display text-lg font-bold text-accent-text">GG</span>
        <button
          type="button"
          onClick={() => {
            openPageLibrary();
            void loadPageLibrary();
          }}
          class={`rounded-button px-3 py-1.5 text-sm transition-colors ${
            isPageLibraryOpen
              ? 'bg-accent-subtle text-accent-text'
              : 'text-text-muted hover:bg-surface-overlay hover:text-text-base'
          }`}
        >
          {t('toolbar.allPages', 'All Pages')}
        </button>
        {isPageLibraryOpen ? (
          <span class="truncate text-sm font-medium text-text-muted">
            {t('toolbar.selectPageOrCreateLayout', 'Select a page or create a new layout.')}
          </span>
        ) : currentPost ? (
          <>
            <span class="text-text-faint">/</span>
            <PageTitleEditor />
            <span class="rounded-badge bg-surface-overlay px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted">
              {currentPost.status}
            </span>
          </>
        ) : null}
        {isDirty && (
          <span class="rounded-badge bg-accent-subtle px-2 py-0.5 text-xs text-accent-text">
            {t('toolbar.unsavedChanges', 'Unsaved changes')}
          </span>
        )}
      </div>

      <div class="flex items-center gap-2">
        <ThemeSwitcher />
        {!isPageLibraryOpen && currentPost?.link && (
          <a
            href={currentPost.link}
            target="_blank"
            rel="noreferrer"
            class="rounded-button px-3 py-1.5 text-sm text-text-muted hover:text-text-base"
          >
            {t('toolbar.view', 'View')}
          </a>
        )}
        {!isPageLibraryOpen && postId != null && (
          <a
            href={`/wp-admin/post.php?post=${postId}&action=edit`}
            class="rounded-button px-3 py-1.5 text-sm text-text-muted hover:text-text-base"
          >
            {t('toolbar.classicEditor', 'Classic Editor')}
          </a>
        )}
        {!isPageLibraryOpen && postId != null && currentPost?.status !== 'publish' && (
          <button
            type="button"
            onClick={() => {
              void publishCurrentPost();
            }}
            class="rounded-button bg-feedback-success px-4 py-1.5 text-sm font-medium text-text-on-accent
                   hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {t('toolbar.publish', 'Publish')}
          </button>
        )}
        <button
          type="button"
          disabled={historyDepth === 0}
          onClick={() => {
            undo();
          }}
          class="rounded-button px-2 py-1.5 text-text-muted hover:bg-surface-overlay hover:text-text-base disabled:cursor-not-allowed disabled:opacity-40"
          title={t('toolbar.undo', 'Undo (Ctrl+Z)')}
          aria-label={t('toolbar.undoAria', 'Undo')}
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M3 7v6h6" />
            <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
          </svg>
        </button>
        <button
          type="button"
          disabled={futureDepth === 0}
          onClick={() => {
            redo();
          }}
          class="rounded-button px-2 py-1.5 text-text-muted hover:bg-surface-overlay hover:text-text-base disabled:cursor-not-allowed disabled:opacity-40"
          title={t('toolbar.redo', 'Redo (Ctrl+Shift+Z)')}
          aria-label={t('toolbar.redoAria', 'Redo')}
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M21 7v6h-6" />
            <path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13" />
          </svg>
        </button>
        {isSaving && <span class="text-xs text-text-muted">{t('toolbar.saving', 'Saving…')}</span>}
        <button
          type="button"
          disabled={isPageLibraryOpen || postId == null || !isDirty}
          onClick={() => {
            void save();
          }}
          class="rounded-button bg-accent-base px-4 py-1.5 text-sm font-medium text-text-on-accent
                 hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
        >
          {t('toolbar.save', 'Save')}
        </button>
      </div>
    </header>
  );
};

const PageTitleEditor: FunctionComponent = () => {
  const currentPost = useDocumentStore((s) => s.currentPost);
  const renameCurrentPost = useDocumentStore((s) => s.renameCurrentPost);
  const [draftTitle, setDraftTitle] = useState(currentPost?.title ?? '');

  useEffect(() => {
    setDraftTitle(currentPost?.title ?? '');
  }, [currentPost?.id, currentPost?.title]);

  if (!currentPost) return null;

  const submit = () => {
    const nextTitle = draftTitle.trim();
    if (nextTitle === '' || nextTitle === currentPost.title) {
      setDraftTitle(currentPost.title);
      return;
    }
    void renameCurrentPost(nextTitle);
  };

  return (
    <input
      type="text"
      value={draftTitle}
      onInput={(event) => setDraftTitle((event.target as HTMLInputElement).value)}
      onBlur={submit}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.currentTarget.blur();
        }
        if (event.key === 'Escape') {
          void setDraftTitle(currentPost.title);
          event.currentTarget.blur();
        }
      }}
      class="min-w-[220px] max-w-[360px] truncate rounded-input border border-transparent bg-surface-base px-3 py-1.5 text-sm font-medium text-text-base transition-colors focus:border-accent-base focus:outline-none"
      aria-label={t('toolbar.pageTitleAria', 'Page title')}
    />
  );
};

// ── Theme Switcher ──────────────────────────────────────────────────────────

const ThemeSwitcher: FunctionComponent = () => {
  const [open, setOpen] = useState(false);
  const [variants, setVariants] = useState<ThemeVariant[]>([]);
  const [brand, setBrand] = useState(() => cookieValue(BUILDER_BRAND_COOKIE) || 'default');
  const [mode, setMode] = useState<'light' | 'dark'>(() => readModeCookie());
  const containerRef = useRef<HTMLDivElement | null>(null);

  useLayoutEffect(() => {
    applyBuilderTheme(brand, mode);
  }, [brand, mode]);

  // Load theme list + active theme on mount
  useEffect(() => {
    let disposed = false;

    fetch(`${API_BASE}themes`, { headers: { 'X-WP-Nonce': NONCE } })
      .then((r) => (r.ok ? r.json() : []))
      .then((allVariants: ThemeVariant[]) => {
        if (disposed) return;

        if (Array.isArray(allVariants)) setVariants(allVariants);

        if (!cookieValue(BUILDER_BRAND_COOKIE)) {
          const availableBrands = [
            ...new Set((allVariants ?? []).map((variant) => variant.brand).filter(Boolean)),
          ];
          if (availableBrands.length > 0 && !availableBrands.includes(brand)) {
            setBrand(availableBrands[0]!);
          }
        }
      })
      .catch(() => {});

    return () => {
      disposed = true;
    };
  }, []);

  // Close popover on outside click
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const applyTheme = (nextBrand: string, nextMode: 'light' | 'dark') => {
    setCookie(BUILDER_BRAND_COOKIE, nextBrand);
    setCookie(BUILDER_MODE_COOKIE, nextMode);
    setBrand(nextBrand);
    setMode(nextMode);
  };

  const brands = [...new Set(variants.map((v) => v.brand))];
  const hasBrands = brands.length > 1;

  return (
    <div ref={containerRef} class="relative">
      <button
        type="button"
        title={t('toolbar.switchTheme', 'Switch theme')}
        onClick={() => setOpen((v) => !v)}
        class={`flex items-center gap-1.5 rounded-button px-3 py-1.5 text-sm transition-colors ${
          open ? 'bg-accent-subtle text-accent-text' : 'text-text-muted hover:text-text-base'
        }`}
      >
        <span aria-hidden="true" style="font-size:15px">
          🎨
        </span>
        <span class="hidden sm:inline">{brand}</span>
        <span class="ml-0.5 text-xs opacity-60">{mode === 'dark' ? '🌙' : '☀️'}</span>
      </button>

      {open && (
        <div class="absolute right-0 top-full z-50 mt-1 w-52 rounded-card border border-border-subtle bg-surface-elevated shadow-lg">
          <div class="p-3 space-y-3">
            {/* Light/Dark toggle */}
            <div>
              <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-text-muted">
                {t('toolbar.mode', 'Mode')}
              </p>
              <div class="flex gap-1.5">
                {(['light', 'dark'] as const).map((m) => (
                  <button
                    key={m}
                    type="button"
                    onClick={() => applyTheme(brand, m)}
                    class={`flex-1 rounded-input py-1.5 text-xs font-medium transition-colors ${
                      mode === m
                        ? 'bg-accent-base text-text-on-accent'
                        : 'border border-border-base text-text-muted hover:border-accent-base hover:text-text-base'
                    }`}
                  >
                    {m === 'light'
                      ? `☀️ ${t('toolbar.modeLight', 'Light')}`
                      : `🌙 ${t('toolbar.modeDark', 'Dark')}`}
                  </button>
                ))}
              </div>
            </div>

            {/* Brand selector (only if multiple brands) */}
            {hasBrands && (
              <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-text-muted">
                  {t('toolbar.brand', 'Brand')}
                </p>
                <div class="flex flex-col gap-1">
                  {brands.map((b) => (
                    <button
                      key={b}
                      type="button"
                      onClick={() => applyTheme(b, mode)}
                      class={`rounded-input px-3 py-1.5 text-left text-xs font-medium transition-colors ${
                        brand === b
                          ? 'bg-accent-base text-text-on-accent'
                          : 'border border-border-base text-text-muted hover:border-accent-base hover:text-text-base'
                      }`}
                    >
                      {b}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

function applyBuilderTheme(brand: string, mode: 'light' | 'dark'): void {
  const isDark = mode === 'dark';
  const roots = [
    document.documentElement,
    document.getElementById('blocky-builder-root'),
    document.querySelector('.blocky-builder-root'),
  ].filter((value): value is HTMLElement => value instanceof HTMLElement);

  roots.forEach((root) => {
    root.dataset.mode = mode;
    root.dataset.brand = brand;
    root.classList.toggle('dark', isDark);
    root.style.colorScheme = mode;
  });

  document.documentElement.style.colorScheme = mode;
}

function readModeCookie(): 'light' | 'dark' {
  return normalizeThemeMode(cookieValue(BUILDER_MODE_COOKIE)) ?? 'light';
}

function normalizeThemeMode(value: unknown): 'light' | 'dark' | null {
  return value === 'dark' || value === 'light' ? value : null;
}

function setCookie(name: string, value: string, days = 365): void {
  const expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
}

function cookieValue(name: string): string {
  const encodedName = `${encodeURIComponent(name)}=`;
  const entry = document.cookie
    .split(';')
    .map((value) => value.trim())
    .find((value) => value.startsWith(encodedName));

  if (!entry) return '';

  try {
    return decodeURIComponent(entry.slice(encodedName.length));
  } catch {
    return entry.slice(encodedName.length);
  }
}
