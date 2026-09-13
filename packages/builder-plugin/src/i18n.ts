const STRINGS = window.BlockyBuilderConfig?.i18n ?? {};
const LOCALE = window.BlockyBuilderConfig?.locale ?? 'en-US';

export function t(
  key: string,
  fallback: string,
  replacements: Array<string | number> = []
): string {
  let value = STRINGS[key] ?? fallback;

  for (const replacement of replacements) {
    value = value.replace('%s', String(replacement));
  }

  return value;
}

export function builderLocale(): string {
  // WP hands PHP-style tags (en_US); Intl requires IETF (en-US).
  return (LOCALE || 'en-US').replace(/_/g, '-');
}
