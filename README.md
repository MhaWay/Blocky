# Blocky

A WordPress page builder built on Tailwind CSS 4 with a closed-set control
system: designers pick from graphic controls (selectors, ranges, choice
boxes) that can only produce **valid** Tailwind output. The frontend ships
plain static CSS + a ~6.5 KB gzip runtime — the performance profile of a
hand-written Tailwind page, editable in a GUI.

Goal: win where Elementor loses — performance *and* design quality.

## Packages

| Package | Role |
|---|---|
| `core-plugin` | WordPress runtime plugin: document engine, REST API, PHP renderers, closed-set CSS vocabulary |
| `builder-plugin` | Editor plugin: Preact builder (canvas, inspector, sidebar), served as separate plugin |
| `theme` | Blocky theme: theme.json + three-layer CSS studio build |
| `tokens` | Design tokens → CSS vars, Tailwind source, editor palette |
| `ui-primitives` | Shared UI primitives for the builder |
| `mcp-server` | Stdio MCP proxy: Claude/LLMs edit sites through scoped API keys |

## Quick start (local WordPress)

```sh
pnpm install
pnpm build
cd tools/local-wp && docker compose up -d && bash sync.sh   # WP at :8888, admin/admin123
```

Builder: `wp-admin/admin.php?page=blocky-builder&post_id=N`.

## CSS architecture (three layers)

- **L1 studio** — theme.json + tokens: what the designer's studio looks like.
- **L2 preview** — sandboxed editor preview CSS (managed by the builder).
- **L3 frontend** — a hashed, static, closed-set stylesheet per site
  (`uploads/blocky/`); never `wp_add_inline_style`, never arbitrary classes
  in the normal control path (code mode is a separate, async rebuild).

Rules and budgets live in `AGENTS.md` and `docs/research/` — the gate that
enforces them is `tools/ci/size-budget.sh` (runs in CI):

| Artifact | Budget (gz) | Today |
|---|---|---|
| Vocabulary CSS (L3) | 30 KB | ~25 KB |
| Runtime entry JS | 12 KB | ~6.5 KB |
| Theme stylesheet | 18 KB | ~15 KB |

`e2e/perf-budget.spec.ts` additionally asserts per-page request counts and
that no lazy chunk (highlight.js, lottie) loads unless the page needs it.

## Testing

```sh
pnpm typecheck && pnpm lint && pnpm test     # turbo: PHP unit + phpstan + JS
. /tmp/keys.env && BLOCKY_API_KEY=... npx playwright test --workers=1   # e2e
```

E2E needs a keyed API user (`wp blocky key create`), fixture posts
(`mcp-target`=11 shared, `builder-target`=9, `audit-grid`=12, `perf-target`=15);
`tools/block-audit/` holds the 100+ block catalog audits. See
`tools/block-audit/README.md` and issue #21 (fixtures-as-code for CI).

## MCP — let an LLM build pages

Scoped, revocable API keys (`wp blocky key create --scopes=...`, 60 req/min
per key) gate a REST MCP endpoint; `packages/mcp-server` is a stdio proxy
clients like Claude Desktop connect to. Setup: `packages/mcp-server/README.md`.

## Further reading

`docs/research/03..06` — current state, three-layer CSS design, strategy
decisions D1-D8, and the spike numbers behind every budget above.
