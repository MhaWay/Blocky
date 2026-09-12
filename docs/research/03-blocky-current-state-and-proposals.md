# Blocky — Analisi dello stato attuale, alternative e miglioramenti

Analisi 2026-09-12 su commit `0791793` (repo `MhaWay/blocky`, monorepo, 250 file, 1 commit "Initial"). Ogni affermazione è verificata su file; ciò che non ho potuto verificare è marcato (verify).

---

## 1. Come è ideato il progetto (architettura attuale, verificata)

### Monorepo (pnpm + turbo + wp-env + docker)
| Package | Ruolo | Dimensione chiave |
|---|---|---|
| `@blocky/core-plugin` | Plugin WP "motore": registry blocchi PHP, renderer, REST, token/theme engine, asset orchestrator, runtime JS frontend | 113 file; `Registry.php` 4837 righe (tutti i blocchi), ~70 Renderers, `src/index.ts` runtime 1162 righe |
| `@blocky/builder-plugin` | Plugin WP "editor": admin page React (zustand+immer), iframe canvas srcdoc, inspector | `Inspector.tsx` 3632 righe, `store/document.ts` ~1450, safelist CSS iframe 542KB, catalogo utility generato |
| `@blocky/tokens` | Design tokens JSON (core/semantic/brands) → build in `tokens.css`, `tokens.dark.css`, `tokens.tailwind.css` (mapping `@theme`) | build a script |
| `@blocky/theme` | Theme FSE minimale (templates HTML, parts) + CSS entry Tailwind via `@tailwindcss/vite` | 17 file |
| `@blocky/ui-primitives` | placeholder (solo cx.ts) | 4 file |
+ `e2e/` **22 spec Playwright** (P0→P3 per fase), CI (.github/workflows: JS+PHP+release), docker-compose (nginx/mysql/node), `tools/wp-env/*` QA script ad-hoc ×14.

### Modello dati (`_blocky_document` in post meta)
**Flat normalized node-map** (NON nested tree): `{ root, nodes: { <id>: { id, type, props, slots{name:[ids]}, variants } } }`. Slots named con shorthand `children`. Scelta **ottima**: move/reparent/referencing molto più puliti dell'albero annidato Elementor.

Stile utente dentro `props`:
- `twClasses: string[]` → classi Tailwind (varianti `md:hover:...` incluse)
- `twColorVars` / `twStyleVars` → **CSS custom properties inline** (`style="--bky-tw-*"`, sanitizer a whitelist rigido): canale per valori arbitrari (colori, spacing, gradient, bg-image) che **non richiede compilazione**
- `variants` di blocco: enum→classi definite nel registry (`'sm' => 'max-w-2xl'`) — i controlli "variant" scrivono enum, il renderer risolve classi

### Pipeline rendering & CSS (il cuore)
1. Save: builder → REST `POST blocky/v1/documents/{id}` → PHP salva JSON, `PageCompiler::warmFrontendCache` renderizza HTML via Pipeline PHP e cachizza in post meta (`_blocky_render_cache`, `_blocky_compile_hash`, `_blocky_css_candidates`) e risponde `html + classCandidates`.
2. CSS: il **browser dell'editor** compila i candidates con l'API JS `compile()` di tailwindcss (`pageCssCompiler.ts`) → `POST documents/{id}/css` → `_blocky_css_cache` ("best-effort", lettera del codice).
3. Frontend: filtro `the_content` (priority 9) serve HTML cachizzato; `AssetOrchestrator::enqueueCompiledPageCss` inietta la CSS di pagina come **`wp_add_inline_style`** (inline nel <style>); theme variant + token CSS separati; asset prod via manifest Vite, dev con HMR.
4. Canvas editor: iframe `srcdoc` = html PHP (editorMode con data-bky-id/slot attrs) + preview CSS **live-compiled** + theme CSS; selezione/hover via postMessage.
5. Runtime frontend: JS island (overlay manager con focus trap, scroll lock, stack, eventi `blocky:overlay::*`) — il design §G di todo.md è sostanzialmente implementato.

### Editor PHP→JS single-source-of-truth
`Registry.php` definisce JSON Schema props + variants map + editorConfig tabs/controls; il builder fa fetch di `GET blocky/v1/blocks` e genera l'inspector. SDK pubblico (`Blocky::registerBlock / renderBlock / registerTheme`). Fondamenta buone.

---

## 2. Punti di forza (da NON toccare)

1. **Node-map normalizzato** + slots named → superiore al tree Elementor.
2. **Class-first output** con doppio canale: classi per il closed-set + **CSS vars inline per gli arbitrari** → riduce il problema JIT alla radice (coerente con la Option C di docs/02, ma già più furba: il canale var è nel codice).
3. Registry PHP single-source + REST + SDK → estendibilità e coerenza editor/frontend.
4. Renderer PHP server-side puri (HtmlString immutabile, sanitizer whitelist) senza template engine → veloci e ispezionabili.
5. Token pipeline core/semantic/brand×mode → mapping `@theme`: design-system serio.
6. E2E Playwright per fase (22), CI completa, phpstan+phpcs+stylelint, monorepo pulito.
7. Runtime JS minimo a islands + CustomEvent bus, zero jQuery.

## 3. Debolezze strutturali (in ordine di rischio)

### W1 — La CSS di produzione è **inline per pagina, compilata dal browser dell'editor** (critico)
- `compile()` include **ogni volta** preflight+base+theme → ogni pagina trasporta una stylesheet completa duplicata in un `<style>` inline: nessuna cache HTTP cross-page, HTML gonfiato, Cache-Control inutile.
- Il CSS esiste **solo se qualcuno salva dall'editor**: documenti creati/modificati via REST, CLI, import o da agenti AI restano **senza CSS** (nessun fallback: `enqueueCompiledPageCss` esce se meta vuota → pagina nuda).
- Last-writer-wins: l'hash del documento viene salvato, ma il POST del CSS è separato e non verificato — nulla lega il CSS cachizzato all'HTML cachizzato.
### W2 — Mancano le editor-table-stakes: **no undo/redo, no autosave, no bozze locali, no revisions UI**. `document.ts` ha solo `save()`.
### W3 — Pannelli bespoke, non universali. `Inspector.tsx` 3632 righe; i controlli **universali** §B di todo.md (tutte le utility Layout/Style/Animations × breakpoint × state) NON risultano implementati: i blocchi espongono variant enum proprietarie + twClasses freeform. Il §F todo che le segna [x] non corrisponde al codice (verify visuale, ma l'evidenza è chiara).
### W4 — Zero test PHP: `phpunit.xml` punta a `tests/Unit` **inesistente**; la CI esegue `composer test` (se non assorbe il fallimento, il job PHP è rosso — verify). Sanitizer di classi/vars e Pipeline meritano unit test immediati.
### W5 — Monoliti da spezzare ora: `Registry.php` 4837 righe/70 blocchi/1 file, runtime `index.ts` 1162, `document.ts` ~1450, Inspector 3632. I preset di controllo condivisi §P0 sono duplicati per-blocco, non centralizzati.
### W6 — Render cache in **post meta**: HTML compilato (potenzialmente 100KB/pagina) in meta → costo serializzazione/autoload; invalidazione non collegata a cambi tema/plugin; meglio Transients/object-cache.
### W7 — Sicurezza endpoint CSS: `POST .../css` accetta qualsiasi stringa e la serve inline al frontend (check solo `edit_post`). Un account editor compromesso può iniettare CSS arbitrario sul frontend (defacement, esfiltrazione via selettori). Serve compilazione server-side o validazione forte.
### W8 — Gap功能i vs Elementor: niente **Theme Builder** (header/footer/conditions), niente **dynamic values** nei controlli (testo→post title/meta), niente import/export template gallery, CPT dedicato per i documenti builder (oggi: solo `page`).
### W9 — DevX residue: `tools/wp-env/qa-*.sh` ×14 one-off, `local:start` solo PowerShell (dev Windows), nessun README root (il design doc è todo.md). Onboarding ostile.

## 4. Alternative e varianti proposte

### A. Pipeline CSS — 3 opzioni
**A1 (raccomandata, "shared + delta")**
- Un **unico file CSS site-wide** ("blocky-shared.css"): preflight+theme+chiusura del set di classi **realmente emesso** (unione dei `_blocky_css_candidates` di tutte le pagine, ricostruito on-save in coda) compilato **server-side con il binario standalone tailwindcss** (release ufficiali v4.3.3, linux-musl incluso) via `@source inline()` brace-ranges dei pattern dei controlli; file in `uploads/blocky/` con hash.
- **Delta per pagina** minuscolo (poche classi arbitrarie non nel shared) su file, non inline.
- L'editor **mantiene** la compile-in-browser ma solo come preview reattiva: la fonte canonica della CSS diventa il server (risolve W1+W7 insieme).
- Host senza `exec()`: degradazione esplicita (compilazione browser + upload file + "Rebuild CSS" manuale in admin, pattern WindPress) — non "pagina nuda".
**A2 (minimo cambiamento)**: tenere la compile-browser ma **scrivere file in uploads** + dedupe: `blocky-base.css` generato una volta (preflight+tokens+theme) e CSS di pagina senza base (solo candidates). Alla publish, hash ≠ candidates → ricompilazione forzata (JS job).
**A3 (massima ambizione, "zero build")**: pannello ristretto al closed-set → safelist statico unico (quello attuale 542KB → potabile a 150-250KB raw con `@source not`), tutto l'arbitrario via canale CSS-vars (già esiste `twStyleVars`). Rinuncia: classi `[... ]` arbitrarie. Guadagno: zero compilazione server, cache perfetta, zero binari. Da valutare come **default** con A1 come escape hatch.

### B. Control system → fare il §B VERAMENTE (il valore mancante più grosso)
- Estrarre preset nel core-plugin: `controls/layout.php`, `style.php`, `animations.php`, `advanced.php` → ogni blocco compone `[content(own), ...LAYOUT, ...STYLE, ...ADVANCED]`. Tipi controllo scritti una volta (range+unità, dimensions 4-in-1, color+var picker, gradient builder, background 4-modi, image control unificato §C).
- Editor: **device switcher globale** (base→2xl) + **variant chips** (hover/focus/group/peer/data/aria/dark/motion/print/has/container-queries) che qualificano la scrittura delle `twClasses` (→ `md:hover:bg-x`). Il catalogo utility generato esiste già; serve l'analogo per i descrittori dei controlli.
- Effetto collaterale: i blocchi diventano ~30 righe di definizione e il §H (checklist per blocco) diventa verificabile automaticamente.

### C. Editor fundamentals
- **Undo/redo + autosave**: middleware su `document.ts` (snapshot della node-map, economici; o JSON-patch log) + localStorage draft + recovery post-crash; poi mapping su WP revisions alla save.
- Inline text editing (contenteditable su heading/text con sync props on blur), multi-select, drag con drop indicator, keyboard map.
- Modalità "code": HTML+classi generate ispezionabili/modificabili — "Copy as Tailwind"/"Import classes" (§D todo) è la feature distintiva power-user, e coincide con il "JSON agent-legible" del doc 01.

### D. Qualità
- Creare `tests/Unit`: RenderContext sanitizer, Node normalize, Pipeline variant resolver, PageCompiler hash/candidates — 40+ casi; verificare che CI PHP sia davvero verde (W4 verify).
- Vitest per le store op; tipi TS generati dagli JSON Schema PHP (contratto save↔UI).
- Refactor: Registry in `blocks/*` per-file con manifest; runtime in moduli (overlays.ts, interactions.ts, animations.ts); Inspector per feature. qa-*.sh ×14 → 1 script + README root.

### E. Resa vs Elementor (roadmap功能i)
1. **Theme Builder**: CPT `blocky_template` + condizioni (archivio/taxonomy/ruolo) header/footer/single/404 — il tema ha già parts HTML da collegare.
2. **Dynamic values**: envelope `{type:'ref', ref:'post.title'|'meta:x'|'query:v'}` nei props testuali — versione minimale del PropValue Elementor v4, parser PHP unico riusato da REST/save.
3. Import/export JSON + template gallery + kit export (tokens+templates).
4. Cache: Transients/object-cache, meta non-autoload, invalidation hooks; page CSS file con cache headers.
5. **AI-ready**: REST typed c'è già → esporre MCP server (WP Abilities API) — Elementor v4 sta validando proprio quella direzione (doc 01 §6).

## 5. Priorità proposte (impatto/ effort)
| # | Azione | Impatto | Effort |
|---|---|---|---|
| 1 | A1/A2: CSS server-side + file (no inline, no "solo-editor") | Altissimo | M |
| 2 | B: control system universale (breakpoint+variant engine) | Altissimo | L |
| 3 | C: undo/redo + autosave | Alto (percezione utenti) | M |
| 4 | D: phpunit + CI verify + split monoliti | Alto (manutenibilità) | M |
| 5 | E1: Theme Builder | Alto (parità Elementor) | L |
| 6 | W6 cache move + W7 hardening endpoint | Medio | S |
| 7 | Spike A3: misurare safelist chiuso reale (KB gzip) | Decide il futuro | S |
| 8 | E2: dynamic values | Medio | M |

## 6. Decisioni richieste all'owner
- Target hosting (WP.com / shared senza `exec()`?) → pesa A1 vs A3.
- Licenza futura (GPL vs freemium) e utente-tipo (Tailwind developer vs migrante Elementor → quest'ultimo apre a un "migration mode" classi Elementor→utilities, donghione non banale).
- Dev principale su Windows? (script PowerShell) → questo ambiente Linux può consolidare i qa-*.sh in workflow container.
