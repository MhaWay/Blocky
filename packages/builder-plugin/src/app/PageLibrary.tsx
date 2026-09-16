import type { FunctionComponent } from 'preact';
import { useEffect, useMemo, useState } from 'preact/hooks';
import {
  type BuilderPageRecord,
  type CreatePostOptions,
  useDocumentStore,
} from '../store/document';
import { builderLocale, t } from '../i18n';

const PAGE_STARTERS: Array<{
  id: NonNullable<CreatePostOptions['starter']>;
  label: string;
  description: string;
  icon: string;
  title: string;
}> = [
  {
    id: 'page',
    label: t('library.pageStarterLabel', 'Page'),
    description: t('library.pageStarterDescription', 'A standard page to build freely.'),
    icon: 'P',
    title: t('library.pageStarterTitle', 'New GG Page'),
  },
  {
    id: 'landing',
    label: t('library.landingStarterLabel', 'Landing'),
    description: t(
      'library.landingStarterDescription',
      'Hero, central content, and a call to action.'
    ),
    icon: 'L',
    title: t('library.landingStarterTitle', 'Landing Page'),
  },
];

const TEMPLATE_STARTERS: Array<{
  id: NonNullable<CreatePostOptions['starter']>;
  label: string;
  description: string;
  icon: string;
  title: string;
}> = [
  {
    id: 'base-template',
    label: t('library.baseTemplateLabel', 'Base Template'),
    description: t(
      'library.baseTemplateDescription',
      'Primary template with header area, dynamic page content, and footer.'
    ),
    icon: 'T',
    title: t('library.baseTemplateTitle', 'Base Template'),
  },
  {
    id: 'header',
    label: t('library.headerStarterLabel', 'Header'),
    description: t(
      'library.headerStarterDescription',
      'Starter for a top bar with branding and navigation.'
    ),
    icon: 'H',
    title: t('library.headerStarterTitle', 'Header Layout'),
  },
  {
    id: 'footer',
    label: t('library.footerStarterLabel', 'Footer'),
    description: t(
      'library.footerStarterDescription',
      'Starter for a footer with links and notes.'
    ),
    icon: 'F',
    title: t('library.footerStarterTitle', 'Footer Layout'),
  },
  {
    id: 'menu',
    label: t('library.menuStarterLabel', 'Menu'),
    description: t('library.menuStarterDescription', 'Quick navigation with pre-aligned items.'),
    icon: 'M',
    title: t('library.menuStarterTitle', 'Navigation Menu'),
  },
  {
    id: 'sidebar',
    label: t('library.sidebarStarterLabel', 'Sidebar'),
    description: t(
      'library.sidebarStarterDescription',
      'Layout with main content and a side column.'
    ),
    icon: 'S',
    title: t('library.sidebarStarterTitle', 'Sidebar Layout'),
  },
  {
    id: 'single-post',
    label: t('library.singlePostLabel', 'Single Post'),
    description: t(
      'library.singlePostDescription',
      'Article template: header slot, title, content, author, and post navigation.'
    ),
    icon: 'A',
    title: t('library.singlePostTitle', 'Article Template'),
  },
  {
    id: 'component',
    label: t('library.componentLabel', 'Component'),
    description: t(
      'library.componentDescription',
      'Structure for one item of a grid, list or loop (card, product tile...).'
    ),
    icon: 'C',
    title: t('library.componentTitle', 'New Component'),
  },
];

export const PageLibrary: FunctionComponent = () => {
  const pages = useDocumentStore((s) => s.pages);
  const isLoadingPages = useDocumentStore((s) => s.isLoadingPages);
  const createPost = useDocumentStore((s) => s.createPost);
  const deletePage = useDocumentStore((s) => s.deletePage);
  const loadPost = useDocumentStore((s) => s.loadPost);
  const loadPageLibrary = useDocumentStore((s) => s.loadPageLibrary);
  const [query, setQuery] = useState('');
  const [contextMenu, setContextMenu] = useState<{
    page: BuilderPageRecord;
    x: number;
    y: number;
  } | null>(null);

  useEffect(() => {
    void loadPageLibrary();
  }, [loadPageLibrary]);

  useEffect(() => {
    if (!contextMenu) return;

    const closeMenu = (event?: Event) => {
      const target = event?.target;
      if (target instanceof Element && target.closest('[data-bky-page-context-menu="true"]')) {
        return;
      }
      setContextMenu(null);
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setContextMenu(null);
    };

    window.addEventListener('mousedown', closeMenu);
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('keydown', handleEscape);

    return () => {
      window.removeEventListener('mousedown', closeMenu);
      window.removeEventListener('scroll', closeMenu, true);
      window.removeEventListener('resize', closeMenu);
      window.removeEventListener('keydown', handleEscape);
    };
  }, [contextMenu]);

  const filteredPages = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return pages;

    return pages.filter((page) => {
      const haystack = [page.title, page.status, page.type].join(' ').toLowerCase();
      return haystack.includes(normalizedQuery);
    });
  }, [pages, query]);

  return (
    <div class="mx-auto flex h-full w-full max-w-7xl flex-col gap-8 px-6 py-8">
      <section class="rounded-card border border-border-subtle bg-surface-elevated p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">
              {t('library.create', 'Create')}
            </p>
            <h2 class="mt-2 font-display text-2xl font-bold text-text-base">
              {t('library.newLayout', 'New Layout')}
            </h2>
            <p class="mt-2 max-w-2xl text-sm text-text-muted">
              {t(
                'library.newLayoutDescription',
                'Choose the type of page to generate. Each card creates a new draft page with a different starter, ready to refine in GG.'
              )}
            </p>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {PAGE_STARTERS.map((template) => (
            <button
              key={template.id}
              type="button"
              onClick={() => void createPost({ starter: template.id, title: template.title })}
              class="group flex min-h-40 flex-col items-start gap-3 rounded-card border border-border-subtle bg-surface-base p-4 text-left transition-colors hover:border-accent-base hover:bg-accent-subtle"
            >
              <span class="flex h-10 w-10 items-center justify-center rounded-input bg-surface-elevated text-sm font-bold text-accent-base transition-colors group-hover:bg-accent-base group-hover:text-text-on-accent">
                {template.icon}
              </span>
              <div>
                <h3 class="text-base font-semibold text-text-base">{template.label}</h3>
                <p class="mt-1 text-sm text-text-muted">{template.description}</p>
              </div>
            </button>
          ))}
        </div>
      </section>

      <section class="rounded-card border border-border-subtle bg-surface-elevated p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">
              {t('library.templates', 'Templates')}
            </p>
            <h2 class="mt-2 font-display text-2xl font-bold text-text-base">
              {t('library.newTemplates', 'New Templates')}
            </h2>
            <p class="mt-2 max-w-2xl text-sm text-text-muted">
              {t(
                'library.newTemplatesDescription',
                'Create reusable parts or a full base template to connect to pages through GG WordPress blocks.'
              )}
            </p>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {TEMPLATE_STARTERS.map((template) => (
            <button
              key={template.id}
              type="button"
              onClick={() => void createPost({ starter: template.id, title: template.title })}
              class="group flex min-h-40 flex-col items-start gap-3 rounded-card border border-border-subtle bg-surface-base p-4 text-left transition-colors hover:border-accent-base hover:bg-accent-subtle"
            >
              <span class="flex h-10 w-10 items-center justify-center rounded-input bg-surface-elevated text-sm font-bold text-accent-base transition-colors group-hover:bg-accent-base group-hover:text-text-on-accent">
                {template.icon}
              </span>
              <div>
                <h3 class="text-base font-semibold text-text-base">{template.label}</h3>
                <p class="mt-1 text-sm text-text-muted">{template.description}</p>
              </div>
            </button>
          ))}
        </div>
      </section>

      <section class="flex flex-1 flex-col rounded-card border border-border-subtle bg-surface-elevated p-6 shadow-sm">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">Library</p>
            <h2 class="mt-2 font-display text-2xl font-bold text-text-base">
              {t('library.allPages', 'All Pages')}
            </h2>
          </div>
          <label class="w-full max-w-sm">
            <span class="sr-only">{t('library.searchPages', 'Search pages')}</span>
            <input
              type="search"
              value={query}
              onInput={(event) => setQuery((event.target as HTMLInputElement).value)}
              placeholder={t('library.searchPagesPlaceholder', 'Search by name or status…')}
              class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
            />
          </label>
        </div>

        {isLoadingPages ? (
          <div class="flex flex-1 items-center justify-center text-sm text-text-muted">
            {t('library.loadingPages', 'Loading pages…')}
          </div>
        ) : filteredPages.length === 0 ? (
          <div class="flex flex-1 flex-col items-center justify-center gap-2 text-center text-text-muted">
            <p class="text-base font-semibold text-text-base">
              {t('library.noPagesFound', 'No pages found')}
            </p>
            <p class="text-sm">
              {t(
                'library.noPagesFoundDescription',
                'Create a new page from one of the starters above.'
              )}
            </p>
          </div>
        ) : (
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {filteredPages.map((page) => (
              <PageCard
                key={page.id}
                page={page}
                onOpen={loadPost}
                onContextMenu={setContextMenu}
              />
            ))}
          </div>
        )}
      </section>

      {contextMenu && (
        <PageContextMenu
          page={contextMenu.page}
          x={contextMenu.x}
          y={contextMenu.y}
          onClose={() => setContextMenu(null)}
          onOpen={loadPost}
          onDelete={deletePage}
        />
      )}
    </div>
  );
};

interface PageCardProps {
  page: BuilderPageRecord;
  onOpen: (postId: number) => Promise<void>;
  onContextMenu: (menu: { page: BuilderPageRecord; x: number; y: number } | null) => void;
}

const PageCard: FunctionComponent<PageCardProps> = ({ page, onOpen, onContextMenu }) => (
  <button
    type="button"
    onClick={() => void onOpen(page.id)}
    onContextMenu={(event) => {
      event.preventDefault();
      event.stopPropagation();
      onContextMenu({ page, x: event.clientX, y: event.clientY });
    }}
    class="group flex min-h-44 flex-col items-start gap-4 rounded-card border border-border-subtle bg-surface-base p-4 text-left transition-colors hover:border-accent-base hover:bg-surface-overlay"
  >
    <div class="flex w-full items-start justify-between gap-3">
      <span class="flex h-10 w-10 items-center justify-center rounded-input bg-surface-elevated text-sm font-bold text-accent-base">
        {page.hasDocument ? 'BK' : 'PG'}
      </span>
      <span
        class={`rounded-badge px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${statusClasses(page.status)}`}
      >
        {page.status}
      </span>
    </div>

    <div class="flex-1">
      <h3 class="line-clamp-2 text-base font-semibold text-text-base">{page.title}</h3>
      <p class="mt-1 text-sm text-text-muted">
        {page.hasDocument
          ? t('library.containsBlockyDocument', 'Contains a GG document')
          : t('library.noBlockyDocument', 'WordPress page without a saved GG document')}
      </p>
    </div>

    <div class="flex w-full items-center justify-between gap-3 text-xs text-text-faint">
      <span>{formatModified(page.modified)}</span>
      <span class="font-semibold text-accent-text transition-transform group-hover:translate-x-0.5">
        {t('library.open', 'Open')}
      </span>
    </div>
  </button>
);

interface PageContextMenuProps {
  page: BuilderPageRecord;
  x: number;
  y: number;
  onClose: () => void;
  onOpen: (postId: number) => Promise<void>;
  onDelete: (postId: number) => Promise<void>;
}

const PageContextMenu: FunctionComponent<PageContextMenuProps> = ({
  page,
  x,
  y,
  onClose,
  onOpen,
  onDelete,
}) => {
  const actions = [
    {
      id: 'open',
      label: t('library.openInBlocky', 'Open in GG'),
      action: () => {
        onClose();
        void onOpen(page.id);
      },
    },
    {
      id: 'view',
      label: t('library.viewPage', 'View Page'),
      disabled: page.link === '',
      action: () => {
        onClose();
        if (page.link) window.open(page.link, '_blank', 'noopener,noreferrer');
      },
    },
    {
      id: 'classic',
      label: t('library.openClassicEditor', 'Open Classic Editor'),
      action: () => {
        onClose();
        window.location.href = `/wp-admin/post.php?post=${page.id}&action=edit`;
      },
    },
    {
      id: 'delete',
      label: t('library.moveToTrash', 'Move to Trash'),
      danger: true,
      action: () => {
        onClose();
        if (!window.confirm(t('library.confirmTrash', 'Move "%s" to the trash?', [page.title])))
          return;
        void onDelete(page.id);
      },
    },
  ];

  return (
    <div
      data-bky-page-context-menu="true"
      class="fixed z-100001 min-w-56 overflow-hidden rounded-card border border-border-strong bg-surface-overlay p-1 shadow-xl"
      style={{ left: `${x}px`, top: `${y}px` }}
      onContextMenu={(event) => event.preventDefault()}
    >
      <div class="border-b border-border-subtle px-3 py-2">
        <p class="truncate text-sm font-semibold text-text-base">{page.title}</p>
        <p class="mt-0.5 text-xs uppercase tracking-wide text-text-faint">
          {t('library.contextMenuHint', 'Right click: page actions')}
        </p>
      </div>

      <div class="grid gap-1 p-1">
        {actions.map((action) => (
          <button
            key={action.id}
            type="button"
            disabled={action.disabled}
            onClick={action.action}
            class={`rounded-input px-3 py-2 text-left text-sm transition-colors ${
              action.danger
                ? 'text-feedback-danger hover:bg-feedback-danger/12 disabled:text-text-faint disabled:hover:bg-transparent'
                : 'text-text-base hover:bg-surface-elevated disabled:text-text-faint disabled:hover:bg-transparent'
            }`}
          >
            {action.label}
          </button>
        ))}
      </div>
    </div>
  );
};

function statusClasses(status: string): string {
  if (status === 'publish') return 'bg-feedback-success/12 text-feedback-success';
  if (status === 'draft') return 'bg-accent-subtle text-accent-text';
  if (status === 'private') return 'bg-surface-overlay text-text-muted';
  return 'bg-feedback-warning/12 text-feedback-warning';
}

function formatModified(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return t('library.recentlyModified', 'Recently updated');
  return t('library.updatedOn', 'Updated %s', [
    date.toLocaleDateString(builderLocale(), { day: '2-digit', month: 'short', year: 'numeric' }),
  ]);
}
