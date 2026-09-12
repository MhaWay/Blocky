# Tailwind CSS v4 + Builder Landscape — Analysis for "blocky"

Verified this session (2026-09-12) against: **tailwindcss.com docs repo** (`ref/tailwind-docs`, commit 2026-09-09), **npm registry** (tailwindcss / @tailwindcss/browser = **4.3.3**), **GitHub releases API** (tailwindcss v4.3.3 assets), **WordPress.org plugins API**. Sections marked *UNVERIFIED-IN-SESSION* come from general knowledge — bricks.io/breakdance.io were unreachable from this environment and the harness web_search backend returned garbage; re-verify later.

---

## 1. Tailwind v4 specifics (verified from docs sources in `ref/tailwind-docs/src/docs/`)

### CSS-first configuration
- Entry: `@import "tailwindcss";` — no `tailwind.config.js` required (`@config` directive exists for legacy compat).
- `@theme` defines design tokens as CSS custom properties in namespaces; the CSS emits the variables so arbitrary values can reference them — "Tailwind also generates regular CSS variables for your theme variables" (`theme.mdx:42`).
- **Breakpoints are theme variables**: "theme variables in the `--breakpoint-*` namespace determine which responsive breakpoint variants exist" (`theme.mdx:105`). Defaults (`responsive-design.mdx`): sm 40rem/640px, md 48rem/768px, lg 64rem/1024px, xl 80rem/1280px, 2xl 96rem/1536px. Adding `--breakpoint-3xl: 120rem` adds a variant. **Min-width (mobile-first) semantics.**
- `@theme inline { ... }` when tokens reference other variables — otherwise utilities resolve through the var indirection and can misresolve (`theme.mdx:480-501`).
- Directive inventory (`functions-and-directives.mdx`): `@import`, `@theme`, `@source`, `@utility`, `@variant`, `@custom-variant`, `@config`, `@reference`, `@plugin`, `@apply`, `@layer`s. `@utility` = first-class custom utilities; JS `@plugin` still supported.

### Content detection / JIT — THE critical constraint for a page builder
`detecting-classes-in-source-files.mdx` (verified):
- Scanning is **build-time** (Rust oxide scanner): class-looking strings in scanned sources become CSS. Automatic base-path detection + explicit `@source "... "`; ignore/disable controls exist (`@source not`, disabling auto-detection).
- **Dynamic class names are the documented footgun** — concatenated names must appear literally somewhere. A builder whose classes live in DB post-meta has no literal source files → scanner sees nothing.
- **Safelisting (v3 `safelist` is gone)**: `@source inline("underline")` forces generation; variants via brace expansion `@source inline("{hover:,focus:,}underline")`; **ranges** `@source inline("{hover:,}bg-red-{50,{100..900..100},950}")`; exclusion via `@source not inline(...)`. `corePlugins`/`safelist`/`separator` JS-config options are explicitly unsupported in v4 (`functions-and-directives.mdx:293`).
- Arbitrary values `[117px]` (`adding-custom-styles.mdx`): on-the-fly utilities from literal strings; spaces as underscores; arbitrary variants (`min-[960px]:`, `data-[...]:`, `has-[...]:`, `group-[...]:` — verified examples in `hover-focus-and-other-states.mdx`); `--value([...types])` declares accepted arbitrary types for custom utilities.

**Implication**: every architecture below answers one question — *when does a page's class set become CSS?*

### Toolchain (verified)
- **Standalone binaries ship in main-repo releases**: tailwindcss **v4.3.3** assets include `tailwindcss-linux-x64`, `-linux-x64-musl`, `-linux-arm64(-musl)`, `-macos-arm64/x64`, `-windows-x64.exe` → a PHP plugin can exec the right binary with no Node install (modern revival of the standalone CLI).
- **`@tailwindcss/browser@4.3.3`** published as `dist/index.global.js` — runtime in-browser engine (Play/CDN pattern; documented as dev/preview use).
- npm `tailwindcss@4.3.3` current; `@tailwindcss/postcss`/`@tailwindcss/vite` are the build-time integrations.

### Official Tailwind WordPress plugin — NOT FOUND where expected (verified absence)
- `tailwindcss.com/wordpress-plugin` → 404; `tailwindcss.com/plus/wordpress-plugin` → 404; tailwindlabs GitHub org (100 public repos) has **no** WP plugin repo; WP.org `slug=tailwind` → nothing. Whatever memory of an "official" plugin exists is wrong *as of today* — **for blocky this is opportunity** (ecosystem below).

### WP-ecosystem Tailwind integrations (verified via WP.org API)
| Plugin | Installs | Approach |
|---|---|---|
| **WindPress** | ~3,000 | "Generate the final optimized CSS file **in the browser without server-side tools**"; integrates with Gutenberg/GreenShift/Kadence; extensible integration API |
| CompileKit | ~10 | generic Tailwind compiler plugin |
| ska-blocks | ~80 | Tailwind classes in block editor |

→ Browser-side compilation is a proven WP pattern today (WindPress); nobody owns "Tailwind-native builder".

---

## 2. Competitor landscape (*UNVERIFIED-IN-SESSION — sites unreachable from this environment; re-check specifics*)

| Builder | Storage | Editor | CSS output | Notes (to verify) |
|---|---|---|---|---|
| **Bricks** | JSON element tree in post meta (own format) | Vue app, iframe frontend editor | Compiles per-page CSS; internally Tailwind-flavored; minimal frontend footprint (no jQuery) | closest ancestor of blocky; one-time-price licensing |
| **Breakdance** | JSON element tree (own) | Vue-based editor | Tailwind classes internally + compiled output; claims clean HTML | — |
| **Gutenberg/FSE** | Blocks as HTML-comment JSON in `post_content` (`<!-- wp:name {json} /-- >`); block.json metadata (registration verified via Gutenberg docs — incl. "block names cannot be changed without touching all posts") | React in-content editor | theme.json tokens → theme CSS vars; opt-in per-block CSS | content-locked markup, no free-form canvas |
| **GenerateBlocks/Kadence** | block attrs in content | block editor | small global CSS | lightweight-addon territory, not Elementor UX |
| **Webflow** | own DB | canvas | semantic CSS classes via style manager | UX benchmark; in Tailwind-land the *utilities are* the style manager — don't reinvent |

Elementor v3/v4: fully source-verified in `01-elementor-internals.md`.

---

## 3. Design questions — concrete options

### (a) Where does compilation happen?
1. **A. On-save PHP→binary**: plugin keeps a `blocky.css` entry (`@import "tailwindcss"` + `@theme` + `@source` for plugin templates). On save, PHP regenerates a **class manifest** extracted from post-meta JSON and runs the standalone binary; output cached in `uploads/blocky/css/`. Bricks-grade result, zero build tooling for users. **Costs**: binary per-platform management; save latency (async queue); hosts with `exec()` disabled must degrade gracefully.
2. **B. Runtime-editor / build-time-prod**: editor canvas loads `@tailwindcss/browser` (instant parity while editing, zero save cost); production gets compiled CSS via A (or cron warm build). WindPress proves the browser half; "Play CDN not for production" respected since prod uses files.
3. **C. Safelist-first, build-once** *(recommended default)*: ship ONE pre-built stylesheet covering the **closed set** of classes the panel can emit — full utilities across the fixed breakpoint×state matrix generated at plugin-dev time via `@source inline()` brace ranges (colors×breakpoints×hover/focus, spacing scale, flex/grid, typography, radii, shadows). The panel edits **only classes from that set** → **no per-site build at all**; one immutable, cache-busted file (est. 200-400KB raw / ~30-60KB gzip — measure).

### (b) Editor ↔ frontend CSS parity
Canvas loads the **same artifact** production uses → WYSIWYG is literal. With arbitrary values, canvas additionally loads the page's small delta CSS and (during editing) `@tailwindcss/browser` for not-yet-built values — carefully namespaced to avoid double-application.

### (c) Panel controls → utilities
Each control writes **class tokens**, not a value store: padding control → `pt-4 md:pt-6`; color swatch → `text-primary` (theme token) or `text-[#hex]` (arbitrary). Elementor v4's lesson stands — a **style schema** is still required, but as *control → (utility family, variants, value constraints)* mapping ("classes schema"). Stored JSON is diffable, git-able, agent-legible.

### (d) Breakpoints
Adopt Tailwind's fixed min-width set; device toggles = sm/md/lg/xl/2xl. Elementor's editable max-width modes are a UX liability (doc 01 §5). `@theme --breakpoint-*` remains available for theme authors, not as a casual setting.

### (e) Custom values
Fast lane: controls snap to scale steps → always safelisted. Escape lane: "custom" toggle → arbitrary class `w-[317px]` → that page gets a tiny **delta CSS** compiled lazily via A. `@source inline()` ranges make the fast lane exhaustively cheap; `@source not inline()` caps pathological sets.

### (f) Frontend runtime
Zero JS by default; Alpine.js attributes (Elementor v4 validated this) only on behavior widgets, per-page bundle. Never jQuery.

### (g) Browser engine
`@tailwindcss/browser` = editor canvas live preview + unpublished-preview + demos. Never production.

### (h) Bricks/Breakdance pattern ("classes in JSON → CSS from a closed set")
= option C *with* per-page files. With v4 inline() ranges + a closed control vocabulary, blocky skips even per-page files for the 90% case — one immutable site CSS beats both on cacheability.

---

## 4. Recommended architecture for blocky (decision draft)

**Data** — Elementor-v4-shaped typed document JSON (element tree, breakpoint+state variants — Elementor's own migration pains prove *typed variants* are the right IR), but style payload stores **class tokens**: `{ "variants": [ { "meta": {"bp":"md","state":null}, "classes": ["flex-row","hover:bg-accent"] } ] }`. A PHP `Class_Schema` validates token grammar per control family (single-parser-at-save, like Elementor's `Props_Parser`).

**CSS** — default **Option C** (one immutable safelist-complete site stylesheet, built at activation, content-hash versioned) + **Option A** fallback (bundled standalone binary, async) for arbitrary-value deltas + theme/`@utility` edits. Editor: **Option B** overlay for live in-progress classes.

**Editor** — iframe canvas of the actual theme page (frontend editor — market expectation), React panel; canvas and server share templates; production markup = utilities + minimal `data-blocky-id` (editor mode only). REST-first save API with typed validation; agent-legible JSON (Elementor v4's MCP direction confirms demand).

**Deliberate divergences from Elementor**: no element-ID CSS hooks, no per-element compiled selectors, no max-width modes, no admin-ajax data plane, no jQuery, no dual widget systems — start on the good parts.

**Open risks to validate next**:
1. Real safelist CSS size on v4.3 — spike: generate from a representative control vocabulary and measure.
2. `exec()` availability matrix on managed hosts — fallback: in-browser build (WindPress pattern) or admin-triggered rebuild.
3. Preflight vs theme styles — the #1 Tailwind-in-WP pain; decide scoped-preflight strategy early.

## 5. Follow-up verification list (blocked by environment today)
- Bricks/Breakdance/Webflow table specifics (pricing, per-page CSS mechanics, editor stacks).
- Whether an "official" Tailwind WP plugin exists under another domain (currently absent on WP.org/GitHub/tailwindcss.com as probed).
- Baseline: byte size of Elementor per-post CSS on a standard page (for the "blocky ships ONE file" claim).
