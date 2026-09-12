# Blocky — Roadmap Blocchi & Controlli (riferimento Elementor + Tailwind power-user)

> Obiettivo: trasformare Blocky in un page builder completo per chi sa usare Tailwind,
> con copertura blocchi paragonabile a Elementor e controlli che espongano TUTTE le
> utility Tailwind tramite UI (senza obbligare a scrivere classi a mano), pur lasciando
> sempre la possibilità di inserire valori arbitrari (`[42rem]`, `bg-[var(--x)]`, ecc.).
>
> Convenzioni:
> - **P0** = refactor base e fondamenta condivise (da fare prima di tutto).
> - **P1** = blocchi essenziali (parità con sezione “Basic” / “General” di Elementor).
> - **P2** = blocchi avanzati (parità con sezione “Pro”).
> - **P3** = blocchi opzionali / nice-to-have.
> - Ogni blocco eredita lo **schema universale di controlli** (sezione B).
> - I blocchi che gestiscono immagini ereditano anche lo **schema immagine** (sezione C).

---

## A. Inventario blocchi

### A.1 Blocchi attualmente presenti (da rifittare con nuovo schema controlli)
- `heading` — titolo (H1–H6).
- `text` / `paragraph` — testo ricco.
- `button` — pulsante / link stilizzato.
- `image` — immagine singola.
- `video` — video (file / embed YouTube / Vimeo).
- `html` — HTML custom.
- `divider` — separatore.
- `spacer` — spaziatore verticale/orizzontale.
- `list` — lista (ul/ol) con item.
- `quote` / `blockquote` — citazione.
- `section` — contenitore di sezione (full-width / boxed).
- `container` — contenitore generico flessibile.
- `rows` — riga (flex orizzontale).
- `columns` — colonne (flex/grid figli).
- `grid` — griglia CSS Grid.
- `card` — card composita (immagine + testo + azione).
- `wp-post-title` — titolo post dinamico.
- `wp-post-content` — contenuto post dinamico.
- `wp-featured-image` — immagine in evidenza.
- `wp-template-part` — inclusione template part.
- `wp-shortcode` — esecuzione shortcode.
- `wp-hook` — hook do_action.
- `theme-toggle` — switch tema chiaro/scuro.

### A.2 Nuovi blocchi da introdurre (ispirati a Elementor)

**Basic / Generale**
- `icon` — icona singola (libreria SVG + upload).
- `icon-box` — icona + titolo + testo + link.
- `icon-list` — lista con icone per item.
- `image-box` — immagine + titolo + testo + link.
- `image-gallery` — galleria base (grid/masonry).
- `basic-gallery` — galleria semplice WP-style.
- `star-rating` — punteggio a stelle.
- `alert` — riquadro alert (info/success/warning/danger).
- `progress-bar` — barra di progresso.
- `counter` — contatore animato.
- `testimonial` — testimonianza singola.
- `social-icons` — set icone social.
- `share-buttons` — pulsanti di condivisione.
- `nav-menu` — menu di navigazione WP.
- `breadcrumbs` — briciole di pane.
- `search-form` — form di ricerca.
- `anchor` — ancora interna (target di scroll).

**Pro / Interattivi**
- `tabs` — tab container (orizzontale/verticale).
- `accordion` — accordion / FAQ.
- `toggle` — toggle singolo (collassabile).
- `image-carousel` — carosello immagini.
- `content-carousel` / `slider` — carosello con contenuti arbitrari.
- `flip-box` — box girevole fronte/retro.
- `call-to-action` — CTA composita (eyebrow + titolo + testo + bottoni + media).
- `price-table` — tabella prezzi.
- `price-list` — listino prezzi.
- `countdown` — conto alla rovescia.
- `before-after-slider` — confronto immagini con slider.
- `hotspot` — immagine con punti interattivi.
- `lottie` — animazione Lottie/JSON.
- `animated-headline` — titolo con parole rotanti / effetti.
- `marquee` — testo/contenuti scorrevoli.
- `embed-google-maps` — mappa Google/OpenStreetMap.
- `embed-iframe` — embed generico (iframe sandbox).
- `code-highlight` — blocco codice con highlight.

**Overlay / Modal / Off-canvas** (vedi §H per il sistema completo)
- `modal` — finestra modale centrata (dialog) con backdrop.
- `offcanvas` — pannello laterale (left / right / top / bottom).
- `drawer` — variante off-canvas con handle/maniglia (mobile-friendly).
- `popover` — popover ancorato a un trigger (auto-positioning).
- `tooltip` — tooltip ancorato (hover/focus).
- `dialog-confirm` — preset dialog di conferma (ok/cancel).
- `lightbox` — overlay per media (immagini/video/gallery).
- `popup` — popup full-screen / center / corner con trigger configurabili (load, exit-intent, scroll %, delay, click, inactivity).
- `notification-toast` — toast non bloccante (top/bottom + corner).
- `cookie-banner` — banner consensi (preset di popup).
- `command-palette` — palette Cmd+K (ricerca azioni).
- `modal-trigger` — pulsante/elemento che apre un overlay specifico (per ID).
- Slot interni riusabili per gli overlay: `modal-header`, `modal-body`, `modal-footer`, `modal-close`.

**Form**
- `form` — contenitore form.
- `form-field-text` — campo testo / email / tel / url / number.
- `form-field-textarea` — area di testo.
- `form-field-select` — select.
- `form-field-radio` — radio group.
- `form-field-checkbox` — checkbox / checkbox group.
- `form-field-date` — data/ora.
- `form-field-file` — upload file.
- `form-field-hidden` — campo nascosto.
- `form-field-honeypot` — antibot.
- `form-submit` — pulsante invio.
- `login-form` — form login WP.
- `register-form` — form registrazione WP.
- `contact-form` — preset contact form.

**Dinamici / WP**
- `posts-list` — elenco post (loop).
- `posts-grid` — griglia post.
- `archive-posts` — loop archivio corrente.
- `featured-posts` — post in evidenza.
- `taxonomy-list` — elenco termini.
- `author-box` — box autore.
- `comments` — area commenti.
- `comment-form` — form commenti.
- `post-navigation` — prev/next post.
- `pagination` — paginazione.
- `sitemap` — mappa del sito.
- `table-of-contents` — indice generato dagli heading.

**Layout / Utility**
- `mega-menu` — menu esteso a colonne.
- `scroll-progress` — barra progresso scroll pagina.
- `sticky-bar` / `notification-bar` — barra sticky top/bottom.
- `back-to-top` — pulsante torna su.
- `divider-fancy` — divider con SVG / pattern.
- `spacer-responsive` — spacer con valori per breakpoint.

---

## B. Schema universale di controlli (per OGNI blocco)

Ogni blocco espone 4 tab fisse + 1 contestuale.

### Tab 1 — Content (contestuale al blocco)
Campi specifici del blocco (testo, link, opzioni, sorgenti dinamiche WP, ecc.).
Tutti i campi testuali devono supportare **dynamic tags** (post title, meta, ACF, query var, user, site, date).

### Tab 2 — Layout
Esporre tutte le utility Tailwind di layout, con override per breakpoint
(`base / sm / md / lg / xl / 2xl`) e variant chain (hover, focus, active, group-hover, peer-*, dark, motion-safe, motion-reduce, print, data-*, aria-*).

- **Display**: `block | inline | inline-block | flex | inline-flex | grid | inline-grid | contents | hidden`.
- **Flex** (quando display=flex/inline-flex):
  - direction (row / row-reverse / col / col-reverse)
  - wrap (wrap / nowrap / wrap-reverse)
  - justify-content (start / end / center / between / around / evenly / stretch)
  - align-items (start / end / center / baseline / stretch)
  - align-content (start / end / center / between / around / evenly / stretch)
  - gap / gap-x / gap-y (con unità px, rem, em, %, vw, vh, ch, arbitrary)
  - per-child: grow, shrink, basis, order, self-align, self-justify
- **Grid** (quando display=grid):
  - grid-template-columns (preset 1–12 + arbitrary, repeat, minmax, auto-fit, auto-fill)
  - grid-template-rows
  - grid-auto-flow (row / col / dense)
  - grid-auto-columns / grid-auto-rows
  - place-items, place-content, justify-items, align-items
  - gap / gap-x / gap-y
  - per-child: col-span / col-start / col-end / row-span / row-start / row-end
- **Allineamento contenuti (universale layout)**:
  - allineamento orizzontale figli (start / center / end / between / around / evenly / stretch)
  - allineamento verticale figli (start / center / end / baseline / stretch)
  - allineamento self (per blocco interno a un layout flex/grid)
- **Sizing**: width, min-w, max-w, height, min-h, max-h, aspect-ratio (preset + arbitrary), size token shortcuts (full, screen, fit, min, max).
- **Spacing**: padding e margin per lato (t/r/b/l) e per asse (x/y), con unità multiple, supporto valori negativi per margin.
- **Position**: static / relative / absolute / fixed / sticky + inset (top/right/bottom/left) + z-index.
- **Overflow**: visible / hidden / clip / scroll / auto, per asse x/y.
- **Float / clear**: opzionale.
- **Object-fit / object-position**: per immagini/video.
- **Container**: opzione “contenitore” (max-width per breakpoint, centratura, padding orizzontale).
- **Visibility per breakpoint**: nascondi su sm/md/lg/xl/2xl.

### Tab 3 — Style
- **State scopes**:
  - per tutti i blocchi: almeno `base` + `hover`.
  - per blocchi interattivi o semanticamente adatti (es. `button`, form controls, tabs, accordion, toggle, nav items): anche `focus`, `focus-visible`, `active`, `disabled`, `visited`, `checked`, `open`, ecc. quando applicabile.
- **Typography** (per blocchi con testo, ereditato dai contenitori):
  - font-family (system + Google Fonts + custom)
  - font-size (px/rem/em + preset Tailwind + arbitrary)
  - font-weight (100–900)
  - line-height
  - letter-spacing
  - word-spacing
  - text-transform (none / upper / lower / capitalize)
  - text-decoration (none / underline / line-through / overline) + color + style + thickness + offset
  - text-align (left / center / right / justify / start / end)
  - text-wrap (wrap / nowrap / balance / pretty)
  - white-space, word-break, hyphens
  - text-color
  - text-shadow (preset + custom: x, y, blur, color)
  - first-letter / first-line (variant)
  - placeholder color (per input)
  - selection color
- **Background** (semplificato — solo 4 tipi):
  1. `transparent`
  2. `custom` — color picker (hex/rgba/hsla/var) + opacità.
  3. `gradient` — linear/radial/conic; stops multipli (color + position); angolo; ripetuto; from/via/to.
  4. `image` — selezione da **WP Media Library** (sostituibile, con anteprima); per ogni immagine:
     - position (preset 9 + custom x/y in % o px)
     - size (auto / cover / contain / arbitrary)
     - repeat (no-repeat / repeat / repeat-x / repeat-y / round / space)
     - attachment (scroll / fixed / local)
     - origin / clip
     - blend-mode
     - overlay color + opacità (color + gradient)
     - parallax (offset y su scroll)
     - filtro su background (blur, brightness, ecc.)
  > **Da rimuovere/disabilitare** dall’UI: tutti i tipi di background diversi da questi 4.
- **Border**:
  - style (solid / dashed / dotted / double / none) per lato
  - width per lato
  - color per lato
  - radius per angolo (top-left, top-right, bottom-right, bottom-left) + scorciatoia simmetrica
  - divide-* (per figli)
- **Outline**: width, style, color, offset.
- **Ring** (Tailwind): width, color, offset color, offset width, inset.
- **Box shadow**: preset (sm, md, lg, xl, 2xl, inner, none) + custom multilayer (x, y, blur, spread, color, inset).
- **Drop shadow** (filter): preset + custom.
- **Filters**: blur, brightness, contrast, grayscale, hue-rotate, invert, saturate, sepia, drop-shadow.
- **Backdrop filters**: backdrop-blur, backdrop-brightness, ecc.
- **Opacity** (0–100).
- **Mix-blend-mode** / **background-blend-mode**.
- **Cursor**: preset + custom.
- **Pointer-events**: auto / none.
- **User-select**: none / text / all / auto.
- **Scroll behavior / scroll snap** (per contenitori scrollabili).
- **Mask** (SVG/preset) e clip-path.

### Tab 4 — Animations (NUOVA)
- **Entrance animations** (Intersection Observer):
  - preset: fade, fade-up/down/left/right, zoom-in/out, slide-up/down/left/right, flip-x/y, bounce, rotate, blur-in.
  - duration, delay, easing (preset + cubic-bezier custom).
  - threshold (% di intersezione), root margin, repeat (once / every).
- **Hover animations** (su blocco):
  - transform: scale, translate (x/y), rotate, skew (x/y).
  - color shift (testo, background, border).
  - shadow grow, ring grow.
  - filter shift (blur, brightness, ecc.).
  - opacity shift.
  - durata, delay, easing.
- **Focus / active / group / peer**: stesse opzioni hover, con scope variant.
- **Scroll-triggered**:
  - parallax (speed, axis).
  - sticky pin (con offset).
  - reveal progressive (parola/lettera per heading).
  - scrub timeline (proprietà legate allo scroll, % start/end).
- **Transitions** (globali del blocco):
  - property (all / colors / opacity / shadow / transform / custom list).
  - duration, delay, timing-function.
  - will-change.
- **Transforms** statici:
  - translate, rotate, scale, skew per asse + origin + perspective + transform-style (3d).
- **Keyframes/CSS animation**:
  - selezione preset (pulse, ping, spin, bounce, custom).
  - iteration count, direction, fill-mode, play-state.
- **Motion safety**:
  - rispetto `prefers-reduced-motion` (auto disattivazione / animazione alternativa).
  - toggle “motion-safe only”.
- **Performance**:
  - `will-change`, `contain`, `content-visibility`.

### Tab 5 — Advanced
- **Identificatori**: id HTML, anchor id, name.
- **Custom classes**: input multi-tag con autocomplete delle classi Tailwind (catalogo generato) + supporto arbitrary values + supporto `!important` (`!`).
- **Variant builder**: costruzione visuale di chain (`md:hover:dark:bg-[var(--x)]/80`).
- **Custom attributes**: coppie chiave/valore (`data-*`, `aria-*`, `role`, `tabindex`, `title`, `lang`, ecc.).
- **CSS variables**: definizione `--var: value` con scope al blocco; riferibili nei controlli (es. `bg-[var(--brand)]`).
- **Custom CSS**: textarea con selettore `{{WRAPPER}}` (escape hatch).
- **Conditional visibility / display logic**:
  - device (mobile / tablet / desktop, per breakpoint).
  - login state (logged-in / logged-out).
  - user role.
  - data corrente / range orario.
  - query var / cookie / parametro URL.
  - dynamic condition (post meta, ACF, taxonomy, query risultati).
- **Responsive overrides**: tutti i controlli di Layout/Style/Animations sono ripetibili per ogni breakpoint.
- **Sticky / motion**: opzione “sticky” con offset e breakpoint di attivazione.
- **Accessibility helpers**: aria-label, aria-describedby, role, focus ring obbligatorio per interactive blocks.
- **SEO**: tag semantico (override per heading level, section/article/aside, schema microdata).
- **Print styles**: hide-on-print, force-print.

---

## C. Schema controlli IMMAGINE (per ogni blocco che gestisce immagini)

Applicabile a: `image`, `image-box`, `image-gallery`, `image-carousel`, `basic-gallery`,
`card` (media), `wp-featured-image`, `video` (poster), `background-image` di qualsiasi
blocco, `hotspot`, `before-after-slider`, `flip-box` (face media), `lottie` (poster).

- **Source**:
  - selezione da WP Media Library (anteprima, info dimensioni, peso, alt).
  - upload diretto.
  - URL esterno.
  - dynamic source (featured image, ACF image, custom field, gravatar autore, immagine termine).
- **Size**: scelta della size WP registrata (thumbnail, medium, large, full, custom) + override `srcset`/`sizes`.
- **Attributi**:
  - alt (con suggerimento da media meta).
  - title.
  - caption (mostra/nascondi).
  - figcaption custom.
  - loading: `lazy | eager`.
  - decoding: `async | sync | auto`.
  - fetchpriority: `auto | high | low`.
  - referrerpolicy.
  - crossorigin.
- **Layout immagine**:
  - aspect-ratio (preset + arbitrary).
  - object-fit (cover / contain / fill / none / scale-down).
  - object-position (focal point UI 9-zone + drag custom in %).
  - width / height (con preset + arbitrary, mantieni proporzioni).
  - max-width, min-width, ecc.
- **Stile immagine**:
  - border, radius per angolo, shadow, ring, outline.
  - filtri (blur, brightness, contrast, grayscale, hue-rotate, invert, saturate, sepia, drop-shadow).
  - blend-mode rispetto al contenitore.
  - mask SVG / clip-path (preset shapes: circle, hexagon, blob, custom path).
  - overlay color/gradient con opacità.
  - tinta duotone (color1 + color2).
- **Hover**:
  - scale / translate / rotate.
  - swap image (immagine secondaria al hover, dissolvenza/scivolata).
  - filter shift (es. da grayscale a colore).
  - overlay reveal con testo/icona.
  - zoom-in stile lightbox preview.
- **Link / azione**:
  - link a URL / media / lightbox / popup / custom action.
  - lightbox (single / gallery / video).
  - apri in nuova tab, nofollow, sponsored, ugc.
- **Caption / didascalia**:
  - mostra/nascondi.
  - posizione (sotto / sopra / overlay).
  - allineamento.
  - tipografia ereditata dal sistema typography (vedi Style).
- **Performance**:
  - WebP/AVIF preference (se disponibile).
  - placeholder (blur-up / dominant color / skeleton).
  - LQIP / BlurHash.
- **Accessibilità**:
  - alt obbligatorio quando link, opzionale quando decorativa (`role="presentation"`).
  - aria-label per lightbox trigger.

---

## D. Tailwind power-user surface (trasversale)

- **Class editor avanzato**:
  - autocomplete da catalogo generato (`blockPresetCatalog.ts`).
  - input di valori arbitrari `[…]`.
  - supporto `!` (important).
  - rilevamento classi conflittuali (es. due `bg-*` non variant) con avviso.
- **Variant chain builder**:
  - breakpoint: `sm md lg xl 2xl` (+ `max-*`).
  - state: `hover focus focus-visible focus-within active visited disabled checked open`.
  - parent/sibling: `group-hover group-focus peer-hover peer-checked`.
  - data/aria: `data-[state=open] aria-expanded:` ecc.
  - theme: `dark`.
  - motion: `motion-safe motion-reduce`.
  - print, rtl/ltr, supports-*, has-*.
  - container queries: `@sm`, `@md`, ecc.
- **CSS variables panel**: definizione e riuso (`--brand`, `--space-x`, ecc.).
- **Token bridge**:
  - design tokens del theme.json di WP esposti come variant.
  - sincronizzazione `twColorVars` con WP color palette.
- **Live preview**: ogni modifica si riflette nell’iframe canvas.
- **Copy as Tailwind**: esporta le classi finali del blocco.
- **Import classes**: incolla una stringa di classi e popola i controlli UI.

---

## E. Fondamenta condivise da costruire (P0 — prima dei blocchi)

1. **Refactor del control system**:
   - introdurre struttura tab fissa `[Content, Layout, Style, Animations, Advanced]` per ogni blocco.
   - rendere lo schema dichiarativo riusabile (DRY) — i blocchi dichiarano solo i campi del Content; Layout/Style/Animations/Advanced derivano da preset condiviso.
2. **Background control unificato**:
   - implementare il nuovo controllo con 4 modalità (transparent / custom / gradient / image).
   - rimuovere/nascondere tutte le altre opzioni.
   - integrare WP Media Library (wp.media) per la modalità image.
3. **Animations engine**:
   - libreria leggera basata su IntersectionObserver + CSS classes + variabili CSS.
   - rispetto `prefers-reduced-motion`.
   - registrare attributi `data-bky-anim-*` sui blocchi e gestire runtime nel preview iframe e nel frontend.
4. **Responsive system**:
   - editor con switch breakpoint globale (`base / sm / md / lg / xl / 2xl`).
   - tutti i controlli scrivono varianti specifiche del breakpoint attivo.
5. **Variant chain UI**:
   - componente unico riutilizzato in Layout/Style/Animations/Advanced.
6. **Image control unificato**:
   - componente `MediaImageControl` con tutte le opzioni descritte in C.
   - usato sia per `image` block sia per Background→image sia per i campi media in altri blocchi.
7. **Localization**:
   - tutti i nuovi label/descrizioni passano da `t(...)` (builder) e `__()` (PHP registry).

---

## F. Fasi / priorità

### P0 — Fondamenta
- [x] Tab Animations introdotta nel framework controlli.
- [x] Background control ridotto a 4 modalità + media picker.
- [x] Image control unificato + focal point UI.
- [x] Variant chain builder + responsive switch globale.
- [x] Refit blocchi esistenti (A.1) sul nuovo schema universale.
- [x] **Overlay manager runtime** (open/close/toggle, stack, focus trap, scroll lock, ESC, click-outside, `prefers-reduced-motion`).
- [x] **Interactions engine** (Trigger → Action → Target con condizioni e modifiers) integrato nella tab Advanced di ogni blocco interattivo.

### P1 — Blocchi essenziali nuovi
- [x] icon, icon-box, icon-list.
- [x] image-box, image-gallery, basic-gallery.
- [x] tabs, accordion, toggle.
- [x] alert, progress-bar, counter, star-rating.
- [x] social-icons, share-buttons.
- [x] nav-menu, breadcrumbs, search-form, anchor.
- [x] posts-list, posts-grid, archive-posts, pagination.
- [x] form + form-field-* + form-submit.
- [x] modal, offcanvas, drawer + modal-trigger + slot header/body/footer/close.
- [x] popover, tooltip, dialog-confirm.

### P2 — Blocchi avanzati
- [x] image-carousel, content-carousel/slider.
- [x] call-to-action, testimonial, price-table, price-list.
- [x] flip-box, hotspot, before-after-slider, countdown.
- [x] animated-headline, marquee, lottie.
- [x] embed-google-maps, embed-iframe, code-highlight.
- [x] mega-menu, login-form, register-form, contact-form preset.
- [x] author-box, comments, comment-form, post-navigation.
- [x] popup (con trigger load/exit-intent/scroll/delay/click), notification-toast, cookie-banner, lightbox, command-palette.

### P3 — Nice-to-have
- [x] sitemap, table-of-contents.
- [x] scroll-progress, sticky-bar, back-to-top.
- [x] divider-fancy, spacer-responsive.
- [x] featured-posts, taxonomy-list.

---

## G. Sistema Overlay (modal / off-canvas / popover / popup) + Interazioni

Obiettivo: rendere modal/drawer/popover/popup blocchi a sé stanti, configurabili con
**template parts** per le sotto-aree, attivabili da qualunque elemento (button, link,
immagine, blocco custom) tramite un sistema dichiarativo di **trigger → action → target**.

### G.1 Modello dati di un overlay
Ogni overlay è un blocco di tipo `overlay` con varianti (`variant: modal | offcanvas | drawer | popover | popup | tooltip | lightbox | toast | command-palette`).

Attributi comuni:
- `overlayId` — slug univoco generato (es. `subscribe-modal`); usato come target dei trigger.
- `variant` — vedi sopra.
- `template` — preset di base selezionabile da dropdown (vedi G.2).
- `placement` — center / top / bottom / left / right / corner-{tl,tr,bl,br} (filtrato per variant).
- `size` — sm / md / lg / xl / full / arbitrary (w + h).
- `backdrop` — none / dim / blur / custom-color + opacity + click-to-close.
- `dismissible` — esc-to-close, click-outside-to-close, swipe-to-close (mobile).
- `focusTrap` — abilitato di default; restore focus all’elemento trigger alla chiusura.
- `scrollLock` — blocca scroll del body all’apertura.
- `mountTo` — `body` (portal) | `inline` (in-place).
- `openOn` — array di trigger automatici: `manual | load | delay:Nms | scroll:N% | exit-intent | inactivity:Nms | element-visible:#id | element-click:#id | route-match | query-param:key=value | cookie-absent:name`.
- `closeOn` — `esc | outside | route-change | submit-success | timeout:Nms`.
- `frequency` — `always | once-per-session | once-per-day | once-ever` (con cookie/localStorage).
- `transition` — preset (fade, slide-from-{side}, zoom, scale, flip) + duration + easing; rispetta `prefers-reduced-motion`.
- `a11y` — `role="dialog" | "alertdialog"`, `aria-modal`, `aria-labelledby` (id dell’header), `aria-describedby` (id del body).
- `zIndex` — stack ordinato automaticamente (overlay manager).

### G.2 Slot e contenuto
Un overlay espone slot strutturali che il blocco padre rende editabili come **InnerBlocks**:
- `slot:header` — area titolo + close button.
- `slot:body` — contenuto principale.
- `slot:footer` — azioni (es. ok/cancel, submit form).
- `slot:close` — pulsante chiusura (auto-inserito, sovrascrivibile).

**Modalità di editing primaria: inline.** Grazie all’editing dedicato dell’overlay nel
canvas (vedi G.5), ogni slot si compone direttamente con altri blocchi Blocky come
InnerBlocks. Non c’è quindi bisogno di un selettore "template part" nelle impostazioni
dello slot per l’uso normale.

**Riuso opzionale** (NON obbligatorio, NON nelle setting di default): per scenari di
riuso reale (es. stesso footer CTA su più modal), si può inserire dentro lo slot un
blocco esistente di tipo `wp-template-part` o un pattern sincronizzato. È sufficiente
fare “Insert block” come per qualsiasi altro blocco — niente UI dedicata, niente
"Header source: Inline / Template Part / Pattern" nelle setting.

**Templates di base** selezionabili al momento dell’inserimento del blocco overlay
(scaffold iniziale che popola gli slot con blocchi inline, modificabili poi liberamente):
- `blank` — solo body vuoto.
- `dialog` — header (titolo + close) + body + footer (cancel + confirm).
- `subscribe` — header + form newsletter + footer disclaimer.
- `auth` — login/register tabs.
- `media` — lightbox media.
- `cart` (drawer) — lista prodotti + totale + checkout.
- `menu` (offcanvas) — nav-menu mobile.
- `cookie` — banner consensi + preferenze.
- `command` — palette ricerca + lista azioni.

### G.3 Sistema Trigger → Action → Target (interazioni)
Qualunque blocco interattivo (button, link, image, icon, card, custom HTML, form-submit, anchor, nav item) espone nella tab **Advanced → Interactions** una lista di azioni. Ogni azione ha:
- **Event**: `click | hover | focus | submit | load | visible | scroll-to:N% | key:Cmd+K | timer:Nms`.
- **Action type**:
  - `overlay.open` / `overlay.close` / `overlay.toggle` (target = `overlayId`).
  - `overlay.openNext` / `overlay.openPrev` (per sequenze multi-step).
  - `scroll.toAnchor` (target = id ancora).
  - `tabs.activate` / `accordion.toggle` / `slider.next` ecc. (interazioni con altri blocchi).
  - `copy.toClipboard` (target = testo / valore campo).
  - `form.submit` / `form.reset` / `form.setField`.
  - `class.toggle` / `class.add` / `class.remove` (target = selettore / blocco).
  - `attribute.set` (target = selettore + attr + valore).
  - `cookie.set` / `localStorage.set` / `state.set` (chiave/valore).
  - `navigate` (URL + target tab) — rispetta nofollow/sponsored.
  - `media.playPause` / `media.seek`.
  - `custom.emit` (event bus, payload JSON) — per integrazioni third-party.
- **Conditions** (AND/OR):
  - device, login state, role, query var, cookie, time-range, frequency cap.
- **Modifiers**:
  - delay, debounce, throttle.
  - preventDefault, stopPropagation.
  - once.
- **Target selection** UI:
  - dropdown con tutti gli `overlayId` presenti nella pagina.
  - selector libero (CSS selector) per casi avanzati.
  - picker di blocchi (drag pin → click sul blocco target nel canvas).

Esempio flusso utente:
1. Inserisce blocco `modal` con `overlayId = subscribe-modal`, template `subscribe`.
2. Inserisce un blocco `button` “Iscriviti”.
3. Sul button → Advanced → Interactions → Add → `click` → `overlay.open` → target `subscribe-modal`.
4. Anteprima nel canvas: click sul button apre il modal (con focus trap, esc-to-close).

### G.4 Overlay Manager runtime
Un piccolo runtime JS (parte di `core-plugin` frontend e del preview iframe del builder):
- Registra tutti gli overlay presenti in pagina (data-attributes `data-bky-overlay`, `data-bky-overlay-id`, `data-bky-overlay-variant`).
- Espone API globale `window.Blocky.overlays.{open,close,toggle,isOpen}(id)`.
- Gestisce stack (z-index incrementale, chiusura inversa con ESC).
- Gestisce focus trap (basato su `tabindex` + sentinella).
- Gestisce scroll lock e padding compensativo per scrollbar.
- Bridge ai trigger: data-attributes `data-bky-action="overlay.open"` `data-bky-target="subscribe-modal"` + `data-bky-event="click"` (auto-binding).
- Rispetto `prefers-reduced-motion` per transizioni.
- Eventi DOM emessi: `blocky:overlay:open`, `blocky:overlay:close`, `blocky:overlay:beforeOpen` (cancellabile).

### G.5 Editing nel builder
- Pannello dedicato “**Overlays**” nel Sidebar/Outline che elenca tutti gli overlay della pagina (id, variant, stato).
- Click su un overlay → entra in **modalità edit overlay** (canvas mostra l’overlay aperto sopra la pagina; il resto della pagina è disabilitato/dimmed).
- Pulsante “Anteprima trigger” per simulare l’apertura tramite il trigger configurato.
- Toggle “Mostra overlay nel canvas” per ogni overlay (solo editing, non viene reso sempre aperto in frontend).
- Validazione: warning se un overlay non ha alcun trigger associato (eccetto `openOn=load/exit-intent/...`).

### G.6 Accessibilità (requisito non negoziabile)
- `role="dialog"` o `role="alertdialog"` (dialog-confirm).
- `aria-modal="true"` per varianti che bloccano l’interazione di fondo.
- Focus trap attivo + restore focus al trigger alla chiusura.
- ESC chiude (salvo `dismissible.esc=false`).
- Tooltip/popover: `role="tooltip"` / `aria-describedby`; mai trap di focus.
- Toast: `role="status"` o `role="alert"` + `aria-live`.
- Contrast check sul backdrop.

### G.7 Checklist di accettazione (overlay)
- [ ] `overlayId` univoco generato/editabile.
- [ ] variant + placement + size + backdrop + transitions configurabili.
- [ ] slot header/body/footer editabili **inline** come InnerBlocks (no selettore template part nelle setting).
- [ ] riuso via insert di `wp-template-part` / synced pattern come blocco normale dentro lo slot.
- [ ] trigger esterni funzionanti via `data-bky-action` (open/close/toggle).
- [ ] manager runtime con stack, focus trap, scroll lock, ESC, click-outside.
- [ ] `openOn` automatici (load/delay/scroll/exit-intent/inactivity/...).
- [ ] `frequency` cap rispettato (cookie/localStorage).
- [ ] a11y: role corretto, aria-*, restore focus, motion-safe.
- [ ] anteprima nel builder + editing slot funzionante.
- [ ] localizzazione completa (`t()` / `__()`).

---

## H. Checklist di accettazione per ogni blocco

Un blocco si considera “done” quando:
- [ ] espone tutte e 5 le tab (Content, Layout, Style, Animations, Advanced).
- [ ] ogni controllo supporta override per breakpoint e variant chain (dove applicabile).
- [ ] Background offre SOLO le 4 modalità (transparent / custom / gradient / image) con media picker WP.
- [ ] se gestisce immagini, espone l’intero schema C (focal point, alt, lazy, filters, hover swap, lightbox).
- [ ] le animazioni rispettano `prefers-reduced-motion`.
- [ ] tutti i label/descrizioni sono localizzati (`t()` / `__()`).
- [ ] il markup di output è semanticamente corretto e accessibile (aria, role, focus ring).
- [ ] il blocco è registrato sia nel builder sia nel registry PHP con metadati coerenti.
- [ ] copertura test minima: render base + render con varianti responsive.
- [ ] possiede tutti gli stili in style adatti e validi per personalizzare il tipo di blocco
