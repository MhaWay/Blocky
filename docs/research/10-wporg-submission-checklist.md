# 10 — WordPress.org submission checklist (Blocky)

Slug desiderato: **blocky-page-builder** (verificato libero il 2026-09-14: restituisce la pagina di ricerca).
Lo slug `blocky` è occupato da "Blocky! – Additional Content Blocks" (abbandonato dal 2015, 10 install) → rischio collisione nome basso, ma il nome espositato proposto è **Blocky – Page Builder** per distanza sufficiente.

## Stato preparazione

| Voce | Stato |
|---|---|
| Dominio testuale unico + i18n | ✅ 5 lingue + POT (PR #46) |
| Licenza GPL-2.0+ (LICENSE nei plugin) | ✅ |
| readme.txt formato wp.org | ✅ (Tested up to: 7.1, Requires PHP: 8.2 — allineati a CI/requisiti reali) |
| Icona + banner wp.org | ✅ assets/wporg/ (icona V3 blu: palette "trust blue", 128/256/512, banner 772x250 + 1544x500, SVG sorgenti inclusi) |
| Gates CI (phpstan L8, phpunit, e2e 35/35) | ✅ |
| Zip pulite (no node_modules/tests/dev) | ✅ workflow release.yml corretto (PR #47) |

## Decisioni Aperte (bloccanti per l'invio)

1. **One plugin vs due plugin.** wp.org approva un plugin per slug. Opzioni:
   - **A (raccomandata): bundle singolo** "Blocky – Page Builder" = zip unico con loader che include motore e builder. Un solo zip da mantenere, installazione più semplice per gli utenti (metodo Elementor).
   - B: due plugin con slug separati (blocky-page-builder + blocky-page-builder-engine). Legato più debole ma più complessità.
2. **Screenshot reali** richiesti dal readme: servire screenshot-1..N (builder vuoto + pattern, pannello contenuti, ispettore, animazioni d'ingresso, frontend di una pagina). Da produrre dopo i P0 UX (starter patterns) per non fotografare un canvas vuoto.

## Procedura d'invio (al momento dell'approvazione delle decisioni)

1. Costruire la zip del plugin finale (stesso flusso di release.yml, slug blocky-page-builder, header Name: "Blocky – Page Builder").
2. Account wordpress.org dell'utente (MhaWay) → https://wordpress.org/plugins/upload-plugin/ caricare zip + agree GPL.
3. Compilare form: slug proposto, svn richiesto; attesa reviewer (tipicamente giorni, a volte settimane per page builder — security review manuale).
4. Dopo l'approvazione: import in SVN del trunk, pubblicazione tag 0.1.0, poi asset (icon/banner) in assets/.
5. Preparare risposte ai reviewer comuni: sanitization/escaping (esistono), nonce/permessi REST (esistono), nessuna telefonata a casa (verificare: nessuna chiamata di telemetria), nessuna clausola non-GPL, nessuna dipendenza Google Fonts by default (✅ regola di progetto).

## Note

- I cataloghi IT/DE/ES/FR/PT-BR vanno dichiarati "awaiting native review" nel readme fino a revisione; GlotPress disponibile dopo la pubblicazione.
- Mock di vetrina in /tmp/icon/mock.png contiene numeri fittizi (recensioni/installazioni) solo a scopo di prova: NON usare in materiali pubblici.

## Rebrand (2026-09-14)

Brand pubblico: **Gennaker™ – Page Builder** (GG-Ally). Verdetto finale dopo l'analisi dei marchi: "Blockwork" era pulito in classe 9/42 ma debole; "Genna" occupato da SaaS USA attiva; "Gennaker" (la vela che incanala il vento, coerente con Tailwind) è risultato libero su ogni fronte: wp.org slug, npm, GitHub (~2 stelline), gennaker.com e .it registrabili. Motivo: "Blocky" debole, affollato
(wp.org, DNS proxy 6.9k stelle, npm) e SEO invincibile; TMview mostra marchi "Blockwork" vivi solo
in classi 28/41 (giocattoli/intrattenimento). Attesa: screenshot TMview filtrato classi 9/42 come
verdetto finale. Verifica DPMA su "Blockwork Studio" (DE, 25/35/42) non automatizzabile.

- Fase 1 (QUESTA PR): solo stringhe visibili, header plugin, readme, banner, cataloghi, e2e.
- Fase 2 (dopo approvazione wp.org): identifier interni (namespace Blocky\*, text domain 'blocky',
  slug admin page, meta keys _blocky_*, uploads/blocky/, window.Blocky*) → migrazione doc-version.
