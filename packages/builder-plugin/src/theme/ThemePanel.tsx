import type { FunctionComponent } from 'preact';
import { useMemo, useState } from 'preact/hooks';
import { useThemeStore } from '../store/theme';
import type { ThemeTokenGroup, ThemeTokenMeta, ThemeVariantSettings } from '../store/theme';
import { t } from '../i18n';

const VARIANT_IDS = ['default--light', 'default--dark'] as const;

export const ThemePanel: FunctionComponent = () => {
  const groups = useThemeStore(s => s.groups);
  const variants = useThemeStore(s => s.variants);
  const isLoading = useThemeStore(s => s.isLoading);
  const isSaving = useThemeStore(s => s.isSaving);
  const isDirty = useThemeStore(s => s.isDirty);
  const save = useThemeStore(s => s.save);
  const resetAll = useThemeStore(s => s.resetAll);
  const [variantId, setVariantId] = useState<(typeof VARIANT_IDS)[number]>('default--light');
  const [query, setQuery] = useState('');

  const activeVariant = variants[variantId];
  const normalizedQuery = query.trim().toLowerCase();

  const filteredGroups = useMemo(() => filterGroups(groups, normalizedQuery), [groups, normalizedQuery]);

  return (
    <div class="flex h-full flex-col">
      <div class="border-b border-border-subtle px-4 py-3">
        <div class="grid grid-cols-2 gap-1 rounded-input border border-border-subtle bg-surface-base p-1">
          {VARIANT_IDS.map(id => (
            <button
              key={id}
              type="button"
              onClick={() => setVariantId(id)}
              class={`rounded-input px-2 py-1.5 text-xs font-semibold transition-colors ${variantId === id
                ? 'bg-accent-base text-text-on-accent'
                : 'text-text-muted hover:bg-surface-overlay hover:text-text-base'}`}
            >
              {id === 'default--light' ? t('theme.light', 'Light') : t('theme.dark', 'Dark')}
            </button>
          ))}
        </div>

        <label class="mt-3 block">
          <span class="sr-only">{t('theme.searchTokens', 'Search tokens')}</span>
          <input
            type="search"
            value={query}
            onInput={event => setQuery((event.target as HTMLInputElement).value)}
            placeholder={t('theme.searchTokensPlaceholder', 'Search tokens…')}
            class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base
                   placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          />
        </label>
      </div>

      <div class="flex-1 overflow-y-auto p-3">
        {isLoading && <p class="px-2 py-4 text-sm text-text-faint">{t('theme.loadingTheme', 'Loading theme…')}</p>}
        {!isLoading && !activeVariant && <p class="px-2 py-4 text-sm text-text-faint">{t('theme.dataUnavailable', 'Theme data unavailable.')}</p>}

        {!isLoading && activeVariant && (
          <div class="space-y-3">
            {filteredGroups.map(group => (
              <ThemeTokenGroupSection
                key={group.id}
                group={group}
                variant={activeVariant}
                initiallyOpen={normalizedQuery !== '' || group.id === 'semantic-colors'}
              />
            ))}
          </div>
        )}
      </div>

      <div class="border-t border-border-subtle p-3">
        <div class="flex gap-2">
          <button
            type="button"
            onClick={resetAll}
            disabled={!isDirty || isSaving}
            class="rounded-button border border-border-base px-3 py-2 text-xs font-medium text-text-muted hover:border-feedback-danger hover:text-feedback-danger disabled:cursor-not-allowed disabled:opacity-50"
          >
            {t('theme.reset', 'Reset')}
          </button>
          <button
            type="button"
            onClick={() => void save()}
            disabled={!isDirty || isSaving}
            class="flex-1 rounded-button bg-accent-base px-3 py-2 text-xs font-semibold text-text-on-accent hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isSaving ? t('theme.saving', 'Saving…') : t('theme.saveTheme', 'Save Theme')}
          </button>
        </div>
      </div>
    </div>
  );
};

interface ThemeTokenGroupSectionProps {
  group:         ThemeTokenGroup;
  variant:       ThemeVariantSettings;
  initiallyOpen: boolean;
}

const ThemeTokenGroupSection: FunctionComponent<ThemeTokenGroupSectionProps> = ({ group, variant, initiallyOpen }) => (
  <details open={initiallyOpen} class="rounded-card border border-border-subtle bg-surface-base">
    <summary class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
      {group.label}
    </summary>
    <div class="space-y-3 border-t border-border-subtle p-3">
      {group.tokens.map(token => (
        <TokenEditorField key={`${variant.id}-${token.key}`} token={token} variant={variant} />
      ))}
    </div>
  </details>
);

interface TokenEditorFieldProps {
  token:   ThemeTokenMeta;
  variant: ThemeVariantSettings;
}

const TokenEditorField: FunctionComponent<TokenEditorFieldProps> = ({ token, variant }) => {
  const setToken = useThemeStore(s => s.setToken);
  const resetToken = useThemeStore(s => s.resetToken);
  const value = variant.values[token.key] ?? '';
  const defaultValue = variant.defaults[token.key] ?? '';
  const overridden = Object.prototype.hasOwnProperty.call(variant.overrides, token.key);

  return (
    <div class="space-y-1.5">
      <div class="flex items-center justify-between gap-2">
        <label class="min-w-0 text-xs font-medium text-text-muted" for={`${variant.id}-${token.key}`}>
          {token.label}
        </label>
        {overridden && (
          <button
            type="button"
            onClick={() => resetToken(variant.id, token.key)}
            class="shrink-0 text-xs font-medium text-accent-text hover:underline"
          >
            {t('theme.default', 'Default')}
          </button>
        )}
      </div>

      {token.type === 'color' ? (
        <div class="grid grid-cols-[44px_1fr] gap-2">
          <input
            id={`${variant.id}-${token.key}`}
            type="color"
            value={isHexColor(value) ? value : '#000000'}
            onInput={event => setToken(variant.id, token.key, (event.target as HTMLInputElement).value)}
            class="h-10 w-11 rounded-input border border-border-base bg-surface-elevated"
            aria-label={token.label}
          />
          <input
            type="text"
            value={value}
            onInput={event => setToken(variant.id, token.key, (event.target as HTMLInputElement).value)}
            class={`min-w-0 rounded-input border bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:outline-none ${overridden ? 'border-accent-base' : 'border-border-base focus:border-accent-base'}`}
            placeholder={defaultValue}
          />
        </div>
      ) : (
        <input
          id={`${variant.id}-${token.key}`}
          type="text"
          value={value}
          onInput={event => setToken(variant.id, token.key, (event.target as HTMLInputElement).value)}
          class={`w-full rounded-input border bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:outline-none ${overridden ? 'border-accent-base' : 'border-border-base focus:border-accent-base'}`}
          placeholder={defaultValue}
        />
      )}

      <div class="flex items-center justify-between gap-2 text-[11px] text-text-faint">
        <span class="min-w-0 truncate">{token.cssVar}</span>
        <span class="shrink-0 truncate">{defaultValue}</span>
      </div>
    </div>
  );
};

function filterGroups(groups: ThemeTokenGroup[], query: string): ThemeTokenGroup[] {
  if (query === '') {
    return groups;
  }

  return groups
    .map(group => ({
      ...group,
      tokens: group.tokens.filter(token =>
        token.key.toLowerCase().includes(query)
        || token.label.toLowerCase().includes(query)
        || token.cssVar.toLowerCase().includes(query)
        || group.label.toLowerCase().includes(query),
      ),
    }))
    .filter(group => group.tokens.length > 0);
}

function isHexColor(value: string): boolean {
  return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(value);
}