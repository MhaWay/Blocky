=== GG-Ally Page Builder ===
Contributors: ggallydotnet
Plugin URI: https://github.com/MhaWay/Blocky
Tags: page builder, tailwind, blocks, editor, static css
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Tailwind-native page builder. Customizable blocks, a fully translated interface, and a deterministic static-CSS compiler. No bloat.

== Description ==

GG-Ally channels the wind of Tailwind CSS into a fast, predictable page builder for WordPress.

**How it works**

* Every page you build is compiled to a single static CSS file with a content hash, served from the uploads directory. No inline styles, no runtime style injection, no database queries for CSS.
* The editor offers closed-set, token-driven controls (spacing, colors, typography, effects) instead of an infinity field of arbitrary CSS classes. This keeps the design system coherent and the generated CSS small.
* Frontend output is plain markup: zero JavaScript is loaded for layout blocks. Interactive blocks (carousels, tabs, accordions, forms) ship tiny, code-split ES modules that load only when actually present on the page.

**Highlights**

* Visual editor: drag & drop, inline text editing, device preview, undo/redo, layers and pages tabs.
* 13 layout and content blocks with more on the way: rows, columns, grids, flex, sections, cards, headings, text, images, buttons, forms, galleries, carousels, embeds.
* Theme system: design tokens (palette, type scale, spacing, radius) applied across every block, with light/dark variants.
* Entrance animations, sticky effects and scroll-driven motion, compiled to static CSS.
* Convert existing Gutenberg posts to GG-Ally pages with one click from the editor header.
* Built-in form submissions stored privately in WordPress.
* Fully translated interface: English, Italian, German, Spanish, French and Brazilian Portuguese.
* No Google Fonts by default — the system font stack or your local fonts only.

**Who is it for?**

Site builders and agencies who love Tailwind but need a visual editor their clients can use, and performance-minded developers who do not want a builder that ships megabytes of CSS and JS on every page.

== Installation ==

1. In your WordPress admin, go to Plugins > Add New > Upload Plugin.
2. Upload the ggally-page-builder zip file and activate it.
3. Click the GG-Ally menu in the admin sidebar, or open any post or page and choose "Edit with GG".

== Frequently Asked Questions ==

= Do I need to know Tailwind CSS? =

No. Tailwind is the engine under the hood; the interface uses plain-language, closed-set controls. Tailwind experts will feel at home because the vocabulary maps 1:1 to Tailwind utilities.

= Why does my site not load any frontend JavaScript? =

That is by design. Each page’s styling is compiled into one static, content-hashed CSS file served from the media uploads. Only interactive blocks load small JS modules, and only when they are actually on the page.

= Can I use arbitrary custom CSS classes? =

The normal control path intentionally prevents arbitrary classes to keep output maintainable. An expert "code mode" channel exists for advanced use with a rebuild step.

= How is the CSS compiled? =

The Tailwind CSS compiler is bundled as JavaScript and runs inside your own browser tab while you save in the editor. Nothing is ever downloaded or executed on your server; the compiled result is stored as a static CSS file in your media library and linked from the page.

= Does it work with any theme? =

Yes. GG-Ally renders its pages independently of the active theme, using its own block markup and compiled stylesheets.

= Which languages are included? =

English (source), Italian, German, Spanish, French, Brazilian Portuguese. The interface follows the user's WordPress profile language.

= Does the plugin call external services? =

GG-Ally is fully self-contained at runtime: no third-party CDN, no tracking, and the frontend never requests anything from us. The one network operation is opt-in: after activation, GG-Ally shows a Setup screen that explains and asks the administrator to confirm the one-time download of the official, version-pinned `tailwindcss` command-line compiler (v4.3.3). Until it is confirmed, the download never happens and the plugin keeps working with its bundled base stylesheet directly from its [official GitHub releases](https://github.com/tailwindlabs/tailwindcss/releases), verifies its published SHA-256 checksum, and caches it locally in `wp-content/blocky-engine/` (outside the web-served uploads folder). The binary is used only as a local build tool while the administrator saves or builds, never for site visitors, and the download happens once per server. This is the same compiler Tailwind Labs ships for everyone; pinning plus checksum verification means GG-Ally cannot be silently switched to different code.

== Screenshots ==

1. The GG-Ally editor: block library on the left, canvas in the middle, token-based inspector on the right.
2. Pages list with the GG-Ally badge identifying GG-Ally-built content.
3. "Edit with GG" button inside the WordPress editor to convert or open a document.
4. A published page rendered on the frontend, styled by the compiled static CSS.

== Changelog ==

= 0.1.0 =
* First public release.
* Visual editor with 13 core blocks, theme tokens, device preview, undo/redo.
* Static CSS compiler (content-hashed files, no inline styles).
* Entrance animations, sticky/scroll effects.
* Gutenberg conversion, form submissions, i18n (EN/IT/DE/ES/FR/PT-BR).

== Upgrade Notice ==

= 0.1.0 =
First release.
