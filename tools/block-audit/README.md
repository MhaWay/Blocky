# block-audit

One-shot Node ESM audit scripts for the 100+ block catalog. They hammer the
live WordPress stack (tools/local-wp) through the REST API + Playwright and
screenshot everything, so they can only run while that stack is up.

## Requirements

- Stack running: `tools/local-wp` (`docker compose up -d`, `bash sync.sh`).
- An API key with `catalog:read,documents:read,documents:write`:
  `wp blocky key create ...` inside the wp container, then
  `export BLOCKY_API_KEY=bky_live_...`.
- Run from the repo root (`@playwright/test` resolves from the repo):
  `node tools/block-audit/audit-frontend.mjs` etc.
- The REST layer throttles to 60 requests/min per key: the scripts pace
  themselves at ~1050ms/request and back off 62s on 429 `blocky_rate_limited`.

## Fixtures

Scripts write documents to dedicated fixture posts so they never trash real
pages: post 11 (`/f3-fixture/`, undo/save specs) and post 12 (`/audit-grid/`,
audits). Recreate them once per fresh database:

```sh
wp post create --post_title='F3 Fixture' --post_name=f3-fixture --post_status=publish --porcelain
wp post create --post_title='Audit Grid' --post_name=audit-grid --post_status=publish --porcelain
```

## Scripts

- `audit-render.mjs` — for every catalog type, save a minimal document via
  REST and compare the server-rendered page against the save response
  (normalized: editor attrs/classes stripped). Flags frontend/save divergence.
- `audit-inspector.mjs` — for every type, insert in the builder via the
  `window.BlockyBuilderDebug` handle and screenshot the inspector
  (`/tmp/audit-shots/insp-<slug>.png`). Catches broken/missing controls.
- `audit-frontend.mjs` — for every type, save with generated rich props and
  screenshot the frontend (`.entry-content` → `/tmp/audit-shots/front-<slug>.png`),
  failing on console errors / 404s. Catches runtime-asset and render bugs.

Findings so far were fixed upstream: runtime chunk base (`vite.config.ts`
base), the unlayered theme.json link color, code-highlight, and the
`listFields` structured editor for pipe-protocol props.
