import type { FunctionComponent } from 'preact';
import { useEffect, useMemo, useState } from 'preact/hooks';
import { type BuilderPageRecord, type PageStarterId, useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import { builderLocale, t } from '../i18n';

const TEMPLATE_KINDS: { id: PageStarterId; label: string }[] = [
  { id: 'base-template', label: t('templates.kindBase', 'Base') },
  { id: 'header', label: t('templates.kindHeader', 'Header') },
  { id: 'footer', label: t('templates.kindFooter', 'Footer') },
  { id: 'menu', label: t('templates.kindMenu', 'Menu') },
  { id: 'sidebar', label: t('templates.kindSidebar', 'Sidebar') },
  { id: 'single-post', label: t('templates.kindArticle', 'Article') },
  { id: 'component', label: t('templates.kindComponent', 'Component') },
];

const KIND_LABELS: Record<string, string> = Object.fromEntries(
  TEMPLATE_KINDS.map((kind) => [kind.id, kind.label])
);

/**
 * Sidebar 'Templates' panel: manages reusable template documents (header,
 * footer, menus, sidebars, base and single-post templates). Opening one
 * loads it into the editor; creating one picks the starter and opens it.
 */
export const TemplatesPanel: FunctionComponent = () => {
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

  const templates = useMemo(() => {
    const q = query.trim().toLowerCase();
    return pages.filter(
      (p) =>
        p.templateKind !== '' &&
        (!q || [p.title, p.templateKind].join(' ').toLowerCase().includes(q))
    );
  }, [pages, query]);

  const open = async (page: BuilderPageRecord) => {
    await loadPost(page.id);
    setActivePanel('blocks');
  };

  const create = async (starter: PageStarterId) => {
    await createPost({ starter });
    setActivePanel('blocks');
  };

  const trash = async (page: BuilderPageRecord) => {
    const ok = window.confirm(
      t('templates.trashConfirm', 'Move template "%s" to the trash?', [page.title])
    );
    if (!ok) return;
    await deletePage(page.id);
  };

  return (
    <div class="flex min-h-0 flex-1 flex-col">
      <div class="px-3 pt-3">
        <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">
          {t('sidebar.templates', 'Templates')}
        </h2>
        <p class="mt-1 text-[11px] leading-snug text-text-muted">
          {t('templates.description', 'Reusable parts and layouts. Click one to edit it.')}
        </p>
      </div>

      <div class="flex flex-wrap gap-1 px-3 pt-2">
        {TEMPLATE_KINDS.map((kind) => (
          <button
            key={kind.id}
            type="button"
            onClick={() => void create(kind.id)}
            class="rounded-badge border border-border-base bg-surface-overlay px-2 py-0.5 text-[11px] font-semibold text-text-base transition-colors hover:border-accent-base hover:text-accent-text"
          >
            + {kind.label}
          </button>
        ))}
      </div>

      <div class="px-3 pb-2 pt-2">
        <input
          type="search"
          value={query}
          onInput={(event) => setQuery((event.target as HTMLInputElement).value)}
          placeholder={t('templates.searchPlaceholder', 'Search templates…')}
          class="w-full rounded-input border border-border-base bg-surface-base px-2.5 py-1.5 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
        />
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
        {isLoadingPages && templates.length === 0 ? (
          <p class="px-2 py-4 text-sm text-text-muted">
            {t('templates.loading', 'Loading templates…')}
          </p>
        ) : templates.length === 0 ? (
          <div class="px-2 py-6 text-center">
            <p class="text-sm font-semibold text-text-base">
              {t('templates.empty', 'No templates yet')}
            </p>
            <p class="mt-1 text-xs text-text-muted">
              {t(
                'templates.emptyDescription',
                'Create a header, footer or article template above.'
              )}
            </p>
          </div>
        ) : (
          <ul class="flex flex-col gap-1">
            {templates.map((page) => (
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
                  title={t('templates.openHint', 'Open this template in the editor')}
                >
                  <span class="min-w-0 flex-1 truncate text-sm font-medium text-text-base">
                    {page.title}
                  </span>
                  <span class="shrink-0 rounded-badge bg-accent-base px-1.5 py-px text-[10px] font-bold text-text-on-accent">
                    {(KIND_LABELS[page.templateKind] ?? page.templateKind).toUpperCase()}
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
                        {t('templates.view', 'Preview')}
                      </a>
                    ) : null}
                    <button
                      type="button"
                      onClick={() => void trash(page)}
                      class="text-feedback-danger hover:underline"
                    >
                      {t('templates.trash', 'Trash')}
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
  if (Number.isNaN(date.getTime())) return t('templates.recentlyModified', 'Recently updated');
  return t('templates.updatedOn', 'Updated %s', [
    date.toLocaleDateString(builderLocale(), { day: '2-digit', month: 'short', year: 'numeric' }),
  ]);
}
