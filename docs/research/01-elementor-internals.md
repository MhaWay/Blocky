# Elementor Internals — Technical Analysis for "blocky"

Analyzed directly from the **elementor/elementor** source at commit `3195141` (2026-09-10), **version 4.4.0**, including its in-repo `docs/atomic-builder/` (the v4 architecture reference). All claims verified against files in `blocky-research/ref/elementor/`.

Key insight up front: **Elementor has two generations**:

- **v3 "classic"** — PHP classes, Backbone editor, `{{WRAPPER}}` CSS templates, jQuery frontend handlers, per-post compiled CSS keyed by random element IDs.
- **v4 "Atomic Builder"** (experiments `e_opt_in_v4` + `e_atomic_elements`) — typed PropValues, Twig rendering (shared PHP-Twig frontend / Twing-JS editor canvas), React editor micro-packages, global classes + variables (Webflow-style design system), and **Alpine.js** (not jQuery) for frontend interactions.

---

## 1. Data model & storage

Post meta keys (`core/base/document.php:42-48`):

| Meta key | Constant | Purpose |
|---|---|---|
| `_elementor_data` | `ELEMENTOR_DATA_META_KEY` | The element tree (JSON) — the page |
| `_elementor_edit_mode` | `BUILT_WITH_ELEMENTOR_META_KEY` | `'builder'` marks a document as Elementor-built |
| `_elementor_template_type` | `TYPE_META_KEY` | Which Document class handles it (page/post/kit/header…) |
| `_elementor_page_settings` | `PAGE_META_KEY` | Document-level settings (Global Styles for this page) |
| `_elementor_version` | — | Version that last saved the doc |
| `_elementor_element_cache` | `CACHE_META_KEY` | Rendered-element cache |

Save path: `Document::save()` → `update_metadata('post', $id, '_elementor_data', $json)` (`document.php:1387`). WP posts remain the host; `post_content` keeps a `[elementor-page <id>]` shortcode for theme compatibility.

### Element tree shape (v3 settings style, verified from test template JSON)

```json
{ "content": [
  { "id": "7f1da560", "elType": "container", "settings": { "boxed_width": {"unit":"px","size":1290,"sizes":[]} },
    "elements": [
      { "id": "2c66403a", "elType": "widget", "widgetType": "accordion",
        "settings": {
          "items": [ {"item_title":"Item #1","_id":"5751892"} ],
          "accordion_border_radius": {"unit":"px","top":"12","right":"12","bottom":"12","left":"12","isLinked":true},
          "normal_title_color": "#FFFFFF",
          "__globals__": { "normal_title_color": "" }
        },
        "elements": [] }
    ] }
] }
```

Notable details:
- IDs are **random 8-hex strings** — used as CSS class hooks (`elementor-element-<id>`) for compiled CSS.
- Sizes are structured: `{"unit":"px","size":N,"sizes":[]}`; dimensions add `top/right/bottom/left/isLinked`.
- `__globals__` maps setting → global color/font token (design-system binding at value level).
- Settings keys are flattened-with-prefix (`title_typography_font_family`) — legacy of control-group concatenation.

### v4 element data: PropValues (`docs/atomic-builder/fundamentals/prop-value.md`)

Every stored value becomes a typed envelope:

```json
{ "$$type": "color", "value": "#wc26-gold", "disabled": false }
```

- `$$type` → registered prop type (`string`, `size`, `color`, `dimensions`, `background`, `link`, `html`, …).
- `disabled: true` → rendered as `null` (suppress) while persisting in editor.
- Absent key = default; `null` = explicit reset; `null` inside an object value = partial reset.
- Components wrap values in `{"$$type":"overridable","value":{"override_key":"hero-title","origin_value":{...}}}`.
- Styles (as opposed to settings) live per element as **variants**:

```json
{ "variants": [
  { "meta": { "breakpoint": "desktop", "state": null },  "props": { "color": {"$$type":"color","value":"#333"} } },
  { "meta": { "breakpoint": "mobile",  "state": "hover" },"props": { "color": {"$$type":"color","value":"#wc26-gold"} } }
] }
```

`props` keys are validated against the canonical **`Style_Schema`** (`styles/style-schema.php`): the authoritative CSS-longhand→prop-type map (`width`, `padding`, `border-radius`, `background`, `box-shadow`, `flex-direction`, …), extensible via `elementor/atomic-widgets/styles/schema` (the Variables module uses it to union `color` with `global-color-variable`).

> **This is the closest thing to a "CSS-in-JSON IR" in Elementor.** A Tailwind-native builder can treat the same shape as either (a) source of compiled CSS or (b) a *utility-class recipe*.

### Templates / CPTs (verified constants)

- `e-kit` — global styles document ("Kit")
- `e-landing-page` (`modules/landing-pages/module.php:21`), `e_default_style` (per-type default styles), `e_global_class` (global classes, **one CPT post per class**)
- Components: dedicated document type (`modules/components/module.php:170`)
- Theme-builder conditions stored as meta on the template.

**Global classes** (`docs/atomic-builder/global-classes/data-model.md`): one CPT post per class (label = post_title, variants JSON in `_elementor_global_class_data[_preview]`), kit meta holds `order` + label maps; max 1000 per kit; cascade order = `order` array. Published vs preview (`_preview` suffix) contexts everywhere.

---

## 2. Element / Widget architecture (v3 classic, still the backbone)

- `includes/managers/elements.php` + `includes/managers/widgets.php`. Elements are PHP classes extending `Element_Base` (`includes/base/element-base.php`); widgets extend `Widget_Base` (`includes/base/widget-base.php`).
- ~48 control types in `includes/controls/` (color, media, dimensions, slider, repeater, url, wp-widget, wysiwyg…), composed via `Controls_Stack`; controls carry `condition` (show/hide), `prefix_class` (class-injection trick), `selectors` (CSS templates), `global` binding.
- ~35 core widgets (`includes/widgets/`), with "optimized" variants (`common-optimized.php`) feeding the v4 atomic path.
- `container` (flexbox, since 3.6) replaced `section/column` (`includes/elements/section.php|column.php|inner-section.php` kept as legacy).

## 3. Rendering pipeline

### v3
1. `Frontend` (`includes/frontend/frontend.php`) detects `_elementor_edit_mode`; body gets `data-elementor-type`/`data-elementor-id`; content rendered via `[elementor-page]` → `Document::render()` walking the tree.
2. Each element wrapper (`element-base.php:787-857`):

```php
'class' => ['elementor-element', 'elementor-element-' . $id],
'data-id' => $id,
'data-element_type' => $this->get_type(),
'data-e-type' => $this->get_type(),
// widget-base.php:512
'data-widget_type' => $name . '.' . $skin,
// element-base.php:844 — JS-handler settings:
'data-settings' => wp_json_encode( $frontend_settings ),
```

`prefix_class` controls append setting-derived classes; animations add `elementor-invisible`. This attribute soup is what editor JS + frontend jQuery handlers key off.

### v4 (verified from `docs/atomic-builder/atomic-widgets/rendering.md`)
- Widget markup = **Twig templates**; *same* `.twig` renders server-side (`Template_Renderer`) and in the editor canvas client-side (Twing JS). Single source of truth for markup — kills the v3 editor/frontend duplication.
- Context: `{ id, interaction_id, type, settings (resolved), base_styles }`.
- `Render_Props_Resolver::for_settings()` transforms PropValues → plain values via per-`$$type` transformers (chaining depth-limited to 3).
- Sanitization: `Html_Prop_Type::sanitize()` runs `wp_kses()` **at save time**; render-time restrictions are a separate independent filter layer.

## 4. CSS generation

### v3 — compiled per-post files with `{{WRAPPER}}` templates
- Widgets declare style templates in code: `'{{WRAPPER}} .selector' => 'color: {{VALUE}};'` (e.g. `includes/widgets/image-carousel.php:609+`). `{{WRAPPER}}` expands to `.elementor-element.elementor-element-<id>` (scoped per element + responsive mode), `{{VALUE}}`/`{{SIZE}}{{UNIT}}` resolve from settings; `-global`/`-hover` suffix variants for states/globals.
- Files (`core/files/css/`): `Post` (FILE_PREFIX `post-` → `post-<id>.css`), `Post_Preview` (`elementor-preview-<id>`), written under `uploads/elementor/css/`; hook `elementor/css-file/post/parse` lets modules append (Kit/global classes append here). Parsing = regex template substitution (`core/files/css/base.php:357-411`).
- Global CSS (kit) as separate file; per-post CSS = selector-per-element rules → **the CSS-bloat machine**: one rule per element+property, file per post, regenerated on save.

### v4 — Atomic CSS pipeline (`architecture/data-flow.md`)
```
frontend load → elementor/post/render collects post IDs
→ elementor/atomic-widgets/styles/register (providers register style defs by path)
→ Styles_Renderer: PropValues → CSS per breakpoint variant
→ CSS_Files_Manager: per-post .css (cached to disk) → wp_enqueue_style
```
Providers: `['base']` = all widget types' `define_base_styles()`; `['local',$post_id,$context]` = per-element `styles` from document JSON. Invalidation via `elementor/atomic-widgets/styles/clear`. Kit adds global-class CSS (`Atomic_Global_Styles`) and variables (`Variables_CSS_Renderer`).

**Still compiled CSS, not utilities.** v4 cleaned the architecture (typed props, variants, single schema) but never adopted utility-first output.

## 5. Responsive / breakpoints

- v4 runtime config (`core/breakpoints/manager.php` `get_default_config()`): **max-width, mobile-last**, user-editable:
  mobile 767 (max), mobile_extra 880 (max), tablet 1024 (max), tablet_extra 1200 (max), laptop 1366 (max), widescreen 2400 (**min**).
- Editor stores per-breakpoint style variants; CSS gets `@media` per mode. The newer "responsive CSS variables" approach moves device values to CSS custom properties so editor JS rewrites them on device switch.
- Legacy deprecated table in `core/responsive/responsive.php` (xs0/sm480/md768/lg1025/xl1440/xxl1600) — vestigial, not the live system.
- **Design note for blocky**: Tailwind = min-width (mobile-first), fixed scale sm640/md768/lg1024/xl1280/2xl1536. Elementor = max-width, editable, 6 device modes. Adopting Tailwind breakpoints verbatim is the low-friction choice; custom ones mean `@theme { --breakpoint-*: ... }` — supported, but the utility set shifts meaning. Keep Tailwind's fixed.

## 6. Editor architecture

- v1 shell still orchestrates: iframe preview in edit mode + side panel (Backbone era `assets/dev/`), communicating over an `elementor/message` postMessage channel; server ops over admin-ajax `elementor/ajax/*` (`elementor/ajax/register_actions` hook; v4 registers `render/element` at `modules/atomic-widgets/module.php:233`).
- v4 editor = **React/TS micro-packages** (`packages/packages/core/`: `editor-canvas`, `editor-editing-panel`, `editor-props`, `editor-styles`, `editor-styles-repository`, `editor-global-classes`, `editor-variables`, `editor-components`, `editor-interactions`, `editor-design-system`, …) loaded via the `elementor/editor/v2/packages` PHP filter, hooked onto the legacy model through `editor-v1-adapters`.
- REST surface is thin: `elementor/v1/*` (settings, documents media import, `operations/opt-in-v4`, feedback) + module APIs (global classes, variables, components, CSS-converter). Most editor traffic remains admin-ajax.
- Validation chain: JS live `validatePropValue` → PHP `Props_Parser` at save/import → module save handlers (`elementor/document/before_save`: component circular-deps, interactions).
- v4 ships **MCP integration** (WP Abilities API + in-editor MCP tools): AI-agent-driven editing as first-class. Docs explicitly tell agents: "emit `{ $$type, value }`, not raw CSS". blocky should design its JSON agent-legible from day one.

## 7. Frontend runtime

- v3: jQuery + per-widget JS handlers keyed off `data-widget_type` + `data-settings` (tabs, accordion, swiper…), Swiper bundled.
- v4: `elementor-v2-frontend-handlers` base + **Alpine.js** (`elementor-v2-alpinejs`) + action-link/form handler packages. Interactions module = declarative trigger/action JSON stored on elements.

## 8. Pain points a clone must avoid (all visible in the source)

1. **Element-ID-scoped compiled CSS** → per-post files with thousands of `.elementor-element-7f1da560` rules; shared styles barely extracted. Utilities + class-based style manager fix this by construction.
2. **Two data generations** (v3 flattened settings vs v4 PropValues) forcing migration machinery (`prop-type-migrations`, `Migrations_Orchestrator`, `design-system-sync`, `container-converter`). A new project starts v4-shaped.
3. **Random IDs as styling hooks** — unmergeable, unreviewable; class/utility hooks are greppable and reusable.
4. **Editor/frontend dual runtime** (v3) — PHP class render + Backbone JS render of the same widget. v4's shared Twig source is the right answer; blocky equivalent: shared template layer for server (PHP) and canvas (JS).
5. **Max-width responsive model** + 6 device modes (two of them "extra/landscape" edge cases).
6. **admin-ajax as the editor's data plane** (untyped, non-inspectable); v4 REST is partial.
7. **Meta bloat**: everything in post meta (page JSON, settings, cache, globals maps) — big pages hit serialized-meta cost; `_elementor_element_cache` papers over render cost.
8. **Two widget systems in parallel** (`includes/widgets` legacy vs `modules/atomic-widgets/elements`); jQuery-era widgets still shipping.

## 9. Takeaways for blocky

**Must replicate (the substance users depend on):**
- Document = JSON tree in post meta + `edit_mode` flag + shortcode/`the_content` handoff; templates-as-CPTs with conditions; kit/global styles; published-vs-preview split; revision/autosave interplay.
- A **style schema** as canonical map (keys → typed values → rendered output) shared by editor UI, save validation, CSS output, and REST/agent writes. (Elementor v4's `Style_Schema` + PropValue envelope is the proof of value; blocky's version maps "control → Tailwind-utility class(es)" instead of "control → CSS declaration".)
- Breakpoint/state **variants** as a first-class concept (`meta: {breakpoint, state}`) — maps 1:1 onto Tailwind variants.
- Global classes / design tokens bound at value level (like `__globals__`) → in blocky: `@theme` CSS vars + semantic utilities (theme.json interop later).
- Big-document ergonomics: element cache or static render output for hot pages.

**Modernize / diverge:**
- Output **utility classes as the styling primitive** (panel edits classes; no compiled per-element CSS for basic styling; custom-CSS only as escape hatch).
- Mobile-first breakpoints = Tailwind's, non-editable; states via variant syntax (`hover:`, `focus-within:`, `data-*:`, container queries `@min-[...]:`).
- No element-ID CSS hooks in production output; minimal `data-*` attributes, editor-mode only.
- Editor canvas reusing the *same* template functions as server render (Elementor v4's shared-Twig idea — copy the pattern).
- REST-first typed editor API, single PHP parser reused for REST/agent writes (`Props_Parser`-at-save is the model).
- Alpine.js-level interactivity budget: ~10KB frontend JS, zero jQuery.
- Agent-legible JSON + MCP/Abilities endpoint from the start.

### Source map (paths under `blocky-research/ref/elementor/`)
`core/base/document.php` (meta/save) · `includes/base/element-base.php` (wrapper attrs) · `includes/base/widget-base.php` · `includes/controls/*` · `includes/elements/*` · `includes/widgets/*` (style templates) · `core/files/css/*` (per-post CSS) · `core/breakpoints/manager.php` + `core/responsive/responsive.php` · `modules/atomic-widgets/**` (v4 engine: `styles/style-schema.php`, `props-resolver/`, `css-converter/`) · `modules/global-classes/**` · `docs/atomic-builder/**` (architecture, data-flow, style-schema, prop-value, rendering, global-classes — the richest reading) · `packages/packages/core/editor-*` (v4 editor packages) · `modules/mcp/**`.
