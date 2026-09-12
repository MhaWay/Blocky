/**
 * Blocky Theme — Runtime Theme Switcher
 * Handles: mode (light/dark/auto), brand switching, persistence via cookie + localStorage
 */

const COOKIE_MODE  = 'bky_mode';
const COOKIE_BRAND = 'bky_brand';
const STORAGE_MODE  = 'bky-theme-mode';
const STORAGE_BRAND = 'bky-theme-brand';

// ── Types ─────────────────────────────────────────────────────────────────────

export type ThemeMode = 'light' | 'dark' | 'auto';
export type ThemeBrand = string;

export interface ThemeState {
  mode: ThemeMode;
  brand: ThemeBrand;
}

// ── Loaded stylesheets cache ──────────────────────────────────────────────────

const loadedStylesheets = new Map<string, Promise<void>>();

async function ensureStylesheet(href: string): Promise<void> {
  if (document.querySelector(`link[href="${href}"]`)) return;

  if (loadedStylesheets.has(href)) {
    return loadedStylesheets.get(href);
  }

  const promise = new Promise<void>((resolve, reject) => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.onload = () => resolve();
    link.onerror = () => reject(new Error(`Failed to load stylesheet: ${href}`));
    document.head.appendChild(link);
  });

  loadedStylesheets.set(href, promise);
  return promise;
}

// ── Cookie helpers ─────────────────────────────────────────────────────────────

function setCookie(name: string, value: string, days = 365): void {
  const expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
}

// ── Current state ─────────────────────────────────────────────────────────────

export function getThemeState(): ThemeState {
  const mode  = (document.documentElement.dataset.mode  ?? 'auto') as ThemeMode;
  const brand = document.documentElement.dataset.brand ?? 'default';
  return { mode, brand };
}

// ── Setters ───────────────────────────────────────────────────────────────────

export async function setMode(mode: ThemeMode): Promise<void> {
  document.documentElement.dataset.mode = mode;

  const isDark =
    mode === 'dark' ||
    (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

  document.documentElement.classList.toggle('dark', isDark);

  setCookie(COOKIE_MODE, mode);
  localStorage.setItem(STORAGE_MODE, mode);

  // Dispatch event for external listeners (e.g. builder, analytics)
  window.dispatchEvent(new CustomEvent('bky:mode-change', { detail: { mode, isDark } }));
}

export async function setBrand(brand: ThemeBrand): Promise<void> {
  const baseUrl = (document.documentElement.dataset.themeUrl ?? '') as string;

  // Preload the brand CSS before switching to avoid flash
  if (brand !== 'default') {
    const cssUrl = `${baseUrl}/dist/brands/${brand}.css`;
    try {
      await ensureStylesheet(cssUrl);
    } catch {
      console.warn(`[Blocky] Brand stylesheet not found: ${brand}`);
      return;
    }
  }

  document.documentElement.dataset.brand = brand;
  setCookie(COOKIE_BRAND, brand);
  localStorage.setItem(STORAGE_BRAND, brand);

  window.dispatchEvent(new CustomEvent('bky:brand-change', { detail: { brand } }));
}

export async function setTheme(state: Partial<ThemeState>): Promise<void> {
  const current = getThemeState();
  const next = { ...current, ...state };

  if (state.brand !== undefined && state.brand !== current.brand) {
    await setBrand(state.brand);
  }
  if (state.mode !== undefined && state.mode !== current.mode) {
    await setMode(state.mode);
  }
}

// ── Respond to OS preference change ──────────────────────────────────────────

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
  if (getThemeState().mode === 'auto') {
    document.documentElement.classList.toggle('dark', e.matches);
  }
});

// ── Expose on window for non-module usage ─────────────────────────────────────

declare global {
  interface Window {
    blockyTheme: {
      setMode: typeof setMode;
      setBrand: typeof setBrand;
      setTheme: typeof setTheme;
      getState: typeof getThemeState;
    };
  }
}

window.blockyTheme = { setMode, setBrand, setTheme, getState: getThemeState };
