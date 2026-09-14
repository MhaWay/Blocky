# 08 — Gutenberg entry point and page conversion

## "Edit with Blocky" header button (Elementor-style)

- Enqueued only on the block editor screen (`enqueue_block_editor_assets`): a small ES
  module (`builder-plugin/src/gutenberg.ts`) inserts a button right after the back
  control in the editor header.
- Click flow: automatic drafts are saved first (`core/editor.saveDraft`), then
  `POST /blocky/v1/convert/{id}` runs and the browser redirects to the builder.
- Conversion is **non-destructive**: `post_content` is never touched. The converter only
  writes `_blocky_document` + `_blocky_editor=blocky` (same switch the Gutenberg overlay
  notice already respects), so the page can always go back to the block editor.

## Converter (`WpBlockConverter`)

- Pure core (`convertBlocks()`, unit-tested): parsed blocks → Blocky document.
- Mapped: heading, paragraph, image (attachmentId), buttons/button, columns
  (real `column-N` slots), group→section, list (ordered flag), quote (citation),
  separator, spacer (px→size steps).
- Everything else with children → section; leaf blocks → `bky/html` with the block's
  own rendered HTML (sanitized). Nothing is ever lost silently.
- The output document is validated by `PropsValidator` before it is persisted and the
  frontend cache is warmed (`PageCompiler::warmFrontendCache`).
- Known gap: `PropsValidator` does not enforce `required` schemas yet (paragraph needs
  `content` — converter maps it correctly, but the validator would not have caught it).
  Follow-up: enforce `required` + `enum` in the save contract.

## Tests

- `WpBlockConverterTest` (5 tests) incl. "converted document passes the real save
  validator".
- e2e `gutenberg-convert.spec.ts`: button visible in the header → click → builder URL →
  frontend renders heading/paragraph/list (fixture page id 115).
