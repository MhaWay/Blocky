import { useState } from 'preact/hooks';
import type { FunctionComponent } from 'preact';
import { t } from '../i18n';

/*
 * Closed-set icon picker: bundled Lucide set (ISC license) + user SVG uploads
 * sanitized server-side. Values are tokens: lucide-{name}, upload-{id}, or a
 * legacy glyph string.
 */

interface Props {
  value: string;
  label: string;
  onChange: (token: string) => void;
}

const API_BASE = window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/';
const NONCE = window.BlockyBuilderConfig?.nonce ?? '';

let iconCache: Record<string, string> | null = null;

async function loadIcons(): Promise<Record<string, string>> {
  if (iconCache) return iconCache;
  const res = await fetch(API_BASE + 'icons', {
    credentials: 'same-origin',
    headers: { 'X-WP-Nonce': NONCE },
  });
  const data = (await res.json()) as { icons?: Record<string, string> };
  iconCache = data.icons ?? {};
  return iconCache;
}

function svgFor(token: string, icons: Record<string, string> | null): string | null {
  if (!token) return null;
  if (token.startsWith('lucide-')) {
    const inner = icons?.[token.slice(7)];
    return inner
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em">' +
          inner +
          '</svg>'
      : null;
  }
  if (token.startsWith('upload-')) return null;
  return null;
}

export const IconPicker: FunctionComponent<Props> = ({ value, label, onChange }) => {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [icons, setIcons] = useState<Record<string, string> | null>(iconCache);
  const [glyphMode, setGlyphMode] = useState(
    !value.startsWith('lucide-') && !value.startsWith('upload-') && value !== '' && value !== '★'
  );
  const [uploadError, setUploadError] = useState<false | 'rejected' | 'failed'>(false);

  const openPicker = (): void => {
    setOpen((o) => !o);
    if (!iconCache) {
      void loadIcons().then(setIcons);
    }
  };

  const names = icons
    ? Object.keys(icons)
        .filter((n) => query === '' || n.includes(query.toLowerCase()))
        .slice(0, 200)
    : [];

  const upload = (file: File | undefined): void => {
    if (!file) return;
    setUploadError(false);
    void file
      .text()
      .then(async (svg) => {
        const res = await fetch(API_BASE + 'icons/upload', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({ svg, name: file.name.replace(/\.svg$/i, '') }),
        });
        const data = (await res.json()) as { token?: string; code?: string };
        if (data.token) {
          onChange(data.token);
          setOpen(false);
        } else {
          setUploadError(data.code === 'blocky_invalid_svg' ? 'rejected' : 'failed');
        }
      })
      .catch(() => setUploadError('failed'));
  };

  const preview = svgFor(value, icons);
  const label0 = label.replace(/^\w/, (c) => c.toUpperCase());

  return (
    <div class="relative">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-medium text-text-muted">{label0}</span>
        <button
          type="button"
          class="flex items-center gap-2 rounded-button border border-border-base px-2 py-1 text-xs"
          onClick={openPicker}
        >
          <span class="inline-flex h-4 w-4 items-center justify-center text-sm">
            {preview ? (
              <span class="leading-none" dangerouslySetInnerHTML={{ __html: preview }} />
            ) : value.startsWith('upload-') ? (
              <span class="leading-none">◆</span>
            ) : (
              <span class="leading-none">{value || '★'}</span>
            )}
          </span>
          <span class="max-w-24 truncate text-text-muted">{value || '★'}</span>
        </button>
      </div>
      {open && (
        <div class="absolute right-0 z-30 mt-1 w-80 rounded-card border border-border-base bg-surface-base p-2 shadow-md">
          <input
            type="text"
            class="mb-2 w-full rounded-button border border-border-base bg-surface-base px-2 py-1 text-xs"
            placeholder={t('icons.search', 'Search icons…')}
            value={query}
            onInput={(e) => setQuery((e.target as HTMLInputElement).value)}
          />
          {!glyphMode ? (
            <>
              <div class="grid max-h-56 grid-cols-8 gap-1 overflow-auto">
                {names.map((n) => (
                  <button
                    type="button"
                    key={n}
                    title={n}
                    class="flex h-8 items-center justify-center rounded-button hover:bg-surface-hover"
                    onClick={() => {
                      onChange('lucide-' + n);
                      setOpen(false);
                    }}
                    dangerouslySetInnerHTML={{ __html: svgFor('lucide-' + n, icons) ?? '' }}
                  />
                ))}
              </div>
              <div class="mt-2 flex items-center justify-between text-xs">
                <label class="cursor-pointer text-accent-base">
                  {t('icons.upload', 'Upload SVG')}
                  <input
                    type="file"
                    accept=".svg,image/svg+xml"
                    class="hidden"
                    onChange={(e) => upload((e.target as HTMLInputElement).files?.[0])}
                  />
                </label>
                <button
                  type="button"
                  class="text-text-muted hover:underline"
                  onClick={() => setGlyphMode(true)}
                >
                  {t('icons.useGlyph', 'Use glyph')}
                </button>
              </div>
              {uploadError && (
                <p class="mt-1 text-xs text-feedback-danger" role="alert">
                  {uploadError === 'rejected'
                    ? t('icons.uploadRejected', 'The SVG was rejected (scripts or unsafe attributes).')
                    : t('icons.uploadFailed', 'The icon upload failed.')}
                </p>
              )}
            </>
          ) : (
            <div class="flex items-center gap-2">
              <input
                type="text"
                class="w-20 rounded-button border border-border-base px-2 py-1 text-center text-sm"
                value={value}
                maxlength={2}
                onInput={(e) => onChange((e.target as HTMLInputElement).value)}
              />
              <button
                type="button"
                class="text-text-muted hover:underline"
                onClick={() => setGlyphMode(false)}
              >
                {t('icons.useSet', 'Icon set')}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};
