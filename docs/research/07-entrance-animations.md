# 07 — Entrance animations (closed set)

Feature: preset entrance animations for every block/content node, one shared `animation` prop.

## Contract

- Prop `animation` (object): `preset` (closed set of 15), `trigger` `scroll|load` (default
  `scroll`), `speed` `fast|normal|slow` (300/600/1000ms), `delay` steps
  `0|100|200|400|600|800ms`, `repeat` bool (replay on viewport re-entry).
- Presets: fade-in, fade-up, fade-down, fade-left, fade-right, zoom-in, zoom-out,
  slide-in-up, slide-in-down, slide-in-left, slide-in-right, flip-up, flip-down,
  bounce-in, blur-in.
- PHP `RenderContext::animationAttributes()` is the single validator: anything off the
  closed set renders as no animation (same pattern as `interactions`, AGENTS rule 1).
  It emits `data-bky-anim{,-trigger,-speed,-delay,-repeat}` in both frontend and
  editor renders (the canvas previews live).
- Runtime (`core-plugin/src/index.ts`) adds `html.bky-anim-ready` then IntersectionObserver
  toggles `.bky-anim-in`; CSS lives in `src/styles/animations.css` (bundled into the
  hashed runtime chunk, not L3 page CSS).

## Accessibility / resilience

- Hidden states exist only under `html.bky-anim-ready`: no-JS visitors always see content.
- `prefers-reduced-motion: reduce`: the runtime never sets the class and the CSS also
  forces visible — double safety.

## Budget

- ~1.3KB added to the runtime chunk (JS+CSS, gz), L3 page CSS untouched.

## Limits / follow-ups

- Preset-level only; no per-keyframe authoring (that stays code-mode territory).
- Canvas preview relies on the builder re-render (attr change => new element => re-observe).
- Candidate follow-up: staggered group animations; exit animations; `Blocky.animations.replay()`
  already exposed for previews/editors.
