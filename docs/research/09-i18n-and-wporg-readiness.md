# 09 — Internationalization and WordPress.org readiness

## Catalogs shipped

- Source of truth: every UI string already flows through `__()`/`self::tr()` (text domain
  `blocky`); `languages/blocky.pot` is the extracted template (578 strings).
- Machine-quality starter catalogs with human review needed:
  Italian (it_IT), German (de_DE), Spanish (es_ES), French (fr_FR), Brazilian Portuguese (pt_BR).
  Each is a plain `.po` + generated `.mo` (little-endian, no fuzzy entries), duplicated in
  both plugins because each plugin calls `load_plugin_textdomain('blocky')`.
- Untranslated technical identifiers (slugs, css fragments, query params) are intentionally
  left empty → WordPress falls back to the English source.
- Regenerating or adding a language = add `/tmp/lang/xx.json`-style dictionary and rebuild
  po/mo; no code changes. Long term: connect the plugin to translate.wordpress.org after
  the directory listing and drop the shipped catalogs in favor of GlotPress packs.

## Why many languages

- The distribution channel is wordpress.org: installs are global from day one; the top non-
  English markets (PT-BR, DE, ES, FR, IT) are the majority of new installs.
- The WordPress.org review process and directory ranking favor plugins with active i18n;
  community translations only become possible once the plugin is listed.

## WordPress.org readiness added

- `readme.txt` per plugin (short/long description, FAQ, changelog, stable tag 0.1.0).
- `LICENSE` (GPL-2.0+) per plugin — required for the directory and compatible with WP.
- No external service calls at runtime (no fonts, no telemetry) — already true by design.
- Note: the product ships as two plugins (core + builder). For the directory we will either
  publish one bundle that requires the other, or list both and cross-link them. Decision
  before submission; `blocky-core` must never be installed alone.
