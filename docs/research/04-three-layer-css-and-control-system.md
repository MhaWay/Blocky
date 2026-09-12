# Blocky — Architettura a 3 livelli CSS + Control System (proposta formale)

Base: analisi docs/03 + intent dell'owner (2026-09-12):
- **Editor graphic-first**: ogni proprietà si modifica tramite controlli con **range validi** (pulsanti, select, choicebox, slider a step), come Elementor "ma fatto meglio".
- "Temi" in preview = **varianti cromatiche light/dark** (non temi WP terzi) → il tokens system attuale è già la risposta giusta, va solo isolato meglio nei layer.
- Obiettivo: **vincere Elementor su prestazioni e qualità**.
- **MCP/API con key per LLM** solo a sistema stabile.

---

## 0. Principi guida
1. **One engine, three outputs.** Un solo compilatore Tailwind (binario standalone, versione pinnata), tre artefatti derivati deterministically dallo stesso input. Il WYSIWYG editor↔frontend dev'essere una proprietà strutturale, non una coincidenza di versioni npm.
2. **Graph-first ⇒ closed vocabulary.** Ogni controllo emette solo classi da un range valido (token/scale). I valori fuori-scale passano dal canale **CSS custom properties** (`twStyleVars`, già esiste) — mai classi arbitrarie nel percorso normale. Questo è ciò che rende L3 piccolo e il builder istantaneo.
3. **Il documento JSON è il prodotto.** Editor, REST, MCP, theme: tutti consumatori dello stesso contratto, validato da un solo parser server-side.
4. **Frontend = file statici cache-abili + islands JS minime.** Mai inline (salvo future decisioni esplicite), mai dipendenze da "chi ha salvato l'ultima volta".

## 1. I 3 layer, definiti

### L1 — Studio (canvas del builder)
- **Artefatto**: `blocky-studio.css` — compilata **a release del plugin** (CI), contiene: preflight + tokens `@theme` + **closed-set completo** (tutte le classi che i controlli del control-kit possono emettere × breakpoint × stati, via `@source inline()` brace-ranges) + variant CSS light/dark (toggle via attributo; i tokens dark già coprono).
- Il canvas la carica statica → **zero compilazione durante il typing** per il closed-set. Stime: la safelist esistente (542KB raw) è gonfiata da `@source` di sorgenti TS/PHP troppo ampi; potandola al solo vocabolario controlli reale → obiettivo <250KB raw / ~30KB gzip (misurare: spike S1).
- **Overlay arbitrari** (solo modalità code/dev): la compile live in-browser attuale resta come overlay di anteprima, marcata come non-canonical.
- La chrome dell'editor (`builder.css`) resta separata com'è (giusto così).

### L2 — Preview (preview/draft con varianti cromatiche)
- **Stesso artefatto di L3** + layer variante attivo (light/dark via tokens; `ThemeEngine` già fa la sua parte) generato **alla publish/preview-time dal binario server-side**, non dal browser.
- La preview di una bozza usa il percorso identico con flag `draft` (draft ≠ published tokens).
- Il live-compile browser dell'iframe **non è più la fonte della preview**: serve solo tra una keystroke e l'async-rebuild (200-500ms di coda, non bloccante).
- Se un giorno arriverà il supporto temi terzi: il *theme bridge* entra come **input extra del compilatore** (preflight scoped + mapping theme.json), senza toccare i 3 layer. Architettura pronta, feature rimandata.

### L3 — Frontend visitatori
- **2 file, entrambi statici in `uploads/blocky/` con content-hash:**
  - `blocky-site-<hash>.css` — preflight+theme+chiusura dei candidati **di tutto il sito** (unione dei `_blocky_css_candidates`, già raccolti alla save). Ricostruito in coda post-save (WP-Cron/async), non on-request.
  - `blocky-page-<id>-<hash>.css` — solo il delta della pagina fuori dal set sito (spesso vuoto: i valori custom passano dal canale vars inline nelle attr).
- Enqueue: theme-variant CSS (esistente) + site CSS + page delta. **Mai `wp_add_inline_style`** per la utility CSS.
- Pagine senza CSS rigenerato (host senza `exec`, coda ferma): fallback esplicito: (a) compile via browser dell'editor con upload del file (pattern WindPress) come parte della save quando il server non può, (b) admin "Rebuild CSS" di massa, (c) debug esplicito. **Mai pagina nuda silenziosa**.

### Motore
- Binario `tailwindcss-linux-x64` (+musl/macos/win) dalle release ufficiali 4.3.x, vendored nel plugin o scaricato alla prima attivazione con checksum + versione in constante `BLOCKY_TW_VERSION`. Stessa versione usata in CI per L1 → parità garantita.
- Se `exec()` non disponibile: stessa pipeline JS in-browser (esiste già!) ma il cui output viene **validato e sanificato** dal server (no `@import`, no url() esterni; hash dichiarato) prima di diventare artefatto — fissa anche W7.

## 2. Control Kit — il cuore graph-first (manca oggi: W3)

Ogni proprietà modificabile = **ControlDescriptor** nel registry (dato, non codice):

`{
  id: "paddingTop", family: "pt", control: "scale-picker",
  values: ["0","px","0.5","1","1.5","2","3","4","5","6","8"],
  responsive: true, variants: ["hover"], default: null,
  custom: { via: "css-var", var: "--bky-tw-pt", units: ["px","rem"] }
}`

- `control` = widget UI (segmented-button, select, choicebox, scale-picker, color-picker-with-swatches, toggle...). UI generata, identica ovunque → `ui-primitives` finalmente esiste, Inspector diventa ~500 righe di renderer generico.
- Il descrittore **è** il contratto per: UI (TS types generati) + validator PHP (classe ∈ famiglia, value ∈ range) + MCP schema (i vincoli diventano JSON Schema per gli LLM: l'agente non può emettere classi fuori range — qualità garantita anche via API).
- Preset condivisi per tab (§B todo, finalmente come dati): `LAYOUT=[display, flex..., grid..., sizing, spacing, position, overflow...]`, `STYLE=[...]`, `ADVANCED=[...]`. Blocco nuovo = content props + slice di preset → ~30 righe.
- Device switcher + variant chips (hover/focus/dark/...) qualificano la scrittura: il documento salva `{bp:"md", state:"hover", classes:["pt-6"]}`; la classe finale `md:hover:pt-6` è derivata dal renderer, non digitata. Storage per-variant dichiarato → UI pulita, migrazioni, diff, MCP tutto ne beneficiano.

## 3. Prestazioni: rendere "vincere su Elementor" misurabile
Budget in **CI (size-limit gate + Lighthouse CI)**:
- L3: CSS sito < 40KB gzip, delta pagina < 5KB, 0 CSS inline, 0 duplicazione cross-page.
- JS frontend: runtime < 12KB gzip totale, caricato solo se la pagina contiene islands interattive (il flag `interactive` nel registry c'è già: collegarlo a enqueue condizionale).
- TTFB: HTML cachizzato (muovere il render-cache da post meta → object cache, W6) e CSS cache-abile → confrontabile con un sito statico.
- L1 (builder): una sola stylesheet, zero compile per keystroke nel percorso closed-set.
- Benchmark: misurare la per-post CSS reale di Elementor su una pagina standard → numero di marketing onesto per il claim "prestaZIONI".

## 4. MCP/API key (last mile, on purpose)
Quando 1-3 girano: REST `blocky/v1` + application passwords/api key (cookie+nonce non bastano per client esterni) → scope: read catalog (registry + control kit), documents CRUD validati dal parser unico, patch per-id (la node-map piatta rende le patch banali). Closed-set + descriptor → JSON Schema: gli LLM lavorano **dentro** il range valido. Nulla da inventare: è la conseguenza naturale di tutto il resto.

## 5. Roadmap proposta
| Fase | Contenuto | Perché |
|---|---|---|
| **S0 Setup** | AGENTS.md nel repo, README root, roadmap su issues, branch develop | Lavorarci sopra in sessioni multiple senza perdere contesto |
| **S1 Spike (1 giorno)** | Potare la safelist → KB gzip reali; diff output binario vs npm compile; verificare determinismo candidate-extraction PHP | Fissa i budget e conferma il one-engine |
| **P1 CSS pipeline** | Binario + coda rebuild + file uploads + site-shared/page-delta + fallback senza exec + de-inline + hash-verify | Uccide W1/W7 |
| **P2 Control Kit** | Descriptor schema + preset tabs + ui-primitives + inspector generico + device/variant writer + PHP validator | IL prodotto: graph-first, blocchi a 30 righe |
| **P3 Editor UX** | Undo/redo + autosave localStorage + inline text editing + multi-select | Table-stakes |
| **P4 Qualità** | phpunit tests/Unit + vitest + bundle budget CI + split monoliti + consolidare qa-*.sh | La "qualità" del claim |
| **P5 MCP** | API key + typed endpoints dal descriptor | Solo a sistema solido |

Nota d'ordinamento: P1 prima di P2 perché il Control Kit *definisce* il closed-set definitivo della safelist — ma lo schema dei descriptor si progetta **durante** P1 (il closed-set di S1/P1 nasce provvisoriamente dai cataloghi attuali; P2 lo congela).

## 6. Registry delle decisioni
- D1: "temi" = varianti cromatiche light/dark via tokens system (owner, 2026-09-12). Theme bridge temi terzi **fuori scope MVP**, architettura predisposta.
- D2: editor graphic-first con range vincolati; fuori-range via canale CSS-vars, non classi arbitrarie libere (owner, 2026-09-12).
- D3: MCP/API-con-key rimandato a sistema funzionante (owner, 2026-09-12).
- D4 (proposta): one engine (binario standalone), closed-set + delta, no inline, file cache-abili.
