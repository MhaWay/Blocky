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

## Round-2 review wp.org (2026-09): quattro lezioni

1. **Activation hook nel bundle**: `register_activation_hook(__FILE__)` in blocky-core.php, copiato a
   `engine/engine.php` nel bundle, si aggancia a un file che NON e' il plugin attivato -> le tabelle
   api_keys/api_audit non nascevano su install pulita (bug reale, trovato dal review tool). Ora: costante
   `GGALLY_MAIN_PLUGIN_FILE` definita nel loader, hook registrata su quella. Test definitivo: drop tabelle
   via $wpdb -> `wp plugin activate` -> le tabelle ricompaiono. Attenzione: `wp db query` nel container
   pulito non ha il client mysql, e lo stato tra tentativi falliti puo' ingannare — sempre ripartire da zero.
2. **gettext con variabili**: i wrapper tipo `tr($text)` NON sono trasparenti al POT extractor — il
   parser legge il codice, non lo esegue. Fix: 190 label di registro convertite a `__('Literal','blocky')`
   inline; catalog.php mai-eseguito con 337 letterali copre le stringhe annidate localizzate a runtime.
   Le stringhe user-supplied (label form) NON vanno nei gettext: solo `esc_html($val)`.
3. **URL delle sottocartelle**: il trucco `site_url('wp-content' . substr(path))` per seguire site_url a
   runtime era peggio del problema (spezza WP_CONTENT_DIR custom, gia' gestito da plugin_dir_url()). Due
   righe tolte, via PHPStan-free: e' il bootstrap.
4. **Naming global**: una variabile locale al bootstrap ma a global scope (`$bkyMain`) genera warning
   PC NonPrefixedVariableFound. Regola: anche le variabili dei file bootstrap usano il prefix.



## Round-3 review wp.org (2026-09): sei lezioni brevi

1. **Le cap di rotta contano sulle rotte che CREANO oggetti.** Le write post-scoped erano
   giagate bene (meta-cap edit_post via Access::allowed_post), ma POST /library creava una
   pagina nuova con la sola edit_posts: un author NON puo creare pagine → serve edit_pages
   (e delete_pages per la libreria). Regola: se la rotta crea/elimina contenuti di un post
   type X, usare le primitive map del post type, non un proxy generico.
2. **Auth cookie senza nonce: le scritture rispondono 401.** I test REST fatti a mano via
   curl/requests devono estrarre il nonce dalle pagine admin (rest_nonce) e mandare
   X-WP-Nonce; chi lo dimentica vede 401 e crede siano cap rotte.
3. **wp eval + rest_do_request e inaffidabile** (404 spurii, contesto CLI): testare via HTTP
   vero, browser-like.
4. **Anche UN solo __( $var ) superstite viene segnalato.** Fix onesto: apply_filters(
   'gettext', $text, $text, 'domain' ) (che e esattamente cio che __ fa), con la catalog
   letterale che resta unica fonte per il POT.
5. **Output da hook altrui → wp_kses_post** nel renderer hook: hardening da una riga, zero
   regressioni nei 45 e2e.
6. **Versione: ogni upload deve averla nuova. Changelog readme completo** (0.1.1 era sparito!).
   Tables create-on-activate = api_keys/api_audit via dbDelta; i documenti stanno in postmeta:
   non descrivere mai in reply tabelle che il codice non crea.

## Round-4 review wp.org (2026-09): tre lezioni brevi

1. **Niente inline script nei renderer.** Otto renderer emittevano <script> DOM-ready + un
   <style> per i keyframes di Marquee: tutti rimossi. Il DOM resta nei renderer, la logica
   vive in src/islands.ts, bundled nel modulo enqueueato blocky-core. roots() usa il selettore
   + ':not([data-bky-id])' per non toccare il canvas dell'editor. I keyframes bky-marquee
   ora stanno in styles/animations.css (importata dal CSS generato, quindi enqueue pulita).
   Attenzione: se un upgrade lascia render cache vecchie in DB, quelle contengono ancora
   inline script — il compile-hash non copre il codice PHP, quindi va considerato l'attr
   di versione nel payload della cache in futuro (todo: cache-key v3).
2. **Nonce sulle letture sensibili.** selectedFormSubmission() leggeva $_GET['submission_id']
   senza nonce: ora l'URL admin e' generato da wp_nonce_url() e validato con
   wp_verify_nonce(..., 'blocky_view_submission') prima di leggere qualunque input.
3. **Prefix >= 4 caratteri.** 'bky_' era troppo corto: rinominati transient (ggapb_rate_),
   post type (ggapb_form_entry, con migrazione one-shot $wpdb->update + cache flush all'activate),
   cookie e chiavi (ggapb_live_/ggapb_brand/ggapb_mode). Le chiavi legacy 'bky_live_*' restano
   verificabili (doppio prefisso, nessuna rottura per i key file esistenti). 'blocky_' e'
   >= 7 caratteri quindi ammessa, e gli handle/hooks non sono stati rinominati.

## Round-4 follow-up (v0.1.4) — cache invalidation across upgrades

When renderer output changes (e.g. inline script/style removed in favour of the
enqueued blocky-core runtime), sites upgrading would keep serving the old cached
HTML forever: PageCompiler::cachedHtml() returns the stored string with no
version check. Fixes shipped in v0.1.4:

- documentHash() mixes BLOCKY_CORE_VERSION into the compile hash, so an engine
  bump can never match a stored hash.
- cacheIsFresh(postId, document) re-validates the stored _blocky_compile_hash on
  the frontend read path (Plugin.php), so stale cache is not served even when
  the document is unchanged.
