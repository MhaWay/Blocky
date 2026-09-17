# 12 — Matrice di parità Elementor free (e oltre) — 2026-09-16

Fonte: lista ufficiale wp.org/plugins/elementor (114 li) + lista widget canonica free. Contro inventario reale
blocky: 102 tipi bky/\* registrati in Registry.php, 84 renderers (verificato via grep, non a memoria).

Legenda: ✓ coperto · ⚠ parziale/via alternativa · ✗ assente

## Widget contenuti

heading ✓ · image ✓ · text ✓ · button ✓ · image-box ✓ · icon ✓ · icon-box ✓ · alert ✓ · quote ✓ ·
divider ✓ · spacer ✓ · counter ✓ · progress-bar ✓ · star-rating ✓ · testimonial ✓ · flip-box ✓ ·
animated-headline ✓ · table-of-contents ✓ · call-to-action ✓ · card ✓ · html ✓ · code-highlight ✓

## Media ed embed

video ✓ · embed-google-maps ✓ · embed-iframe ✓ (copre SoundCloud/Facebook/Twitter embed) ·
image-gallery ✓ · basic-gallery ✓ · image-carousel ✓ · slider ✓ · content-carousel ✓ ·
before-after-slider ✓ · hotspot ✓ · lottie ⚠ (unico runtime JS, lazy — accettabile by-design) ·
lightbox ✓ · gallery-carousel ✓ · text-path ✗ (SVG/JS, fuori DNA — valutare se serve davvero)

## Navigazione e struttura

nav-menu ✓ · mega-menu ✓ · anchor (menu anchor) ✓ · breadcrumbs ✓ · pagination ✓ · back-to-top ✓ ·
scroll-progress ✓ · sticky-bar ✓ · post-navigation ✓ · search-form ✓ · sitemap ✓

## Overlay e interazione (il LORO Pro, da noi free)

popup ✓ · modal ✓ · modal-trigger ✓ · offcanvas ✓ · drawer ✓ · dialog-confirm ✓ ·
notification-toast ✓ · tooltip ✓ · popover ✓ · cookie-banner ✓ (Elementor non ce l ha free)

## Tabs/accordion

tabs ✓ · accordion ✓ · toggle ✓

## Form (il LORO Pro, da noi free e illimitato)

form ✓ · form-fields completi (text/textarea/select/radio/checkbox/date/file/hidden/honeypot) ✓ ·
form-submit ✓ · submissions private in WP ✓ · login-form ✓ · register-form ✓ · contact-form ✓ · comment-form ✓

## Contenuti dynamic WP (primitive theme builder, già presenti come elementi)

posts ✓ (posts-grid/posts-list/archive-posts/featured-posts) · wp-post-title ✓ · wp-post-content ✓ ·
wp-featured-image ✓ · author-box ✓ · comments ✓ · wp-shortcode ✓ · wp-template-part ✓ · wp-hook ✓ ·
theme-toggle ✓ (loro non nativo) · data-field ✓ · taxonomy-list ✓

## Listini

price-list ✓ · price-table ✓ · PayPal-button ✗ (scelta: dipendenza esterna, fuori roadmap)

## Icone

icon/icon-box/list ✓ MA libreria icone gestita (carica/SVG set/selettore) ⚠ — gap 1

## What Elementor free claims vs noi (strutturale, non feature)

- Reduced DOM output: noi by-design (renderer PHP minimi, verificabile in e2e)
- Speed improvement claims: CSS compilato per pagina hashato, zero JS di default — misurabile
- No Google Fonts by default · i18n UI completa 6 lingue · build deterministica/CI · API REST+key/MCP

## I VERI gap strutturali (tutto il resto è descriptor work)

1. **Theme builder template system** (header/footer/archive/single/404 come template con condizioni di
   display): le PRIMITIVE c esistono gi (wp-\* + nav-menu + condizioni del builder menu), manca il CRUD UI
   dei template-part e le conditions. È il pezzo che rende il confronto possibile con Elementor Pro.
2. **WooCommerce** (loro pagina lista ~15 widget prodotto free/pro): 0 coverage. Il gap singolo
   più grande. Sottoinsieme ragionato: product-grid, add-to-cart, gallery, meta — su islands runtime.
3. **Icon library gestita** — FATTA (feat/icon-library): 1838 Lucide (ISC) via manifest JSON nel
   plugin, endpoint GET/POST blocky/v1/icons, upload SVG con sanitizer DOM allowlist proprio
   (wp_kses_svg NON esiste nel core: verificare sempre prima di citarlo), controllo inspector
   closed-set 'icon' (picker+cerca+upload+glyph legacy), renderer inline SVG con classe lucide-{name}.
   Il core blocca gli upload SVG di default: filtri upload_mimes temporanei attorno alla sola
   scrittura gia sanificata.
4. **Template/Pattern library cloud** — v1 FATTA (feat/pattern-library): 5 section pattern
   bundled (JSON deterministico in templates/patterns/, validati contro il Registry) + pannello
   sidebar Patterns con insert a fine pagina (cloni con id freschi) e salvataggio della pagina
   corrente come pattern utente (option blocky_user_patterns, cap 30, DELETE solo per user-\*).
   Attenzione ingegneristica: i default dei columns salvano gli slot column-N con indici
   1-based (column-1..): i pattern con column-0 non vengono renderizzati (ColumnsRenderer
   itera 1..count). Il cloud/lead-gen resta idea di business, non codice.
   Il core blocca gli upload SVG di default: filtri upload_mimes temporanei attorno alla sola
   scrittura gia sanificata.

## Verdetto numerico

Sulla lista FREE core Elementor: copertura attuale ~90% (su ~55 widget significativi: ~49 ✓, 2 ⚠,
2 ✗ di cui 1 scelta deliberata). "Fare tutto il free" non è una roadmap di anni: è una roadmap di
_descriptor work_ + i 4 gap strutturali sopra. Il free lo abbiamo gia quasi intero e con 3 cose
che loro vendono (popup/forms/overlay) e una (theme-toggle) che non hanno. Il differenziale
vincevole resta il layer 3: CSS per pagina, zero JS, misurabile.

## Non fare

Clone marketing-suite/A-B/maintenance; PayPal/Stripe buttons; text-path; qualsiasi cosa che
reintroduca JS o CSS inline by-default (regole 2/4).
