# Blocky — Se lo strutturassi io: strategia, stack, decisioni

Domanda dell'owner (2026-09-12): "se fossi tu a strutturare questo progetto con l'obiettivo di diventare il miglior concorrente di Elementor, cosa faresti?" Risposta come decisione, non come menu. Ogni scelta è motivata e verificabile; i numeri vengono da docs/05 + spike S2.

## 0. Tesi
1. **Non si vince clonando Elementor feature-per-feature** (team enorme, ecosistema, brand). Si vincono le loro **debolezze strutturali**: CSS/JS bloat, DOM profondo, editor lento, output non portabile, JSON illeggibile, eredità jQuery/Backbone.
2. **Posizionamento**: "il builder per chi cura il Core Web Vitals" — agenzie, dev shop, il mercato Tailwind-first che oggi usa Bricks/Breakdance o soffre con ACF+Gutenberg.
3. **Il moat non è il builder, è il contratto**: documento JSON versionato + vocabolario chiuso + validazione unica. Da lì derivano gratis: performance deterministica, migrazioni, MCP/AI, git-friendliness, testabilità.

## 1. Stack decisionale (cosa terrei, cosa cambierei)
| Area | Decisione | Motivo |
|---|---|---|
| Modello dati | Tenere node-map piatta + slots named; **formalizzare** doc-version e varianti strutturate bp/state con migrazione framework dal giorno 1 | Elementor doc01: due generazioni di dati = dieci anni di macchine di migrazione. Noi nasciamo versionati. |
| Output styling | Class-first closed-set (S2: **23.4 KB gz** per tutto il vocabolario) + CSS-vars per fuori-scale; nessuna classe arbitraria nel percorso normale; escape "code mode" con ricompilazione asincrona | Determina L3 = file unico cacheabile: la tesi di mercato. |
| Motore CSS | Binario standalone vendored (checksum + versione in costante); CLI fonte canonica; npm-browser solo preview overlay; fallback upload validato senza exec | One engine, three outputs (docs/04); equivalenza verificata S1. |
| Cache | HTML render in object cache/transients astratti (NON post meta); documento in meta autoload (piccolo); rebuild in coda | TTFB quasi-statico; risolve W6. |
| Editor | React+zustand+immer (già); **Inspector riscritto come renderer di descriptor**; canvas iframe srcdoc + postMessage (già, funziona); undo/redo + autosave | Il valore è il Control Kit, non reimpastare React. |
| Runtime frontend | Runtime custom minimale (overlay/interactions attuale buono); NO Alpine, NO React frontend; enqueue islands solo se presenti | 0 JS default batte anche Alpine. |
| Font | Default system-stack; opt-in self-hosted woff2 + preload + swap | Il tranello PSI (docs/05). |
| Test | phpunit sul motore (sanitizer/pipeline/extractor) + vitest + **Playwright screenshots visual-regression per blocco** + **Lighthouse CI budget** su sito fixture golden | La qualità del claim dev'essere un gate rosso/verde, non una vibe. |
| CI | JS/PHP ok; aggiungere: compile vocabolario → size gate (L1<=30KB gz, L3<=35KB gz) + PSI fixture >= 95 | Numeri in ogni PR. |
| Licenza | Core **GPL gratuito feature-complete** (builder, blocchi, overlays, form); Pro = cloud kit/marketplace/collaboration/support; MAI paywall sulla qualità dell'output | La wedge di mercato è la credibilità; monetizzare dopo la community (playbook WP Rocket/GenerateBlocks). |
| Ecosistema | CPT blocky_template + conditions (Theme Builder) PRIMA del marketplace; import/export JSON locale subito | Parità Elementor percepita senza cloud. |

## 2. Roadmap (decisioni già incorporate)
- **F0 (fatto)**: spike S1/S2 — numeri chiusi, budget fissati (L1<=30KB, L3<=35KB gz).
- **F1 CSS-pipeline**: binario + coda + file in uploads + hash + fallback senza exec + de-inline + endpoint CSS protetto (ricompilazione server-side). Uccide W1/W7. Deliverable: sito demo con 1 file CSS, Lighthouse mobile >= 95.
- **F2 Control Kit**: descriptor come dato, validator PHP generato, UI generata in ui-primitives, device/variant writer, blocchi rifatti sui preset. Uccide W3/W5; il vocabolario S2 diventa file canonico generato.
- **F3 Editor UX**: undo/redo, autosave, inline editing, multi-select, command palette. Uccide W2.
- **F4 Qualità strutturale**: phpunit + visual regression + split Registry + object cache + benchmark Elementor misurato. Uccide W4/W6.
- **F5 Theme Builder + import/export + dynamic values**: parità percepita.
- **F6 MCP/API-key**: agent-ready (Elementor v4 sta validando proprio questa domanda).
- **F7 Migration mode**: importatore Elementor (parser _elementor_data -> node-map Blocky, mappature controlli comuni) + "export to static HTML+Tailwind" — il killer go-to-market: arrivi col contenuto migrato, ed esci quando vuoi.

## 3. Cosa NON farei (trappole)
- Riscrivere da zero: l'asset attuale (renderers, tokens, runtime overlays) è buono; si riorganizza, non si ribalta.
- Supporto temi terzi nel MVP (D1) — il theme-bridge ha già il suo slot nel compilatore (docs/04).
- Blocchi infiniti: 70 ci sono già; la parità si vince con 5 tab universali + preset, non col 71° blocco.
- Cloud-locked: nessun obbligo account; cloud come optional (la fiducia è il brand con cui Bricks/Breakdance hanno rubato pezzi a Elementor).
- Classi arbitrarie come percorso primario: uccidono il closed-set, quindi il budget, quindi la tesi.

## 4. Registry decisioni (update)
- **D5** (S2, 2026-09-12): vocabolario Control Kit = **23.4 KB gzip** compilato (0.3s, binario 4.3.3). Budget adottati: L1 <= 30KB gz, L3 <= 35KB gz. File canonico temporaneo: docs/vocab-s2.css, diventerà packages/.../vocabulary/studio.css generato dai descriptor in F2.
- **D6**: runtime custom senza Alpine nel frontend (proposal — owner da confermare).
- **D7**: core GPL gratuito feature-complete, Pro = cloud/collaboration (proposal — owner).
- **D8**: importatore Elementor (F7) come go-to-market, dopo F1-F5.
