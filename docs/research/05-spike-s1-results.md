# Spike S1 — Risultati (eseguito 2026-09-12, ambiente Linux aarch64, node 24, tailwind 4.3.3)

## Setup
- Binario standalone `tailwindcss-linux-arm64` v4.3.3 dalle release ufficiali + npm `tailwindcss@4.3.3` installato side-by-side.
- Entry testati in `/tmp/spike/`:
  - **B**: replica del vocabolario builder = 412 righe `@source inline()` prese da `preview.css` + `tailwind-utility-safelist.css` correnti (no scan PHP)
  - **C**: solo `@source core-plugin/src/**.php` (proxy del vocabolario statico dei 70 renderer) — stima del floor di L3
- Tokens regolari buildati dallo script repo (puro node, zero deps — buon segnale).

## Numeri
| Artefatto | raw | gzip | Note |
|---|---|---|---|
| Studio completo (vocabolariobuilder attuale) | 2.31 MB | **209 KB** | I 412 inline() correnti sono una cucina esagerata (scale numeriche complete × varianti). Oggi il canvas viaggia su quest'ordine di grandezza. |
| Safelist directive attuale (file in repo) | 542 KB | 86 KB | È il *sorgente* delle direttive, non la CSS compilata. |
| **L3 proxy: solo classi dei renderer PHP** | **46 KB** | **8.9 KB** | Il pavimento: vocabolario statico di tutti i blocchi. Il CSS reale di un sito = questo + chiusure dei controlli (closed-set), che P2 definirà. Anche 3-4× resta su 25-35KB gzip **un solo file per tutto il sito**. |

## Equivalenza motori (one-engine test)
- CLI senza `--minify` vs npm JS API, stesso input, stessa versione: **stesso output semantico**, byte-differenze solo cosmetiche (quote singole vs doppie nei font-stack — il CLI passa da lightningcss anche senza minify: 2.956.881 vs 2.956.889 byte, prima differenza a byte 213 sui font di preflight).
- Conclusione: **un solo motore è sicuro**: il CLI/binario come fonte canonica; la compile npm-in-browser resta lecita SOLO come overlay di preview (mai artefatto).

## Implicazioni PSI (PageSpeed come HTML statico con Tailwind)
Ciò che rende una HTML+Tailwind statica un 100/100 non è il Tailwind in sé, è il **profilo di asset**: 1 CSS piccolo cacheabile, zero JS, font di sistema, niente query. Il progetto L3 deve replicare quel profilo, non solo il formato CSS:
1. **LCP**: HTML da cache (object cache, non post meta — W6) + 1 file CSS sito (8-35KB gz) render-blocking ma minuscolo → niente Async-CSS/trucchi; long max-age + hash immutabile (CDN friendly).
2. **Fonts — il tranello classico**: `typography.json` referenzia `Inter` e `JetBrains Mono` ma **nessun @font-face/caricatore esiste** → oggi cade silenziosamente su system-ui. Decisione presa qui: **default = sistema** (`ui-sans-serif, system-ui...`) identico alle pagine statiche; Inter/JetBrains solo opt-in **self-hosted** (woff2 + `preload` + `font-display:swap` + subset), MAI Google Fonts (il modo più rapido di perdere 20 punti LCP).
3. **CLS**: nessuna FOUC (CSS piccola, normale link in head); utility `aspect-ratio`/`object-fit` già nello schema immagine (todo §C) — imporre il default aspect-ratio sui blocchi media.
4. **INP**: runtime JS solo se la pagina ha islands (flag `interactive` del registry c'è già — manca l'enqueue condizionale). HTML cachizzato: zero PHP per vista.
5. **TTFB**: HTML in object cache + CSS file statico = profilo quasi-statico. Attenzione a non ri-riscaldare la cache con WP-Cron a ogni richiesta.

Budget CI proposti (size-limit + Lighthouse CI):
- L3 CSS sito ≤ 35KB gzip; delta per pagina ≤ 5KB; 0 byte CSS inline; runtime JS ≤ 12KB gzip, condizionale; 0 font di rete nel default theme.
- L1 studio: budget separato (è editor-only): ≤ 80KB gzip dopo la potatura (oggi 209: potare le inline() alle sole scale usate dai controlli P2, non gli spaghett-ranges di 100..900).

## Prossimi passi
- S2 (subito possibile): potare i 412 inline() al vocabolario Control Kit e rimisurare L1.
- P1: binario in plugin + coda + file uploads + de-inline + hash-verify.
- Font: decidere la policy (sopra) e fissarla nei tokens (modificare core/typography.json → default system).
- Benchmark Elementor: misurare la per-post CSS di una pagina Elementor standard (richiede un WP locale: wp-env o docker del repo).
