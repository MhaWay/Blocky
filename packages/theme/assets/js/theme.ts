/**
 * Blocky Theme — main JS entry point
 * Handles: island mounting, intersection-based lazy enqueue, runtime init
 */

import '../css/main.css';
import './theme-switch';

// ── Island hydration ──────────────────────────────────────────────────────────

interface IslandModule {
  mount: (el: HTMLElement) => void;
  unmount?: (el: HTMLElement) => void;
}

const mountedIslands = new WeakSet<HTMLElement>();

async function hydrateIsland(el: HTMLElement): Promise<void> {
  if (mountedIslands.has(el)) return;

  const component = el.dataset.island;
  if (!component) return;

  try {
    const mod = (await import(`./islands/${component}.js`)) as IslandModule;
    mod.mount(el);
    mountedIslands.add(el);
    el.removeAttribute('data-island-pending');
  } catch (err) {
    console.error(`[Blocky] Failed to load island "${component}":`, err);
  }
}

// ── IntersectionObserver-based lazy hydration ─────────────────────────────────

const observer = new IntersectionObserver(
  (entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) {
        const el = entry.target as HTMLElement;
        void hydrateIsland(el);
        observer.unobserve(el);
      }
    }
  },
  { rootMargin: '200px' }
);

// ── Immediate hydration for visible islands ───────────────────────────────────

function initIslands(): void {
  const islands = document.querySelectorAll<HTMLElement>('[data-island]');

  for (const el of islands) {
    const strategy = el.dataset.islandStrategy ?? 'visible';

    switch (strategy) {
      case 'immediate':
        void hydrateIsland(el);
        break;
      case 'idle':
        if ('requestIdleCallback' in window) {
          window.requestIdleCallback(() => void hydrateIsland(el));
        } else {
          setTimeout(() => void hydrateIsland(el), 100);
        }
        break;
      case 'visible':
      default:
        el.setAttribute('data-island-pending', '');
        observer.observe(el);
        break;
    }
  }
}

// ── DOM ready ─────────────────────────────────────────────────────────────────

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initIslands);
} else {
  initIslands();
}
