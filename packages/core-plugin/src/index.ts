import 'highlight.js/styles/github-dark.css';

interface OverlayApi {
  open: (id: string, trigger?: HTMLElement | null) => boolean;
  close: (id: string) => boolean;
  toggle: (id: string, trigger?: HTMLElement | null) => boolean;
  isOpen: (id: string) => boolean;
  refresh: () => void;
}

interface ActionApi {
  run: (action: string, source: HTMLElement) => void;
  refresh: () => void;
}

interface LottiePlayer {
  setSpeed: (speed: number) => void;
}

interface LottieModule {
  loadAnimation: (config: {
    container: HTMLElement;
    renderer: 'svg';
    loop: boolean;
    autoplay: boolean;
    path: string;
  }) => LottiePlayer;
}

interface HighlightJsModule {
  highlightElement: (element: HTMLElement) => void;
}

interface BlockyRuntime {
  overlays: OverlayApi;
  actions: ActionApi;
}

interface BlockyWindow extends Window {
  Blocky?: BlockyRuntime;
}

interface RegisteredOverlay {
  id: string;
  element: HTMLElement;
  lastTrigger: HTMLElement | null;
}

interface InteractionRule {
  event: 'click' | 'hover' | 'focus' | 'load';
  action: 'overlay.open' | 'overlay.close' | 'overlay.toggle' | 'class.add' | 'class.remove' | 'class.toggle' | 'custom.emit';
  target: string;
  className?: string;
  delay?: number;
  debounce?: number;
  throttle?: number;
  once?: boolean;
  preventDefault?: boolean;
  stopPropagation?: boolean;
  device?: 'any' | 'desktop' | 'tablet' | 'mobile';
  loginState?: 'any' | 'logged-in' | 'logged-out';
  queryKey?: string;
  queryValue?: string;
  cookieKey?: string;
  cookieValue?: string;
}

const OVERLAY_SELECTOR = '[data-bky-overlay-id]';
const ACTION_SELECTOR = '[data-bky-action], [data-bky-interactions]';
const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

const overlays = new Map<string, RegisteredOverlay>();
const overlayStack: string[] = [];
const overlayAutoCloseTimers = new Map<string, number>();
const executedInteractionRules = new WeakMap<HTMLElement, Set<string>>();
const interactionDebounceTimers = new WeakMap<HTMLElement, Map<string, number>>();
const interactionThrottleTimestamps = new WeakMap<HTMLElement, Map<string, number>>();
const initializedLottieNodes = new WeakSet<HTMLElement>();
const initializedCodeHighlightNodes = new WeakSet<HTMLElement>();
const initializedScrollProgressNodes = new WeakSet<HTMLElement>();
const initializedStickyBars = new WeakSet<HTMLElement>();
const initializedBackToTopButtons = new WeakSet<HTMLElement>();
const initializedCommandPalettes = new WeakSet<HTMLElement>();
let bodyScrollLockDepth = 0;
let bodyScrollPaddingRight = '';

type OverlayOpenOrigin = 'manual' | 'auto';

function init(): void {
  registerOverlays();
  bindOverlayGlobalHandlers();
  bindActionDelegates();
  runLoadInteractions();
  initAutomaticOverlayTriggers();
  void initLottieAnimations();
  void initCodeHighlights();
  initScrollProgressBars();
  initStickyBars();
  initBackToTopButtons();
  initCommandPalettes();
  exposeRuntime();
}

function exposeRuntime(): void {
  const runtimeWindow = window as BlockyWindow;
  runtimeWindow.Blocky = {
    overlays: {
      open: openOverlay,
      close: closeOverlay,
      toggle: toggleOverlay,
      isOpen: overlayId => overlayStack.includes(overlayId),
      refresh: refreshRuntime,
    },
    actions: {
      run: (action, source) => executeAction(action, source),
      refresh: refreshRuntime,
    },
  };
}

function refreshRuntime(): void {
  registerOverlays();
  initAutomaticOverlayTriggers();
  void initLottieAnimations();
  void initCodeHighlights();
  initScrollProgressBars();
  initStickyBars();
  initBackToTopButtons();
  initCommandPalettes();
}

async function initLottieAnimations(): Promise<void> {
  const roots = Array.from(document.querySelectorAll<HTMLElement>('[data-bky-lottie]')).filter(root => !initializedLottieNodes.has(root));
  if (roots.length === 0) {
    return;
  }

  const module = await import('lottie-web')
    .then(entry => ((entry as { default?: unknown }).default ?? entry) as LottieModule)
    .catch((): LottieModule | null => null);
  if (!module) {
    return;
  }

  roots.forEach(root => {
    const path = root.dataset.bkyLottieSrc?.trim();
    const container = root.querySelector<HTMLElement>('[data-bky-lottie-canvas]');
    if (!path || !container) {
      initializedLottieNodes.add(root);
      return;
    }

    const player = module.loadAnimation({
      container,
      renderer: 'svg',
      loop: root.dataset.bkyLottieLoop !== 'false',
      autoplay: root.dataset.bkyLottieAutoplay !== 'false',
      path,
    });

    const speed = Number(root.dataset.bkyLottieSpeed ?? '1');
    if (!Number.isNaN(speed) && speed > 0) {
      player.setSpeed(speed);
    }

    const poster = root.querySelector<HTMLElement>('[data-bky-lottie-poster]');
    if (poster) {
      window.setTimeout(() => {
        poster.style.opacity = '0';
        poster.style.pointerEvents = 'none';
      }, 250);
    }

    initializedLottieNodes.add(root);
  });
}

async function initCodeHighlights(): Promise<void> {
  const roots = Array.from(document.querySelectorAll<HTMLElement>('[data-bky-code-highlight]')).filter(root => !initializedCodeHighlightNodes.has(root));
  if (roots.length === 0) {
    return;
  }

  const module = await import('highlight.js')
    .then(entry => ((entry as { default?: unknown }).default ?? entry) as HighlightJsModule)
    .catch((): HighlightJsModule | null => null);
  if (!module) {
    return;
  }

  roots.forEach(root => {
    const code = root.querySelector<HTMLElement>('code');
    if (code) {
      module.highlightElement(code);
    }
    initializedCodeHighlightNodes.add(root);
  });
}

function registerOverlays(): void {
  const elements = document.querySelectorAll<HTMLElement>(OVERLAY_SELECTOR);

  elements.forEach(element => {
    const overlayId = element.dataset.bkyOverlayId?.trim();
    if (!overlayId) return;

    const existing = overlays.get(overlayId);
    overlays.set(overlayId, {
      id: overlayId,
      element,
      lastTrigger: existing?.lastTrigger ?? null,
    });

    if (!element.hasAttribute('role')) {
      element.setAttribute('role', 'dialog');
    }
    element.dataset.bkyReducedMotion = prefersReducedMotion() ? 'true' : 'false';

    const shouldStartOpen = element.dataset.bkyDefaultOpen === 'true';
    if (!shouldStartOpen) {
      element.hidden = true;
      element.setAttribute('aria-hidden', 'true');
      element.removeAttribute('data-bky-open');
      if (!overlayStack.includes(overlayId)) {
        clearOverlayAutoClose(overlayId);
      }
      return;
    }

    element.hidden = false;
    element.setAttribute('aria-hidden', 'false');
    element.setAttribute('data-bky-open', 'true');
    if (!overlayStack.includes(overlayId)) {
      overlayStack.push(overlayId);
    }
  });

  updateOverlayStackStyles();
}

function bindOverlayGlobalHandlers(): void {
  document.addEventListener('keydown', event => {
    const activeId = overlayStack[overlayStack.length - 1];
    if (!activeId) return;

    if (event.key === 'Escape') {
      const overlay = overlays.get(activeId);
      if (!overlay || overlay.element.dataset.bkyDismissEsc === 'false') return;
      event.preventDefault();
      closeOverlay(activeId);
      return;
    }

    if (event.key === 'Tab') {
      trapFocus(event, activeId);
    }
  });

  document.addEventListener('click', event => {
    const activeId = overlayStack[overlayStack.length - 1];
    if (!activeId) return;
    const overlay = overlays.get(activeId);
    if (!overlay || overlay.element.dataset.bkyDismissOutside === 'false') return;
    if (overlay.element.contains(event.target as Node)) return;
    closeOverlay(activeId);
  });

  document.addEventListener('click', event => {
    const target = event.target;
    const source = target instanceof Element ? target.closest<HTMLElement>('[data-bky-cookie-set]') : null;
    if (!source) return;

    const overlayId = source.dataset.bkyTarget?.trim() ?? '';
    const overlay = overlayId ? overlays.get(overlayId) : null;
    const cookieName = overlay?.element.dataset.bkyCookieName?.trim() ?? '';
    if (!cookieName) {
      closeOverlay(overlayId);
      return;
    }

    const cookieValue = source.dataset.bkyCookieSet?.trim() === 'reject' ? 'rejected' : 'accepted';
    const durationDays = Number(overlay?.element.dataset.bkyCookieDuration ?? '180');
    const maxAge = Number.isFinite(durationDays) ? Math.max(1, Math.round(durationDays)) * 24 * 60 * 60 : 180 * 24 * 60 * 60;
    document.cookie = `${encodeURIComponent(cookieName)}=${encodeURIComponent(cookieValue)}; path=/; max-age=${maxAge}; SameSite=Lax`;
    closeOverlay(overlayId);
  });

  window.addEventListener('resize', refreshAnchoredOverlayPositions);
  document.addEventListener('scroll', refreshAnchoredOverlayPositions, true);

  const closeRouteSensitiveOverlays = (): void => {
    [...overlayStack].reverse().forEach(overlayId => {
      const overlay = overlays.get(overlayId);
      if (!overlay) return;
      if (parseOverlayRuleList(overlay.element.dataset.bkyCloseOn).includes('route-change')) {
        closeOverlay(overlayId);
      }
    });
  };

  window.addEventListener('popstate', closeRouteSensitiveOverlays);
  window.addEventListener('hashchange', closeRouteSensitiveOverlays);
}

function bindActionDelegates(): void {
  const dispatchers: Array<[keyof DocumentEventMap, string]> = [
    ['click', 'click'],
    ['mouseenter', 'hover'],
    ['focusin', 'focus'],
  ];

  dispatchers.forEach(([domEvent, actionEvent]) => {
    document.addEventListener(domEvent, event => {
      const target = event.target;
      const source = target instanceof Element ? target.closest<HTMLElement>(ACTION_SELECTOR) : null;
      if (!source) return;
      const interactions = interactionRulesForElement(source).filter(rule => rule.event === actionEvent);
      if (interactions.length === 0) return;

      interactions.forEach(rule => runInteractionRule(rule, source, event));
    }, actionEvent === 'hover');
  });
}

function runLoadInteractions(): void {
  document.querySelectorAll<HTMLElement>(ACTION_SELECTOR).forEach(source => {
    interactionRulesForElement(source)
      .filter(rule => rule.event === 'load')
      .forEach(rule => runInteractionRule(rule, source));
  });
}

function interactionRulesForElement(source: HTMLElement): InteractionRule[] {
  const encodedInteractions = source.dataset.bkyInteractions?.trim();
  if (encodedInteractions) {
    try {
      const parsed = JSON.parse(window.atob(encodedInteractions)) as unknown;
      if (Array.isArray(parsed)) {
        return parsed.flatMap(normalizeInteractionRule);
      }
    } catch {
      return [];
    }
  }

  const action = source.dataset.bkyAction?.trim();
  if (!action) return [];
  return normalizeInteractionRule({
    event: source.dataset.bkyEvent?.trim() || 'click',
    action,
    target: source.dataset.bkyTarget?.trim() || '',
    className: source.dataset.bkyClass?.trim() || '',
    delay: Number(source.dataset.bkyDelay ?? '0'),
    debounce: Number(source.dataset.bkyDebounce ?? '0'),
    throttle: Number(source.dataset.bkyThrottle ?? '0'),
    once: source.dataset.bkyOnce === 'true',
    preventDefault: source.dataset.bkyPreventDefault !== 'false',
    stopPropagation: source.dataset.bkyStopPropagation === 'true',
    device: source.dataset.bkyDevice?.trim() || 'any',
    loginState: source.dataset.bkyLoginState?.trim() || 'any',
    queryKey: source.dataset.bkyQueryKey?.trim() || '',
    queryValue: source.dataset.bkyQueryValue?.trim() || '',
    cookieKey: source.dataset.bkyCookieKey?.trim() || '',
    cookieValue: source.dataset.bkyCookieValue?.trim() || '',
  });
}

function normalizeInteractionRule(entry: unknown): InteractionRule[] {
  if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return [];

  const event = typeof entry['event'] === 'string' ? entry['event'].trim() : '';
  const action = typeof entry['action'] === 'string' ? entry['action'].trim() : '';
  if (!isInteractionEvent(event) || !isInteractionAction(action)) return [];

  return [{
    event,
    action,
    target: typeof entry['target'] === 'string' ? entry['target'].trim() : '',
    className: typeof entry['className'] === 'string' ? entry['className'].trim() : undefined,
    delay: typeof entry['delay'] === 'number' && Number.isFinite(entry['delay']) ? Math.max(0, Math.round(entry['delay'])) : 0,
    debounce: typeof entry['debounce'] === 'number' && Number.isFinite(entry['debounce']) ? Math.max(0, Math.round(entry['debounce'])) : 0,
    throttle: typeof entry['throttle'] === 'number' && Number.isFinite(entry['throttle']) ? Math.max(0, Math.round(entry['throttle'])) : 0,
    once: entry['once'] === true,
    preventDefault: entry['preventDefault'] !== false,
    stopPropagation: entry['stopPropagation'] === true,
    device: isInteractionDevice(typeof entry['device'] === 'string' ? entry['device'].trim() : 'any') ? (typeof entry['device'] === 'string' ? entry['device'].trim() : 'any') : 'any',
    loginState: isInteractionLoginState(typeof entry['loginState'] === 'string' ? entry['loginState'].trim() : 'any') ? (typeof entry['loginState'] === 'string' ? entry['loginState'].trim() : 'any') : 'any',
    queryKey: typeof entry['queryKey'] === 'string' ? entry['queryKey'].trim() : undefined,
    queryValue: typeof entry['queryValue'] === 'string' ? entry['queryValue'].trim() : undefined,
    cookieKey: typeof entry['cookieKey'] === 'string' ? entry['cookieKey'].trim() : undefined,
    cookieValue: typeof entry['cookieValue'] === 'string' ? entry['cookieValue'].trim() : undefined,
  }];
}

function runInteractionRule(rule: InteractionRule, source: HTMLElement, event?: Event): void {
  const ruleKey = `${rule.event}:${rule.action}:${rule.target}:${rule.className ?? ''}`;
  const executedRules = executedInteractionRules.get(source) ?? new Set<string>();
  if (rule.once && executedRules.has(ruleKey)) return;
  if (!matchesInteractionConditions(rule)) return;

  if (event && rule.preventDefault && event.cancelable) {
    event.preventDefault();
  }
  if (event && rule.stopPropagation) {
    event.stopPropagation();
  }

  const execute = (): void => {
    executeAction(rule.action, source, rule);
    if (rule.once) {
      executedRules.add(ruleKey);
      executedInteractionRules.set(source, executedRules);
    }
  };

  if ((rule.throttle ?? 0) > 0 && isInteractionThrottled(source, ruleKey, rule.throttle ?? 0)) {
    return;
  }

  if ((rule.debounce ?? 0) > 0) {
    const timers = interactionDebounceTimers.get(source) ?? new Map<string, number>();
    const existingTimer = timers.get(ruleKey);
    if (existingTimer) {
      window.clearTimeout(existingTimer);
    }
    const timer = window.setTimeout(execute, rule.debounce);
    timers.set(ruleKey, timer);
    interactionDebounceTimers.set(source, timers);
    return;
  }

  if ((rule.delay ?? 0) > 0) {
    window.setTimeout(execute, rule.delay);
    return;
  }

  execute();
}

function executeAction(action: string, source: HTMLElement, rule?: InteractionRule): void {
  const normalizedAction = action.trim();
  if (!normalizedAction) return;
  const target = rule?.target ?? source.dataset.bkyTarget ?? '';
  const className = rule?.className ?? source.dataset.bkyClass ?? '';

  if (normalizedAction === 'overlay.open') {
    openOverlay(target, source);
    return;
  }

  if (normalizedAction === 'overlay.close') {
    closeOverlay(target);
    return;
  }

  if (normalizedAction === 'overlay.toggle') {
    toggleOverlay(target, source);
    return;
  }

  if (normalizedAction.startsWith('class.')) {
    runClassAction(normalizedAction, target, className);
    return;
  }

  if (normalizedAction === 'custom.emit') {
    const eventName = target.trim();
    if (!eventName) return;
    window.dispatchEvent(new CustomEvent(eventName, { detail: { source } }));
  }
}

function runClassAction(action: string, selector: string, className: string): void {
  if (!selector || !className) return;

  document.querySelectorAll<HTMLElement>(selector).forEach(element => {
    if (action === 'class.add') element.classList.add(className);
    if (action === 'class.remove') element.classList.remove(className);
    if (action === 'class.toggle') element.classList.toggle(className);
  });
}

function matchesInteractionConditions(rule: InteractionRule): boolean {
  if (!matchesInteractionDevice(rule.device ?? 'any')) return false;
  if (!matchesInteractionLoginState(rule.loginState ?? 'any')) return false;
  if (!matchesInteractionQuery(rule.queryKey ?? '', rule.queryValue ?? '')) return false;
  if (!matchesInteractionCookie(rule.cookieKey ?? '', rule.cookieValue ?? '')) return false;
  return true;
}

function matchesInteractionDevice(device: InteractionRule['device']): boolean {
  if (!device || device === 'any') return true;
  const width = window.innerWidth;
  if (device === 'mobile') return width < 640;
  if (device === 'tablet') return width >= 640 && width < 1024;
  if (device === 'desktop') return width >= 1024;
  return true;
}

function matchesInteractionLoginState(loginState: InteractionRule['loginState']): boolean {
  if (!loginState || loginState === 'any') return true;
  const isLoggedIn = document.body.classList.contains('logged-in');
  return loginState === 'logged-in' ? isLoggedIn : !isLoggedIn;
}

function matchesInteractionQuery(key: string, value: string): boolean {
  if (!key) return true;
  const params = new URLSearchParams(window.location.search);
  if (!params.has(key)) return false;
  return value === '' ? true : params.get(key) === value;
}

function matchesInteractionCookie(key: string, value: string): boolean {
  if (!key) return true;
  const cookieValue = readCookieValue(key);
  if (cookieValue === null) return false;
  return value === '' ? true : cookieValue === value;
}

function readCookieValue(name: string): string | null {
  const encodedName = `${encodeURIComponent(name)}=`;
  const entry = document.cookie
    .split(';')
    .map(value => value.trim())
    .find(value => value.startsWith(encodedName));

  if (!entry) return null;

  try {
    return decodeURIComponent(entry.slice(encodedName.length));
  } catch {
    return entry.slice(encodedName.length);
  }
}

function isInteractionThrottled(source: HTMLElement, ruleKey: string, throttle: number): boolean {
  const timestamps = interactionThrottleTimestamps.get(source) ?? new Map<string, number>();
  const now = Date.now();
  const lastRun = timestamps.get(ruleKey) ?? 0;
  if (now - lastRun < throttle) {
    return true;
  }
  timestamps.set(ruleKey, now);
  interactionThrottleTimestamps.set(source, timestamps);
  return false;
}

function initAutomaticOverlayTriggers(): void {
  overlays.forEach(overlay => {
    if (overlay.element.dataset.bkyAutoTriggerBound === 'true') return;

    const cookieName = overlay.element.dataset.bkyCookieName?.trim();
    if (cookieName && readCookieValue(cookieName) !== null) {
      overlay.element.dataset.bkyAutoTriggerBound = 'true';
      return;
    }

    const rules = parseOverlayRuleList(overlay.element.dataset.bkyOpenOn);
    if (rules.length === 0) {
      overlay.element.dataset.bkyAutoTriggerBound = 'true';
      return;
    }

    rules.forEach(rule => bindAutomaticOverlayTrigger(overlay, rule));
    overlay.element.dataset.bkyAutoTriggerBound = 'true';
  });
}

function bindAutomaticOverlayTrigger(overlay: RegisteredOverlay, rule: string): void {
  if (rule === 'load') {
    openOverlay(overlay.id, null, 'auto');
    return;
  }

  const delayMatch = /^delay:(\d+)$/.exec(rule);
  if (delayMatch) {
    window.setTimeout(() => openOverlay(overlay.id, null, 'auto'), Number(delayMatch[1] ?? '0'));
    return;
  }

  if (rule === 'exit-intent') {
    const handler = (event: MouseEvent): void => {
      if (event.relatedTarget === null && event.clientY <= 0 && openOverlay(overlay.id, null, 'auto')) {
        document.removeEventListener('mouseout', handler);
      }
    };
    document.addEventListener('mouseout', handler);
    return;
  }

  const scrollMatch = /^scroll:(\d{1,3})$/.exec(rule);
  if (scrollMatch) {
    const threshold = Math.max(0, Math.min(100, Number(scrollMatch[1] ?? '0')));
    const handler = (): void => {
      const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const progress = (window.scrollY / scrollable) * 100;
      if (progress >= threshold && openOverlay(overlay.id, null, 'auto')) {
        window.removeEventListener('scroll', handler);
      }
    };
    window.addEventListener('scroll', handler, { passive: true });
    return;
  }

  const clickMatch = /^(?:click|element-click):(.+)$/.exec(rule);
  if (clickMatch) {
    const selector = clickMatch[1]?.trim();
    if (!selector) return;
    const handler = (event: MouseEvent): void => {
      const target = event.target;
      const source = target instanceof Element ? target.closest<HTMLElement>(selector) : null;
      if (!source) return;
      event.preventDefault();
      openOverlay(overlay.id, source, 'auto');
    };
    document.addEventListener('click', handler);
    return;
  }

  const visibleMatch = /^element-visible:(.+)$/.exec(rule);
  if (visibleMatch && 'IntersectionObserver' in window) {
    const selector = visibleMatch[1]?.trim();
    if (!selector) return;
    const target = document.querySelector<HTMLElement>(selector);
    if (!target) return;
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting) && openOverlay(overlay.id, null, 'auto')) {
        observer.disconnect();
      }
    }, { threshold: 0.3 });
    observer.observe(target);
    return;
  }

  const inactivityMatch = /^inactivity:(\d+)$/.exec(rule);
  if (inactivityMatch) {
    const timeout = Math.max(250, Number(inactivityMatch[1] ?? '0'));
    const activityEvents: Array<keyof DocumentEventMap> = ['mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
    let timer = 0;
    const cleanup = (): void => {
      activityEvents.forEach(eventName => document.removeEventListener(eventName, resetTimer, true));
      if (timer) {
        window.clearTimeout(timer);
      }
    };
    const resetTimer = (): void => {
      if (timer) {
        window.clearTimeout(timer);
      }
      timer = window.setTimeout(() => {
        if (openOverlay(overlay.id, null, 'auto')) {
          cleanup();
        }
      }, timeout);
    };
    activityEvents.forEach(eventName => document.addEventListener(eventName, resetTimer, true));
    resetTimer();
    return;
  }

  const queryParamMatch = /^query-param:([^=]+)(?:=(.*))?$/.exec(rule);
  if (queryParamMatch) {
    const key = queryParamMatch[1]?.trim() ?? '';
    const expectedValue = queryParamMatch[2]?.trim() ?? '';
    const params = new URLSearchParams(window.location.search);
    if (key !== '' && params.has(key) && (expectedValue === '' || params.get(key) === expectedValue)) {
      openOverlay(overlay.id, null, 'auto');
    }
    return;
  }

  const cookieAbsentMatch = /^cookie-absent:(.+)$/.exec(rule);
  if (cookieAbsentMatch) {
    const cookieKey = cookieAbsentMatch[1]?.trim() ?? '';
    if (cookieKey !== '' && readCookieValue(cookieKey) === null) {
      openOverlay(overlay.id, null, 'auto');
    }
    return;
  }

  const routeMatch = /^route-match:(.+)$/.exec(rule);
  if (routeMatch) {
    const expression = routeMatch[1]?.trim() ?? '';
    const maybeOpen = (): void => {
      if (matchesRouteExpression(expression)) {
        openOverlay(overlay.id, null, 'auto');
      }
    };
    maybeOpen();
    window.addEventListener('popstate', maybeOpen);
    window.addEventListener('hashchange', maybeOpen);
    return;
  }

  if (rule === 'shortcut:mod+k') {
    const handler = (event: KeyboardEvent): void => {
      const isMod = event.metaKey || event.ctrlKey;
      if (!isMod || event.key.toLowerCase() !== 'k') return;
      event.preventDefault();
      openOverlay(overlay.id, null, 'auto');
    };
    document.addEventListener('keydown', handler);
  }
}

function parseOverlayRuleList(value: string | undefined): string[] {
  return (value ?? '')
    .split(',')
    .map(entry => entry.trim())
    .filter(entry => entry !== '');
}

function canAutoOpenOverlay(overlay: RegisteredOverlay): boolean {
  const frequency = overlay.element.dataset.bkyFrequency?.trim() ?? 'always';
  if (frequency === '' || frequency === 'always') {
    return true;
  }

  const key = `bky:overlay:frequency:${overlay.id}`;
  try {
    if (frequency === 'session') {
      return window.sessionStorage.getItem(key) !== '1';
    }

    if (frequency === 'once') {
      return window.localStorage.getItem(key) !== '1';
    }

    const lastSeen = Number(window.localStorage.getItem(key) ?? '0');
    if (!Number.isFinite(lastSeen) || lastSeen <= 0) {
      return true;
    }

    const elapsed = Date.now() - lastSeen;
    if (frequency === 'day') {
      return elapsed >= 24 * 60 * 60 * 1000;
    }
    if (frequency === 'week') {
      return elapsed >= 7 * 24 * 60 * 60 * 1000;
    }
  } catch {
    return true;
  }

  return true;
}

function markAutoOverlayOpen(overlay: RegisteredOverlay): void {
  const frequency = overlay.element.dataset.bkyFrequency?.trim() ?? 'always';
  if (frequency === '' || frequency === 'always') {
    return;
  }

  const key = `bky:overlay:frequency:${overlay.id}`;
  try {
    if (frequency === 'session') {
      window.sessionStorage.setItem(key, '1');
      return;
    }

    if (frequency === 'once') {
      window.localStorage.setItem(key, '1');
      return;
    }

    window.localStorage.setItem(key, String(Date.now()));
  } catch {
    // Storage is best-effort; auto-open should still work without persistence.
  }
}

function scheduleOverlayAutoClose(overlayId: string): void {
  clearOverlayAutoClose(overlayId);
  const overlay = overlays.get(overlayId);
  if (!overlay) return;

  const timeoutRule = parseOverlayRuleList(overlay.element.dataset.bkyCloseOn)
    .map(rule => /^timeout:(\d+)$/.exec(rule))
    .find(Boolean);

  if (!timeoutRule) return;

  const timeout = Math.max(0, Number(timeoutRule[1] ?? '0'));
  if (timeout <= 0) return;

  const timer = window.setTimeout(() => closeOverlay(overlayId), timeout);
  overlayAutoCloseTimers.set(overlayId, timer);
}

function clearOverlayAutoClose(overlayId: string): void {
  const timer = overlayAutoCloseTimers.get(overlayId);
  if (!timer) return;
  window.clearTimeout(timer);
  overlayAutoCloseTimers.delete(overlayId);
}

function updateOverlayStackStyles(): void {
  overlayStack.forEach((overlayId, index) => {
    const overlay = overlays.get(overlayId);
    if (!overlay) return;
    overlay.element.style.zIndex = String(5000 + index * 2);
  });
}

function matchesRouteExpression(expression: string): boolean {
  if (!expression) return false;

  if (expression.startsWith('/') && expression.endsWith('/') && expression.length > 2) {
    try {
      return new RegExp(expression.slice(1, -1)).test(window.location.pathname + window.location.search + window.location.hash);
    } catch {
      return false;
    }
  }

  const currentRoute = `${window.location.pathname}${window.location.search}${window.location.hash}`;
  return currentRoute === expression || currentRoute.includes(expression);
}

function initCommandPalettes(): void {
  document.querySelectorAll<HTMLElement>('[data-bky-command-palette]').forEach(root => {
    if (initializedCommandPalettes.has(root)) return;

    const input = root.querySelector<HTMLInputElement>('[data-bky-command-search]');
    const items = Array.from(root.querySelectorAll<HTMLElement>('[data-bky-command-item]'));
    if (!input || items.length === 0) {
      initializedCommandPalettes.add(root);
      return;
    }

    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      items.forEach(item => {
        const text = (item.dataset.bkyCommandItem ?? item.textContent ?? '').toLowerCase();
        item.hidden = query !== '' && !text.includes(query);
      });
    });

    initializedCommandPalettes.add(root);
  });
}

function initScrollProgressBars(): void {
  document.querySelectorAll<HTMLElement>('[data-bky-scroll-progress]').forEach(root => {
    if (initializedScrollProgressNodes.has(root)) return;

    const fill = root.querySelector<HTMLElement>('[data-bky-scroll-progress-fill]');
    if (!fill) {
      initializedScrollProgressNodes.add(root);
      return;
    }

    const update = (): void => {
      const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const progress = Math.max(0, Math.min(100, (window.scrollY / scrollable) * 100));
      fill.style.width = `${progress}%`;
    };

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
    initializedScrollProgressNodes.add(root);
  });
}

function initStickyBars(): void {
  document.querySelectorAll<HTMLElement>('[data-bky-sticky-bar]').forEach(root => {
    if (initializedStickyBars.has(root)) return;

    const dismissButton = root.querySelector<HTMLElement>('[data-bky-sticky-dismiss]');
    const threshold = Math.max(0, Math.min(100, Number(root.dataset.bkyShowAfter ?? '15')));
    const editorMode = root.dataset.bkyEditorMode === 'true';

    const update = (): void => {
      if (root.dataset.bkyDismissed === 'true') {
        setStickyBarVisibility(root, false);
        return;
      }
      if (editorMode) {
        setStickyBarVisibility(root, true);
        return;
      }

      const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const progress = (window.scrollY / scrollable) * 100;
      setStickyBarVisibility(root, progress >= threshold);
    };

    dismissButton?.addEventListener('click', () => {
      root.dataset.bkyDismissed = 'true';
      setStickyBarVisibility(root, false);
    });

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
    initializedStickyBars.add(root);
  });
}

function initBackToTopButtons(): void {
  document.querySelectorAll<HTMLElement>('[data-bky-back-to-top]').forEach(root => {
    if (initializedBackToTopButtons.has(root)) return;

    const threshold = Math.max(0, Math.min(100, Number(root.dataset.bkyShowAfter ?? '20')));
    const editorMode = root.dataset.bkyEditorMode === 'true';

    const update = (): void => {
      if (editorMode) {
        setFloatingControlVisibility(root, true);
        return;
      }
      const scrollable = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const progress = (window.scrollY / scrollable) * 100;
      setFloatingControlVisibility(root, progress >= threshold);
    };

    root.addEventListener('click', event => {
      event.preventDefault();
      window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    });

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
    initializedBackToTopButtons.add(root);
  });
}

function setStickyBarVisibility(root: HTMLElement, visible: boolean): void {
  const position = root.dataset.bkyStickyPosition === 'top' ? 'top' : 'bottom';
  root.classList.toggle('opacity-100', visible);
  root.classList.toggle('pointer-events-auto', visible);
  root.classList.toggle('translate-y-0', visible);

  root.classList.toggle('opacity-0', !visible);
  root.classList.toggle('pointer-events-none', !visible);
  root.classList.toggle(position === 'top' ? '-translate-y-6' : 'translate-y-6', !visible);
}

function setFloatingControlVisibility(root: HTMLElement, visible: boolean): void {
  root.classList.toggle('opacity-100', visible);
  root.classList.toggle('pointer-events-auto', visible);
  root.classList.toggle('translate-y-0', visible);

  root.classList.toggle('opacity-0', !visible);
  root.classList.toggle('pointer-events-none', !visible);
  root.classList.toggle('translate-y-4', !visible);
}

function openOverlay(id: string, trigger?: HTMLElement | null, origin: OverlayOpenOrigin = 'manual'): boolean {
  const overlay = overlays.get(id.trim());
  if (!overlay) return false;
  if (overlayStack.includes(overlay.id)) return true;
  if (origin === 'auto' && !canAutoOpenOverlay(overlay)) return false;

  const beforeEvent = new CustomEvent('blocky:overlay:beforeOpen', {
    bubbles: true,
    cancelable: true,
    detail: { id: overlay.id, element: overlay.element, trigger: trigger ?? null },
  });

  if (!overlay.element.dispatchEvent(beforeEvent)) {
    return false;
  }

  overlay.lastTrigger = trigger ?? activeHTMLElement();
  overlay.element.dataset.bkyReducedMotion = prefersReducedMotion() ? 'true' : 'false';
  overlay.element.hidden = false;
  overlay.element.setAttribute('data-bky-open', 'true');
  overlay.element.setAttribute('aria-hidden', 'false');
  positionAnchoredOverlay(overlay.element, overlay.lastTrigger);
  overlayStack.push(overlay.id);
  updateOverlayStackStyles();
  applyScrollLock();
  focusOverlay(overlay.element);
  scheduleOverlayAutoClose(overlay.id);
  if (origin === 'auto') {
    markAutoOverlayOpen(overlay);
  }
  overlay.element.dispatchEvent(new CustomEvent('blocky:overlay:open', { bubbles: true, detail: { id: overlay.id } }));
  return true;
}

function closeOverlay(id: string): boolean {
  const overlay = overlays.get(id.trim());
  if (!overlay || !overlayStack.includes(overlay.id)) return false;

  overlay.element.hidden = true;
  overlay.element.removeAttribute('data-bky-open');
  overlay.element.setAttribute('aria-hidden', 'true');
  resetAnchoredOverlayPosition(overlay.element);
  clearOverlayAutoClose(overlay.id);
  const stackIndex = overlayStack.lastIndexOf(overlay.id);
  if (stackIndex >= 0) {
    overlayStack.splice(stackIndex, 1);
  }
  overlay.element.style.zIndex = '';
  updateOverlayStackStyles();
  releaseScrollLock();
  overlay.element.dispatchEvent(new CustomEvent('blocky:overlay:close', { bubbles: true, detail: { id: overlay.id } }));
  overlay.lastTrigger?.focus?.();
  return true;
}

function toggleOverlay(id: string, trigger?: HTMLElement | null): boolean {
  if (overlayStack.includes(id.trim())) {
    return closeOverlay(id);
  }
  return openOverlay(id, trigger);
}

function focusOverlay(element: HTMLElement): void {
  const focusables = findFocusable(element);
  const target = focusables[0] ?? element;
  if (!target.hasAttribute('tabindex')) {
    target.setAttribute('tabindex', '-1');
  }
  target.focus();
}

function trapFocus(event: KeyboardEvent, overlayId: string): void {
  const overlay = overlays.get(overlayId);
  if (!overlay || overlay.element.dataset.bkyFocusTrap === 'false') return;

  const focusables = findFocusable(overlay.element);
  if (focusables.length === 0) {
    event.preventDefault();
    overlay.element.focus();
    return;
  }

  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  const active = activeHTMLElement();

  if (event.shiftKey && active === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
  }
}

function findFocusable(container: HTMLElement): HTMLElement[] {
  return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
    .filter(element => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');
}

function activeHTMLElement(): HTMLElement | null {
  return document.activeElement instanceof HTMLElement ? document.activeElement : null;
}

function applyScrollLock(): void {
  bodyScrollLockDepth += 1;
  if (bodyScrollLockDepth !== 1) return;

  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
  bodyScrollPaddingRight = document.body.style.paddingRight;
  document.body.style.overflow = 'hidden';
  if (scrollbarWidth > 0) {
    document.body.style.paddingRight = `${scrollbarWidth}px`;
  }
}

function releaseScrollLock(): void {
  bodyScrollLockDepth = Math.max(0, bodyScrollLockDepth - 1);
  if (bodyScrollLockDepth !== 0) return;

  document.body.style.overflow = '';
  document.body.style.paddingRight = bodyScrollPaddingRight;
}

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function refreshAnchoredOverlayPositions(): void {
  overlayStack.forEach(id => {
    const overlay = overlays.get(id);
    if (!overlay) return;
    positionAnchoredOverlay(overlay.element, overlay.lastTrigger);
  });
}

function positionAnchoredOverlay(element: HTMLElement, trigger: HTMLElement | null): void {
  const variant = element.dataset.bkyOverlayVariant?.trim();
  if (variant !== 'popover' && variant !== 'tooltip') {
    resetAnchoredOverlayPosition(element);
    return;
  }

  const panel = element.querySelector<HTMLElement>('[data-bky-overlay-panel]');
  if (!panel || !trigger) return;

  const triggerRect = trigger.getBoundingClientRect();
  const panelRect = panel.getBoundingClientRect();
  const placement = element.dataset.bkyPlacement?.trim() || (variant === 'tooltip' ? 'top' : 'bottom');
  const gap = variant === 'tooltip' ? 8 : 12;
  const viewportPadding = 12;

  let top = triggerRect.bottom + gap;
  let left = triggerRect.left + (triggerRect.width / 2) - (panelRect.width / 2);

  if (placement === 'top') {
    top = triggerRect.top - panelRect.height - gap;
  } else if (placement === 'left') {
    top = triggerRect.top + (triggerRect.height / 2) - (panelRect.height / 2);
    left = triggerRect.left - panelRect.width - gap;
  } else if (placement === 'right') {
    top = triggerRect.top + (triggerRect.height / 2) - (panelRect.height / 2);
    left = triggerRect.right + gap;
  }

  const maxTop = Math.max(viewportPadding, window.innerHeight - panelRect.height - viewportPadding);
  const maxLeft = Math.max(viewportPadding, window.innerWidth - panelRect.width - viewportPadding);
  panel.style.position = 'fixed';
  panel.style.top = `${clamp(top, viewportPadding, maxTop)}px`;
  panel.style.left = `${clamp(left, viewportPadding, maxLeft)}px`;
}

function resetAnchoredOverlayPosition(element: HTMLElement): void {
  const panel = element.querySelector<HTMLElement>('[data-bky-overlay-panel]');
  if (!panel) return;
  panel.style.position = '';
  panel.style.top = '';
  panel.style.left = '';
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}

function isInteractionEvent(value: string): value is InteractionRule['event'] {
  return ['click', 'hover', 'focus', 'load'].includes(value);
}

function isInteractionAction(value: string): value is InteractionRule['action'] {
  return ['overlay.open', 'overlay.close', 'overlay.toggle', 'class.add', 'class.remove', 'class.toggle', 'custom.emit'].includes(value);
}

function isInteractionDevice(value: string): value is NonNullable<InteractionRule['device']> {
  return ['any', 'desktop', 'tablet', 'mobile'].includes(value);
}

function isInteractionLoginState(value: string): value is NonNullable<InteractionRule['loginState']> {
  return ['any', 'logged-in', 'logged-out'].includes(value);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}