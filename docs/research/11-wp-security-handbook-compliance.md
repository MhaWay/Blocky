# WP Security Handbook — distillato e mappa di compliance

Fonte: https://developer.wordpress.org/apis/security/ (capitoli: common-vulnerabilities, data-validation,
escaping, sanitizing, nonces, user-roles-and-capabilities, example — trucco: ?output_format=md da URL da markdown pulito).
Questo file e' la memoria permanente del progetto sulla sicurezza WP: rileggerlo prima di toccare codice che
legge input, produce output, parla col DB o decide accessi.

## Le regole, ridotte all'osso
1. **Escaping all'OUTPUT, contestuale** (non al save): esc_html/esc_attr per testo/attributi, esc_url per URL,
   esc_url_raw/redir solo quando si salva o si redirecta. Una stringa passata a un template non e' mai fidata.
2. **Sanitizzazione all'INPUT**: sanitize_text_field/sanitize_key per dati semplici, whitelisting whenever
   possibile (in_array su un set chiuso > pattern inventati). Il nostro closed-set dei controlli e' esattamente
   questa regola applicata all'architettura.
3. **Nonce per OGNI azione di stato** (form, admin-post, AJAX, link che cambiano stato). Il nonce dimostra
   l'intento dell'utente; NON autorizza: servono SEMPRE anche current_user_can/capabilities. REST:
   permission_callback obbligatorio su ogni route.
4. **SQL**: sempre $wpdb->prepare con placeholder; i nomi tabella dinamica si costruiscono da costanti
   $wpdb->prefix (+ phpcs:ignore motivato), mai da input.

## Mappa di compliance del bundle (verificata 2026-07, Plugin Check = 0 errori)
- REST: 19 route, tutte con permission_callback (capability check nel callback).
- Nonce: admin-post handler (allow-engine, actions forms), wp_verify_nonce nei 4 handler di stato; link
  di stato via wp_nonce_url.
- Capabilities: 26+ controlli current_user_can; le pagine admin richiedono manage_options/edit_posts.
- Output: esc_* inline ovunque negli echo admin (risolti 30+ flag PHPCS con esc_url/wp_kses_post inline,
  NON con variabili gia' scappate: PHPCS non si fida dell'assegnazione, fidarsi solo della chiamata inline).
- DB: $wpdb->prepare ovunque; unica tabella custom (api keys) con nome da prefisso+costante e ignore motivato.
- File remoti: download binario Tailwind solo dopo consensi esplicito (Setup), checksum sha256, cache fuori
  da uploads (wp-content/blocky-engine). frontend: zero richieste esterne.
- **Guard ABSPATH su TUTTI i 123 file PHP spediti** (2026-07): inserita DOPO namespace (o declare, o header
  docblock se manca il namespace) — l'ordine legale PHP e': docblock -> declare(strict_types) -> namespace ->
  statements. Mettere la guard prima di declare o namespace = fatal ("must be the very first statement").
  Script di riferimento: pattern in questa history (strip-guard-then-insert-after-anchor).

## Falsi positivi noti acceptati nel PC (warning, non errori)
- DynamicHooknameFound in WpHookRenderer (hook names derivano da nomi blocco registrati — set chiuso).
- WordPress.DB.PreparedSQL.NotPrepared sulla tabella costante (ignore inline).
- MissingVersion su un enqueue di style statico del builder (hashed filename nel bundle prod).

## Da tenere d'occhio (review futura)
- Ogni nuovo admin-post/AJAX handler nasce con nonce + capability, senza eccezioni.
- Ogni nuovo file PHP spedito nasce con la guard.
- Ogni nuova echo in admin: escape inline nella stessa riga (stile ormai uniforme nel repo).


## Traduzioni bundle: lezione dura (2026-07)
PC segnala load_plugin_textdomain come deprecated, ma l'auto-load di WP copre SOLO le traduzioni di
translate.wordpress.org e wp-content/languages/plugins/. Le .mo BUNDLE dentro il plugin, senza la chiamata,
NON vengono caricate: verificato con probe (locale it_IT forzato via mu-plugin): senza call stringhe inglesi,
con call stringhe italiane. Nel bundle la chiamata viene riscritta dallo script di build per puntare a
languages/ top-level (domain = slug, .mo engine+builder deduplicate). Il warning PC e' un falso positivo
documentato: la funzione e' scoraggiata solo quando le traduzioni non le distribuisci tu.
