import type { FunctionComponent } from 'preact';
import { useEffect, useMemo, useState } from 'preact/hooks';
import { type BuilderPageRecord, useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import { builderLocale, t } from '../i18n';

/**
 * Sidebar 'Pages' panel: a compact WP-admin-style overview of every WP page.
 * Open any page (Blocky-built or plain WP - the document gets assigned to
 * Blocky on first save), create a new one, or trash it.
 */
export const PagesPanel: FunctionComponent = () => {
  const pages = useDocumentStore((s) => s.pages);
  const isLoadingPages = useDocumentStore((s) => s.isLoadingPages);
  const loadPageLibrary = useDocumentStore((s) => s.loadPageLibrary);
  const loadPost = useDocumentStore((s) => s.loadPost);
  const createPost = useDocumentStore((s) => s.createPost);
  const deletePage = useDocumentStore((s) => s.deletePage);
  const currentPostId = useDocumentStore((s) => s.postId);
  const setActivePanel = useUiStore((s) => s.setActivePanel);
  const [query, setQuery] = useState('');

  useEffect(() => {
    void loadPageLibrary();
  }, [loadPageLibrary]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    // Reusable templates live in their own Templates tab.
    const visible = pages.filter((p) => p.templateKind === '');
    if (!q) return visible;
    return visible.filter((p) => [p.title, p.status].join(' ').toLowerCase().includes(q));
  }, [pages, query]);

  const open = async (page: BuilderPageRecord) => {
    await loadPost(page.id);
    setActivePanel('blocks');
  };

  const add = async () => {
    await createPost({ starter: 'page' });
    setActivePanel('blocks');
  };

  const trash = async (page: BuilderPageRecord) => {
    const ok = window.confirm(t('pages.trashConfirm', 'Move "%s" to the trash?', [page.title]));
    if (!ok) return;
    await deletePage(page.id);
  };

  return (
    <div class="flex min-h-0 flex-1 flex-col">
      <div class="flex items-center justify-between gap-2 px-3 pt-3">
        <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">
          {t('sidebar.pages', 'Pages')}
        </h2>
        <button
          type="button"
          onClick={() => void add()}
          class="rounded-input bg-accent-base px-2.5 py-1 text-xs font-semibold text-text-on-accent transition-colors hover:bg-accent-strong"
        >
          {t('pages.add', 'Add Page')}
        </button>
      </div>

      <div class="px-3 pb-2 pt-2">
        <input
          type="search"
          value={query}
          onInput={(event) => setQuery((event.target as HTMLInputElement).value)}
          placeholder={t('pages.searchPlaceholder', 'Search pages…')}
          class="w-full rounded-input border border-border-base bg-surface-base px-2.5 py-1.5 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
        />
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
        {isLoadingPages && pages.length === 0 ? (
          <p class="px-2 py-4 text-sm text-text-muted">{t('pages.loading', 'Loading pages…')}</p>
        ) : filtered.length === 0 ? (
          <div class="px-2 py-6 text-center">
            <p class="text-sm font-semibold text-text-base">{t('pages.empty', 'No pages found')}</p>
            <p class="mt-1 text-xs text-text-muted">
              {t('pages.emptyDescription', 'Use Add Page to create one.')}
            </p>
          </div>
        ) : (
          <ul class="flex flex-col gap-1">
            {filtered.map((page) => (
              <li
                key={page.id}
                class={
                  'group rounded-input border px-2.5 py-2 transition-colors ' +
                  (page.id === currentPostId
                    ? 'border-accent-base bg-accent-subtle'
                    : 'border-transparent hover:border-border-base hover:bg-surface-overlay')
                }
              >
                <button
                  type="button"
                  onClick={() => void open(page)}
                  class="flex w-full items-center justify-between gap-2 text-left"
                  title={t('pages.openHint', 'Open this page in the editor')}
                >
                  <span class="min-w-0 flex-1 truncate text-sm font-medium text-text-base">
                    {page.title}
                  </span>
                  <span class="flex shrink-0 items-center gap-1.5">
                    {page.hasDocument ? (
                      <span
                        class="rounded-badge bg-accent-base px-1.5 py-px text-[10px] font-bold text-text-on-accent"
                        title={t('pages.builtWith', 'Built with Gennaker')}
                      >
                        BK
                      </span>
                    ) : null}
                    <span
                      class={
                        'rounded-badge px-1.5 py-px text-[10px] font-semibold uppercase ' +
                        (page.status === 'publish'
                          ? 'bg-feedback-success/12 text-feedback-success'
                          : 'bg-surface-overlay text-text-muted')
                      }
                    >
                      {page.status}
                    </span>
                  </span>
                </button>
                <div class="mt-1 flex items-center justify-between text-[11px] text-text-faint">
                  <span>{formatModified(page.modified)}</span>
                  <span class="hidden gap-2 group-hover:flex">
                    {page.link ? (
                      <a
                        href={page.link}
                        target="_blank"
                        rel="noreferrer"
                        class="text-accent-text hover:underline"
                      >
                        {t('pages.view', 'View')}
                      </a>
                    ) : null}
                    <button
                      type="button"
                      onClick={() => void trash(page)}
                      class="text-feedback-danger hover:underline"
                    >
                      {t('pages.trash', 'Trash')}
                    </button>
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

function formatModified(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return t('pages.recentlyModified', 'Recently updated');
  return t('pages.updatedOn', 'Updated %s', [
    date.toLocaleDateString(builderLocale(), { day: '2-digit', month: 'short', year: 'numeric' }),
  ]);
}
