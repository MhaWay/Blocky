# Blocky — Working Guide (agent + dev)

Monorepo pnpm/turbo: @blocky/core-plugin (motore PHP), @blocky/builder-plugin (editor React), @blocky/tokens, @blocky/theme, @blocky/ui-primitives.

## Leggi prima di toccare

- docs/research/11-wp-security-handbook-compliance.md — sicurezza WP: regole, mappa compliance, guard ABSPATH su ogni file spedito
- docs/research/03-blocky-current-state-and-proposals.md — stato attuale + debolezze W1-W9
- docs/research/04-three-layer-css-and-control-system.md — architettura target (L1 studio / L2 preview / L3 frontend)
- docs/research/06-strategy-stack-decision.md — decisioni D1-D8 (chiuse: no classi arbitrarie percorso normale, closed-set, one-engine binario, no inline CSS, system fonts default)
- docs/research/05-spike-s1-results.md — numeri e budget CI: L1<=30KB gz, L3<=35KB gz, runtime<=12KB gz

## Comandi

- pnpm install / pnpm build / pnpm test / pnpm lint / pnpm typecheck (turbo)
- pnpm wp-env start (WP locale) — docker-compose alternativo
- tokens: node packages/tokens/scripts/build.ts (puro node, zero deps)
- CSS spike: tailwindcss CLI standalone 4.3.3 (versione pinnata con tailwindcss npm)

## Regole non negoziabili

1. Nessuna classe Tailwind arbitraria nel percorso controlli: solo vocabolario closed-set + canale CSS-vars (twStyleVars). Le classi arbitrarie esistono solo in "code mode" con rebuild asincrono.
2. Frontend: CSS solo da file statici con hash (uploads/blocky/, uno per pagina); inline CSS solo come fallback di migrazione (pagina mai ricompilata), mai come percorso primario; MAI Google Fonts by default.
3. Documento JSON: ogni modifica = doc-version + migrazione esplicita; parser unico PHP valida save/REST/agent.
4. Un solo motore CSS: il pacchetto npm `tailwindcss` bundled nel builder (compile in browser: preview + salvataggio). MAI binari CLI scaricati o eseguiti lato server (violazione directory wp.org, rimozione 2026-09 — vedi docs/research/11).
5. Ogni PR: non far peggiorare i size budget; new blocks = descriptor, non codice ispettore bespoke.
