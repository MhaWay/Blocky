import type { FunctionComponent, JSX } from 'preact';
import { useEffect, useRef } from 'preact/hooks';
import { useDocumentStore } from '../store/document';
import type { GridPlacement, InsertPosition, MoveDirection } from '../store/document';
import { useUiStore } from '../store/ui';
import { useThemeStore } from '../store/theme';
import type { BlockInsertPreset } from '../sdk/types';
import { BLOCKY_THEME_DARK_TOKENS_CSS, BLOCKY_THEME_TOKENS_CSS } from '../tailwind/pageCssCompiler';
import { t } from '../i18n';

export const Canvas: FunctionComponent = () => {
  const document       = useDocumentStore(s => s.document);
  const previewHtml    = useDocumentStore(s => s.previewHtml);
  const previewCss     = useDocumentStore(s => s.previewCss);
  const selectedId     = useUiStore(s => s.selectedNodeId);
  const previewOverlayNodeId = useUiStore(s => s.previewOverlayNodeId);
  const previewThemeMode = useUiStore(s => s.previewThemeMode);
  const previewFrameNonce = useUiStore(s => s.previewFrameNonce);
  const selectNode     = useUiStore(s => s.selectNode);
  const hoverNode      = useUiStore(s => s.hoverNode);
  const createPost     = useDocumentStore(s => s.createPost);
  const removeBlock    = useDocumentStore(s => s.removeBlock);
  const duplicateBlock = useDocumentStore(s => s.duplicateBlock);
  const moveBlock      = useDocumentStore(s => s.moveBlock);
  const insertBlock    = useDocumentStore(s => s.insertBlock);
  const moveBlockTo    = useDocumentStore(s => s.moveBlockTo);
  const updateProps    = useDocumentStore(s => s.updateProps);
  const deviceMode     = useUiStore(s => s.deviceMode);
  const themeCss       = useThemeStore(s => s.css);

  // Listen to postMessage from the canvas iframe (select / hover / actions)
  useEffect(() => {
    const handler = (event: MessageEvent) => {
      const data = event.data as {
        type?: string;
        id?: string | null;
        direction?: MoveDirection;
        blockType?: string;
        targetId?: string;
        position?: InsertPosition;
        slotName?: string;
        preset?: BlockInsertPreset;
        gridPlacement?: GridPlacement;
        mediaType?: string;
        mediaReturn?: string;
        propKey?: string;
      };
      if (!data || typeof data.type !== 'string' || !data.type.startsWith('bky:')) return;
      switch (data.type) {
        case 'bky:select':    selectNode(data.id ?? null);                                       break;
        case 'bky:hover':     hoverNode(data.id ?? null);                                        break;
        case 'bky:delete':    if (data.id) removeBlock(data.id);                                 break;
        case 'bky:duplicate': if (data.id) duplicateBlock(data.id);                              break;
        case 'bky:move':      if (data.id && data.direction) moveBlock(data.id, data.direction); break;
        case 'bky:insert':
          if (data.blockType) insertBlock(data.blockType, data.targetId, data.position, data.slotName, data.preset, data.gridPlacement);
          break;
        case 'bky:move-to':
          if (data.id && data.targetId && data.position) moveBlockTo(data.id, data.targetId, data.position, data.slotName, data.gridPlacement);
          break;
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [selectNode, hoverNode, removeBlock, duplicateBlock, moveBlock, insertBlock, moveBlockTo, updateProps]);

  if (!document) {
    return (
      <div class="flex h-full flex-col items-center justify-center gap-6 text-text-muted">
        <div class="flex flex-col items-center gap-2">
          <p class="text-lg font-semibold text-text-base">{t('canvas.noDocumentLoaded', 'No document loaded')}</p>
          <p class="text-sm">{t('canvas.noDocumentDescription', 'Create a new page or open an existing post.')}</p>
        </div>
        <button
          type="button"
          onClick={() => void createPost()}
          class="rounded-button bg-accent-base px-5 py-2 text-sm font-medium text-text-on-accent hover:bg-accent-hover"
        >
          + {t('canvas.newPage', 'New Page')}
        </button>
      </div>
    );
  }

  const deviceWidth =
    deviceMode === 'mobile' ? '390px' :
    deviceMode === 'tablet' ? '820px' :
    '1200px';
  const deviceMax =
    deviceMode === 'desktop' ? '1200px' : deviceWidth;
  const deviceMin =
    deviceMode === 'desktop' ? '1200px' : deviceWidth;

  return (
    <div class="relative h-full overflow-auto bg-surface-base p-8">
      <div
        class="relative mx-auto rounded-card border border-border-subtle bg-white shadow-sm overflow-hidden transition-all duration-200"
        style={{ maxWidth: deviceMax, minWidth: deviceMin, width: deviceWidth }}
      >
        <CanvasFrame html={previewHtml} previewCss={previewCss} selectedId={selectedId} previewOverlayNodeId={previewOverlayNodeId} themeCss={themeCss} previewThemeMode={previewThemeMode} previewFrameNonce={previewFrameNonce} />
      </div>
    </div>
  );
};

interface CanvasFrameProps {
  html:       string;
  previewCss: string;
  selectedId: string | null;
  previewOverlayNodeId: string | null;
  themeCss:   string;
  previewThemeMode: 'light' | 'dark';
  previewFrameNonce: number;
}

const CanvasFrame: FunctionComponent<CanvasFrameProps> = ({ html, previewCss, selectedId, previewOverlayNodeId, themeCss, previewThemeMode, previewFrameNonce }) => {
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const previewScrollRef = useRef({ top: 0, left: 0 });
  const scrollWindowRef = useRef<Window | null>(null);
  const insertBlock = useDocumentStore(s => s.insertBlock);

  // Propagate selectedId into the iframe whenever it changes (no full reload)
  useEffect(() => {
    iframeRef.current?.contentWindow?.postMessage(
      { type: 'bky:host-select', id: selectedId },
      '*',
    );
  }, [selectedId, html]);

  useEffect(() => {
    iframeRef.current?.contentWindow?.postMessage(
      { type: 'bky:host-overlay-preview', nodeId: previewOverlayNodeId },
      '*',
    );
  }, [previewOverlayNodeId, html]);

  const handleDragOver = (event: JSX.TargetedDragEvent<HTMLDivElement>) => {
    if (!window.BlockyBuilderDrag?.blockType) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
  };

  const handleDrop = (event: JSX.TargetedDragEvent<HTMLDivElement>) => {
    const blockType = window.BlockyBuilderDrag?.blockType
      || event.dataTransfer?.getData('application/blocky-block-type')
      || event.dataTransfer?.getData('text/plain');

    if (!blockType || !blockType.startsWith('bky/')) return;
    event.preventDefault();
    insertBlock(blockType, undefined, undefined, undefined, window.BlockyBuilderDrag?.preset);
    delete window.BlockyBuilderDrag;
  };

  useEffect(() => () => {
    scrollWindowRef.current = null;
  }, []);

  const handleFrameLoad = () => {
    const frameWindow = iframeRef.current?.contentWindow;
    if (!frameWindow) return;

    const previousWindow = scrollWindowRef.current;
    if (previousWindow && previousWindow !== frameWindow) {
      previousWindow.onscroll = null;
    }

    const syncScroll = () => {
      previewScrollRef.current = {
        top: frameWindow.scrollY,
        left: frameWindow.scrollX,
      };
    };

    scrollWindowRef.current = frameWindow;
    frameWindow.onscroll = syncScroll;
    frameWindow.requestAnimationFrame(() => {
      frameWindow.scrollTo(previewScrollRef.current.left, previewScrollRef.current.top);
      syncScroll();
    });
  };

  return (
    <div onDragOver={handleDragOver} onDrop={handleDrop}>
      <iframe
        key={`preview:${previewThemeMode}:${previewFrameNonce}`}
        ref={iframeRef}
        onLoad={handleFrameLoad}
        class="block w-full"
        style={{ minHeight: '80vh', border: 'none' }}
        srcdoc={buildSrcdoc(html, previewCss, themeCss, previewThemeMode)}
        sandbox="allow-scripts allow-same-origin"
        title="Page Preview"
      />
    </div>
  );
};

function buildSrcdoc(html: string, previewCss: string, themeCss: string, previewThemeMode: 'light' | 'dark'): string {
  const htmlClass = previewThemeMode === 'dark' ? 'dark' : '';
  const emptySlotLabel = t('canvas.dropBlocksHere', 'Drop blocks here');
  const canvasLabels = {
    moveLeft: t('canvas.moveLeft', 'Move left'),
    moveUp: t('canvas.moveUp', 'Move up'),
    moveDown: t('canvas.moveDown', 'Move down'),
    moveRight: t('canvas.moveRight', 'Move right'),
    duplicate: t('canvas.duplicate', 'Duplicate'),
    deleteLabel: t('canvas.delete', 'Delete'),
    addHeadingInside: t('canvas.addHeadingInside', 'Add heading inside'),
    addTextInside: t('canvas.addTextInside', 'Add text inside'),
    addButtonInside: t('canvas.addButtonInside', 'Add button inside'),
  };

  return `<!doctype html>
<html data-mode="${escapeAttribute(previewThemeMode)}" class="${escapeAttribute(htmlClass)}">
<head>
<meta charset="utf-8">
<style>
  ${BLOCKY_THEME_TOKENS_CSS}
  ${BLOCKY_THEME_DARK_TOKENS_CSS}
  ${themeCss}
  ${previewCss}
  html {
    background: var(--bky-color-surface-base);
    color: var(--bky-color-text-base);
    color-scheme: ${previewThemeMode};
  }
  body {
    min-height: 100vh;
    margin: 0;
    background: var(--bky-color-surface-base);
    color: var(--bky-color-text-base);
    font-family: var(--bky-font-body, system-ui, sans-serif);
  }
  [data-bky-id] { position: relative; outline: 2px solid transparent; outline-offset: -2px; transition: outline-color 0.1s; }
  [data-bky-id]:hover { outline-color: rgba(99,102,241,0.5); }
  [data-bky-id][data-bky-selected="1"] { outline-color: rgb(99,102,241); }
  .bky-hover-toolbar {
    position: absolute; top: 0; right: 0; transform: translateY(-100%);
    display: flex; gap: 2px; padding: 4px;
    background: rgb(99,102,241); color: #fff;
    font: 12px/1 system-ui, sans-serif;
    border-radius: 6px 6px 0 0;
    z-index: 99999; pointer-events: auto;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  }
  .bky-hover-toolbar button {
    all: unset; cursor: pointer;
    padding: 4px 8px; border-radius: 3px;
    font-size: 12px; line-height: 1;
  }
  .bky-hover-toolbar button:hover { background: rgba(255,255,255,0.18); }
  .bky-hover-toolbar button:disabled { cursor: not-allowed; opacity: 0.38; }
  .bky-hover-label {
    padding: 4px 8px; font-weight: 600; font-size: 11px;
    text-transform: uppercase; letter-spacing: 0.05em;
    border-right: 1px solid rgba(255,255,255,0.3);
  }
  [data-bky-container="1"] { min-height: 28px; }
  .bky-edit-container {
    box-shadow: inset 0 0 0 1px rgba(14,165,233,0.18), var(--tw-inset-shadow, 0 0 #0000), var(--tw-inset-ring-shadow, 0 0 #0000), var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow, 0 0 #0000);
  }
  .bky-edit-slot {
    min-height: 32px;
    border-radius: 6px;
  }
  .bky-edit-empty-slot {
    display: block;
    min-height: 72px;
    border: 1px dashed rgba(99,102,241,0.35);
    background: rgba(99,102,241,0.04);
  }
  .bky-edit-empty-slot::before {
    content: ${JSON.stringify(emptySlotLabel)};
    display: flex;
    min-height: 72px;
    align-items: center;
    justify-content: center;
    color: rgba(79,70,229,0.78);
    -webkit-text-stroke: 0;
    text-shadow: none;
    font: 700 12px/1 system-ui, sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .bky-edit-empty-slot[data-bky-empty-contrast="light"]::before { color: rgba(255,255,255,0.96); }
  .bky-edit-empty-slot[data-bky-empty-contrast="dark"]::before { color: rgba(55,65,81,0.88); }
  .bky-edit-empty-slot[data-bky-slot-label]::before { content: attr(data-bky-slot-label); }
  .bky-card-slot.bky-edit-empty-slot { min-height: 52px; }
  .bky-card-slot.bky-edit-empty-slot::before { min-height: 52px; }
  .bky-card-media.bky-edit-empty-slot { min-height: 92px; }
  .bky-card-media.bky-edit-empty-slot::before { min-height: 92px; }
  .bky-button-slot.bky-edit-empty-slot {
    min-height: 40px;
    min-width: 132px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .bky-button-slot.bky-edit-empty-slot::before {
    min-height: 40px;
    color: inherit;
  }
  [data-bky-drop="before"]::before,
  [data-bky-drop="after"]::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    height: 3px;
    background: rgb(99,102,241);
    border-radius: 999px;
    z-index: 99998;
    pointer-events: none;
  }
  [data-bky-drop="before"]::before { top: -2px; }
  [data-bky-drop="after"]::after { bottom: -2px; }
  [data-bky-drop="inside"] {
    outline-color: rgb(14,165,233) !important;
    background-image: linear-gradient(rgba(14,165,233,0.06), rgba(14,165,233,0.06));
  }
  .bky-context-menu {
    position: fixed;
    min-width: 168px;
    display: grid;
    gap: 2px;
    padding: 6px;
    background: #111827;
    color: #fff;
    border-radius: 8px;
    box-shadow: 0 14px 40px rgba(0,0,0,0.28);
    z-index: 100000;
    font: 13px/1.2 system-ui, sans-serif;
  }
  .bky-context-menu button {
    all: unset;
    cursor: pointer;
    border-radius: 5px;
    padding: 8px 10px;
  }
  .bky-context-menu button:hover { background: rgba(255,255,255,0.12); }
  .bky-context-menu .danger { color: #fecaca; }
  body[data-bky-overlay-preview-active="1"]::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.38);
    backdrop-filter: blur(2px);
    z-index: 100000;
    pointer-events: none;
  }
  [data-bky-editor-overlay-preview="true"] {
    position: fixed !important;
    inset: 0 !important;
    z-index: 100001 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    padding: 24px;
    border: none !important;
    background: transparent !important;
    min-height: 0 !important;
    width: auto !important;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="offcanvas"] {
    align-items: stretch;
    justify-content: flex-end;
    padding: 0;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="offcanvas"][data-bky-placement="left"],
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="drawer"][data-bky-placement="left"] {
    justify-content: flex-start;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="offcanvas"][data-bky-placement="top"],
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="drawer"][data-bky-placement="top"] {
    align-items: flex-start;
    justify-content: center;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="offcanvas"][data-bky-placement="bottom"],
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="drawer"][data-bky-placement="bottom"] {
    align-items: flex-end;
    justify-content: center;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="drawer"] {
    align-items: stretch;
    justify-content: flex-start;
    padding: 0;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="notification-toast"] {
    align-items: flex-end;
    justify-content: flex-end;
    padding: 16px;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="cookie-banner"] {
    align-items: flex-end;
    justify-content: center;
    padding: 16px;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="command-palette"] {
    align-items: flex-start;
    justify-content: center;
    padding-top: 24px;
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="popover"],
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="tooltip"] {
    align-items: flex-start;
    justify-content: center;
    padding-top: 48px;
  }
  [data-bky-editor-overlay-preview="true"] [data-bky-overlay-panel] {
    max-height: calc(100vh - 48px);
  }
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="offcanvas"] [data-bky-overlay-panel],
  [data-bky-editor-overlay-preview="true"][data-bky-overlay-variant="drawer"] [data-bky-overlay-panel] {
    max-height: 100vh;
  }
</style>
${themeCss ? `<style id="blocky-runtime-theme">${escapeHtml(themeCss)}</style>` : ''}
</head>
<body>
${html}
<script>(function(){
  var toolbar = null, currentNode = null, selectedId = null, previewOverlayNodeId = null, contextMenu = null;
  var emptySlotContrastTimer = 0;
  var defaultEmptySlotTextColor = { r: 79, g: 70, b: 229 };
  var labels = ${JSON.stringify(canvasLabels)};

  function send(type, extra){
    window.parent.postMessage(Object.assign({ type: type }, extra || {}), '*');
  }
  function parseColor(value){
    if (!value) return null;
    var match = value.match(/rgba?\(([^)]+)\)/i);
    if (!match) return null;
    var parts = match[1].split(',').map(function(part){ return part.trim(); });
    if (parts.length < 3) return null;
    var red = parseFloat(parts[0]);
    var green = parseFloat(parts[1]);
    var blue = parseFloat(parts[2]);
    var alpha = parts.length > 3 ? parseFloat(parts[3]) : 1;
    if (!Number.isFinite(red) || !Number.isFinite(green) || !Number.isFinite(blue) || !Number.isFinite(alpha)) return null;
    return { r: red, g: green, b: blue, a: alpha };
  }
  function channelToLinear(channel){
    var value = channel / 255;
    return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
  }
  function luminance(color){
    return 0.2126 * channelToLinear(color.r) + 0.7152 * channelToLinear(color.g) + 0.0722 * channelToLinear(color.b);
  }
  function contrastRatio(a, b){
    var lighter = Math.max(luminance(a), luminance(b));
    var darker = Math.min(luminance(a), luminance(b));
    return (lighter + 0.05) / (darker + 0.05);
  }
  function backgroundFromStyle(style){
    if (!style) return null;
    if (style.backgroundImage && style.backgroundImage !== 'none') {
      return {
        color: parseColor(style.backgroundColor) || parseColor(window.getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 },
        hasImage: true,
      };
    }
    var color = parseColor(style.backgroundColor);
    if (color && color.a > 0.14) {
      return { color: color, hasImage: false };
    }
    return null;
  }
  function resolveEmptySlotBackground(slot){
    var rect = slot.getBoundingClientRect();
    var x = rect.left + (rect.width / 2);
    var y = rect.top + (rect.height / 2);
    if (Number.isFinite(x) && Number.isFinite(y)) {
      var stack = document.elementsFromPoint(x, y);
      for (var index = 0; index < stack.length; index += 1) {
        var element = stack[index];
        if (!element || element === slot) continue;
        var background = backgroundFromStyle(window.getComputedStyle(element));
        if (background) return background;
      }
    }
    var node = slot.parentElement;
    while (node) {
      var background = backgroundFromStyle(window.getComputedStyle(node));
      if (background) return background;
      node = node.parentElement;
    }
    return {
      color: parseColor(window.getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 },
      hasImage: false,
    };
  }
  function updateEmptySlotContrast(){
    document.querySelectorAll('.bky-edit-empty-slot').forEach(function(slot){
      var result = resolveEmptySlotBackground(slot);
      if (!result || !result.color) {
        slot.removeAttribute('data-bky-empty-contrast');
        return;
      }
      var ratio = contrastRatio(defaultEmptySlotTextColor, result.color);
      if (!result.hasImage && ratio >= 2.8) {
        slot.removeAttribute('data-bky-empty-contrast');
        return;
      }
      slot.setAttribute('data-bky-empty-contrast', luminance(result.color) < 0.42 || result.hasImage ? 'light' : 'dark');
    });
  }
  function scheduleEmptySlotContrastUpdate(){
    if (emptySlotContrastTimer) return;
    emptySlotContrastTimer = window.setTimeout(function(){
      emptySlotContrastTimer = 0;
      updateEmptySlotContrast();
    }, 60);
  }
  function eventElement(ev){
    if (!ev.target) return null;
    if (ev.target.nodeType === 1) return ev.target;
    return ev.target.parentElement || null;
  }
  function clearToolbar(){
    if (toolbar) { toolbar.remove(); toolbar = null; }
    currentNode = null;
  }
  function clearOverlayPreview(){
    document.body.removeAttribute('data-bky-overlay-preview-active');
    document.querySelectorAll('[data-bky-editor-overlay-preview="true"]').forEach(function(node){
      node.removeAttribute('data-bky-editor-overlay-preview');
    });
  }
  function findOverlayByNodeId(nodeId){
    var directOverlayMatches = document.querySelectorAll('[data-bky-overlay-id][data-bky-id]');
    for (var index = 0; index < directOverlayMatches.length; index += 1) {
      if (directOverlayMatches[index].getAttribute('data-bky-id') === nodeId) {
        return directOverlayMatches[index];
      }
    }

    var blockMatches = document.querySelectorAll('[data-bky-id][data-bky-type]');
    for (var blockIndex = 0; blockIndex < blockMatches.length; blockIndex += 1) {
      if (blockMatches[blockIndex].getAttribute('data-bky-id') === nodeId) {
        return blockMatches[blockIndex];
      }
    }

    return null;
  }
  function setOverlayPreview(nodeId){
    clearOverlayPreview();
    previewOverlayNodeId = nodeId || null;
    if (!previewOverlayNodeId) return;
    var overlay = findOverlayByNodeId(previewOverlayNodeId);
    if (!overlay) return;
    document.body.setAttribute('data-bky-overlay-preview-active', '1');
    overlay.setAttribute('data-bky-editor-overlay-preview', 'true');
    var panel = overlay.querySelector('[data-bky-overlay-panel]');
    if (panel && panel.focus) {
      window.requestAnimationFrame(function(){ panel.focus(); });
    }
  }
  function closeContextMenu(){
    if (contextMenu) { contextMenu.remove(); contextMenu = null; }
  }
  function clearDropTarget(){
    document.querySelectorAll('[data-bky-drop]').forEach(function(node){
      node.removeAttribute('data-bky-drop');
    });
  }
  function setDropTarget(intent){
    clearDropTarget();
    if (intent && intent.targetElement) {
      intent.targetElement.setAttribute('data-bky-drop', intent.position);
    }
  }
  function gridPlacementForSlot(slot){
    if (!slot) return null;
    var column = parseInt(slot.getAttribute('data-bky-grid-column') || '', 10);
    var row = parseInt(slot.getAttribute('data-bky-grid-row') || '', 10);
    if (!Number.isFinite(column) || !Number.isFinite(row)) return null;
    return { column: column, row: row };
  }
  function resolveDropIntent(ev){
    var element = eventElement(ev);
    if (!element) return null;

    var slot = element.closest('[data-bky-slot-owner]');
    var node = element.closest('[data-bky-id]');

    if (slot && (!node || slot.classList.contains('bky-edit-empty-slot'))) {
      return {
        targetId: slot.getAttribute('data-bky-slot-owner'),
        slotName: slot.getAttribute('data-bky-slot-name') || 'default',
        gridPlacement: gridPlacementForSlot(slot),
        position: 'inside',
        targetElement: slot
      };
    }

    if (!node) {
      var firstSlot = document.querySelector('[data-bky-slot-owner]');
      if (!firstSlot) return null;
      return {
        targetId: firstSlot.getAttribute('data-bky-slot-owner'),
        slotName: firstSlot.getAttribute('data-bky-slot-name') || 'default',
        gridPlacement: gridPlacementForSlot(firstSlot),
        position: 'inside',
        targetElement: firstSlot
      };
    }

    var rect = node.getBoundingClientRect();
    var ratio = rect.height > 0 ? (ev.clientY - rect.top) / rect.height : 0.5;
    var isContainer = node.getAttribute('data-bky-container') === '1';
    if (isContainer && ratio > 0.25 && ratio < 0.75) {
      return {
        targetId: node.getAttribute('data-bky-id'),
        slotName: 'default',
        position: 'inside',
        targetElement: node
      };
    }

    return {
      targetId: node.getAttribute('data-bky-id'),
      slotName: 'default',
      position: ratio < 0.5 ? 'before' : 'after',
      targetElement: node
    };
  }
  function draggableNodes(){
    document.querySelectorAll('[data-bky-id]').forEach(function(node){
      node.setAttribute('draggable', 'true');
    });
  }
  function parentSlotForNode(node){
    if (!node || !node.parentElement) return null;
    return node.parentElement.closest('[data-bky-slot-owner]');
  }
  function gridParentForNode(node){
    var slot = parentSlotForNode(node);
    if (!slot) return null;
    var ownerId = slot.getAttribute('data-bky-slot-owner');
    if (!ownerId || ownerId === node.getAttribute('data-bky-id')) return null;
    var owner = document.querySelector('[data-bky-id="' + ownerId + '"]');
    if (!owner || owner.getAttribute('data-bky-type') !== 'bky/grid') return null;
    return owner;
  }
  function showToolbar(node){
    if (currentNode === node) return;
    clearToolbar();
    currentNode = node;
    var id    = node.getAttribute('data-bky-id');
    var label = (node.getAttribute('data-bky-type') || '').split('/').pop() || 'block';
    var isRoot = id === 'root';
    var isGridItem = !!gridParentForNode(node);
    toolbar = document.createElement('div');
    toolbar.className = 'bky-hover-toolbar';
    toolbar.setAttribute('contenteditable', 'false');
    toolbar.innerHTML =
      '<span class="bky-hover-label">' + label + '</span>' +
      '<button data-act="drag" title="Drag">↕</button>' +
      (isGridItem ? '<button data-act="left" title="' + labels.moveLeft + '">◀</button>' : '') +
      '<button data-act="up"   title="' + labels.moveUp + '">▲</button>' +
      '<button data-act="down" title="' + labels.moveDown + '">▼</button>' +
      (isGridItem ? '<button data-act="right" title="' + labels.moveRight + '">▶</button>' : '') +
      '<button data-act="dup"  title="' + labels.duplicate + '">⎘</button>' +
      (isRoot ? '' : '<button data-act="del"  title="' + labels.deleteLabel + '">✕</button>');
    toolbar.addEventListener('click', function(ev){
      ev.preventDefault(); ev.stopPropagation();
      var btn = ev.target.closest('button'); if (!btn) return;
      var act = btn.getAttribute('data-act');
      if (act === 'del')  send('bky:delete',    { id: id });
      if (act === 'dup')  send('bky:duplicate', { id: id });
      if (act === 'left') send('bky:move',      { id: id, direction: 'left' });
      if (act === 'up')   send('bky:move',      { id: id, direction: 'up' });
      if (act === 'down') send('bky:move',      { id: id, direction: 'down' });
      if (act === 'right') send('bky:move',     { id: id, direction: 'right' });
    }, true);
    node.appendChild(toolbar);
  }
  function showContextMenu(node, x, y){
    closeContextMenu();
    var id = node.getAttribute('data-bky-id');
    var isContainer = node.getAttribute('data-bky-container') === '1';
    var isRoot = id === 'root';
    var isGridItem = !!gridParentForNode(node);
    contextMenu = document.createElement('div');
    contextMenu.className = 'bky-context-menu';
    contextMenu.style.left = x + 'px';
    contextMenu.style.top = y + 'px';
    contextMenu.innerHTML =
      '<button data-act="up">' + labels.moveUp + '</button>' +
      (isGridItem ? '<button data-act="left">' + labels.moveLeft + '</button>' : '') +
      '<button data-act="down">' + labels.moveDown + '</button>' +
      (isGridItem ? '<button data-act="right">' + labels.moveRight + '</button>' : '') +
      '<button data-act="dup">' + labels.duplicate + '</button>' +
      (isContainer ? '<button data-insert="bky/heading">' + labels.addHeadingInside + '</button><button data-insert="bky/text">' + labels.addTextInside + '</button><button data-insert="bky/button">' + labels.addButtonInside + '</button>' : '') +
      (isRoot ? '' : '<button class="danger" data-act="del">' + labels.deleteLabel + '</button>');
    contextMenu.addEventListener('click', function(ev){
      ev.preventDefault(); ev.stopPropagation();
      var menuElement = eventElement(ev);
      var button = menuElement && menuElement.closest('button');
      if (!button) return;
      var insertType = button.getAttribute('data-insert');
      var act = button.getAttribute('data-act');
      if (insertType) send('bky:insert', { blockType: insertType, targetId: id, position: 'inside', slotName: 'default' });
      if (act === 'del')  send('bky:delete',    { id: id });
      if (act === 'dup')  send('bky:duplicate', { id: id });
      if (act === 'left') send('bky:move',      { id: id, direction: 'left' });
      if (act === 'up')   send('bky:move',      { id: id, direction: 'up' });
      if (act === 'down') send('bky:move',      { id: id, direction: 'down' });
      if (act === 'right') send('bky:move',     { id: id, direction: 'right' });
      closeContextMenu();
    }, true);
    document.body.appendChild(contextMenu);
  }
  draggableNodes();
  scheduleEmptySlotContrastUpdate();
  window.addEventListener('load', scheduleEmptySlotContrastUpdate);
  window.addEventListener('resize', scheduleEmptySlotContrastUpdate);
  new MutationObserver(scheduleEmptySlotContrastUpdate).observe(document.body, {
    subtree: true,
    childList: true,
    attributes: true,
    attributeFilter: ['class', 'style']
  });
  document.addEventListener('mouseover', function(e){
    var element = eventElement(e);
    var node = element && element.closest('[data-bky-id]');
    if (!node) return;
    showToolbar(node);
    send('bky:hover', { id: node.getAttribute('data-bky-id') });
  });
  document.addEventListener('mouseleave', function(){
    clearToolbar();
    closeContextMenu();
    clearDropTarget();
    send('bky:hover', { id: null });
  });
  document.addEventListener('click', function(e){
    var element = eventElement(e);
    closeContextMenu();
    if (element && element.closest('.bky-hover-toolbar')) return;
    var node = element && element.closest('[data-bky-id]');
    send('bky:select', { id: node ? node.getAttribute('data-bky-id') : null });
    e.preventDefault();
  });
  document.addEventListener('contextmenu', function(e){
    var element = eventElement(e);
    var node = element && element.closest('[data-bky-id]');
    if (!node) return;
    e.preventDefault();
    send('bky:select', { id: node.getAttribute('data-bky-id') });
    showContextMenu(node, e.clientX, e.clientY);
  });
  document.addEventListener('dragstart', function(e){
    var element = eventElement(e);
    if (!element || element.closest('.bky-hover-toolbar')) return;
    var node = element.closest('[data-bky-id]');
    if (!node || !e.dataTransfer) return;
    closeContextMenu();
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/blocky-node-id', node.getAttribute('data-bky-id'));
    e.dataTransfer.setData('text/plain', node.getAttribute('data-bky-id'));
  });
  document.addEventListener('dragover', function(e){
    var intent = resolveDropIntent(e);
    if (!intent) return;
    e.preventDefault();
    if (e.dataTransfer) {
      var isNewBlock = Array.prototype.indexOf.call(e.dataTransfer.types, 'application/blocky-block-type') !== -1;
      e.dataTransfer.dropEffect = isNewBlock ? 'copy' : 'move';
    }
    setDropTarget(intent);
  });
  document.addEventListener('dragleave', function(e){
    if (!e.relatedTarget) clearDropTarget();
  });
  document.addEventListener('drop', function(e){
    var intent = resolveDropIntent(e);
    clearDropTarget();
    if (!intent || !e.dataTransfer) return;
    e.preventDefault();
    var blockType = e.dataTransfer.getData('application/blocky-block-type');
    var nodeId = e.dataTransfer.getData('application/blocky-node-id');
    var plainText = e.dataTransfer.getData('text/plain');
    if (!blockType && plainText.indexOf('bky/') === 0) blockType = plainText;
    if (!blockType) {
      try {
        blockType = window.parent.BlockyBuilderDrag && window.parent.BlockyBuilderDrag.blockType || '';
      } catch (error) {
        blockType = '';
      }
    }
    if (blockType) {
      var preset = null;
      var presetJson = e.dataTransfer.getData('application/blocky-block-preset');
      if (presetJson) {
        try { preset = JSON.parse(presetJson); } catch (error) { preset = null; }
      }
      if (!preset) {
        try {
          preset = window.parent.BlockyBuilderDrag && window.parent.BlockyBuilderDrag.preset || null;
        } catch (error) {
          preset = null;
        }
      }
      send('bky:insert', { blockType: blockType, targetId: intent.targetId, position: intent.position, slotName: intent.slotName, preset: preset, gridPlacement: intent.gridPlacement });
    } else if (nodeId) {
      send('bky:move-to', { id: nodeId, targetId: intent.targetId, position: intent.position, slotName: intent.slotName, gridPlacement: intent.gridPlacement });
    }
  });
  window.addEventListener('message', function(ev){
    if (!ev.data || typeof ev.data.type !== 'string') return;
    if (ev.data.type === 'bky:host-select') {
      if (selectedId) {
        var prev = document.querySelector('[data-bky-id="' + selectedId + '"]');
        if (prev) prev.removeAttribute('data-bky-selected');
      }
      selectedId = ev.data.id;
      if (selectedId) {
        var next = document.querySelector('[data-bky-id="' + selectedId + '"]');
        if (next) next.setAttribute('data-bky-selected', '1');
      }
      return;
    }
    if (ev.data.type === 'bky:host-overlay-preview') {
      setOverlayPreview(ev.data.nodeId || null);
    }
  });
})();</script>
</body>
</html>`;
}

function escapeAttribute(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
