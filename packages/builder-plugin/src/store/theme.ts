import { create } from 'zustand';

const API_BASE = window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/';
const NONCE    = window.BlockyBuilderConfig?.nonce    ?? '';

export interface ThemeTokenMeta {
  key:    string;
  cssVar: string;
  label:  string;
  type:   'color' | 'dimension' | 'number' | 'font' | 'shadow' | 'text';
}

export interface ThemeTokenGroup {
  id:     string;
  label:  string;
  tokens: ThemeTokenMeta[];
}

export interface ThemeVariantSettings {
  id:        string;
  label:     string;
  brand:     string;
  mode:      'light' | 'dark';
  defaults:  Record<string, string>;
  overrides: Record<string, string>;
  values:    Record<string, string>;
}

interface ThemeSettingsResponse {
  groups:   ThemeTokenGroup[];
  variants: Record<string, ThemeVariantSettings>;
  css:      string;
}

interface ThemeState {
  groups:     ThemeTokenGroup[];
  variants:   Record<string, ThemeVariantSettings>;
  overrides:  Record<string, Record<string, string>>;
  css:        string;
  isLoading:  boolean;
  isSaving:   boolean;
  isDirty:    boolean;

  load:       () => Promise<void>;
  save:       () => Promise<void>;
  setToken:   (variantId: string, key: string, value: string) => void;
  resetToken: (variantId: string, key: string) => void;
  resetAll:   () => void;
}

export const useThemeStore = create<ThemeState>()((set, get) => ({
  groups:    [],
  variants:  {},
  overrides: {},
  css:       '',
  isLoading: false,
  isSaving:  false,
  isDirty:   false,

  async load() {
    set({ isLoading: true });
    try {
      const res = await fetch(`${API_BASE}themes/tokens`, {
        headers: { 'X-WP-Nonce': NONCE },
      });
      if (!res.ok) {
        set({ isLoading: false });
        return;
      }
      const data = await res.json() as ThemeSettingsResponse;
      set({
        groups:    Array.isArray(data.groups) ? data.groups : [],
        variants:  data.variants ?? {},
        overrides: overridesFromVariants(data.variants ?? {}),
        css:       data.css ?? '',
        isLoading: false,
        isDirty:   false,
      });
    } catch {
      set({ isLoading: false });
    }
  },

  async save() {
    const { overrides } = get();
    set({ isSaving: true });
    try {
      const res = await fetch(`${API_BASE}themes/tokens`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        body:    JSON.stringify({ overrides }),
      });
      if (!res.ok) {
        set({ isSaving: false });
        return;
      }
      const data = await res.json() as ThemeSettingsResponse;
      set({
        groups:    Array.isArray(data.groups) ? data.groups : [],
        variants:  data.variants ?? {},
        overrides: overridesFromVariants(data.variants ?? {}),
        css:       data.css ?? '',
        isSaving:  false,
        isDirty:   false,
      });
    } catch {
      set({ isSaving: false });
    }
  },

  setToken(variantId, key, value) {
    set(state => {
      const variant = state.variants[variantId];
      if (!variant) return state;

      const cleanValue = value.trim();
      const nextOverrides = cloneOverrides(state.overrides);
      const nextVariantOverrides = { ...(nextOverrides[variantId] ?? {}) };
      const defaultValue = variant.defaults[key] ?? '';

      if (cleanValue === '' || cleanValue === defaultValue) {
        delete nextVariantOverrides[key];
      } else {
        nextVariantOverrides[key] = cleanValue;
      }

      if (Object.keys(nextVariantOverrides).length === 0) {
        delete nextOverrides[variantId];
      } else {
        nextOverrides[variantId] = nextVariantOverrides;
      }

      return stateFromOverrides(state, nextOverrides);
    });
  },

  resetToken(variantId, key) {
    set(state => {
      const nextOverrides = cloneOverrides(state.overrides);
      const nextVariantOverrides = { ...(nextOverrides[variantId] ?? {}) };
      delete nextVariantOverrides[key];
      if (Object.keys(nextVariantOverrides).length === 0) {
        delete nextOverrides[variantId];
      } else {
        nextOverrides[variantId] = nextVariantOverrides;
      }
      return stateFromOverrides(state, nextOverrides);
    });
  },

  resetAll() {
    set(state => stateFromOverrides(state, {}));
  },
}));

function overridesFromVariants(variants: Record<string, ThemeVariantSettings>): Record<string, Record<string, string>> {
  const result: Record<string, Record<string, string>> = {};
  for (const [variantId, variant] of Object.entries(variants)) {
    if (Object.keys(variant.overrides ?? {}).length > 0) {
      result[variantId] = { ...variant.overrides };
    }
  }
  return result;
}

function cloneOverrides(overrides: Record<string, Record<string, string>>): Record<string, Record<string, string>> {
  return Object.fromEntries(
    Object.entries(overrides).map(([variantId, values]) => [variantId, { ...values }]),
  );
}

function stateFromOverrides(state: ThemeState, overrides: Record<string, Record<string, string>>): Partial<ThemeState> {
  const variants = Object.fromEntries(
    Object.entries(state.variants).map(([variantId, variant]) => {
      const variantOverrides = overrides[variantId] ?? {};
      return [variantId, {
        ...variant,
        overrides: variantOverrides,
        values:    { ...variant.defaults, ...variantOverrides },
      }];
    }),
  );

  return {
    variants,
    overrides,
    css:      cssFromOverrides(overrides),
    isDirty:  true,
  };
}

function cssFromOverrides(overrides: Record<string, Record<string, string>>): string {
  const light = overrides['default--light'] ?? {};
  const dark  = overrides['default--dark'] ?? {};
  const blocks: string[] = [];

  if (Object.keys(light).length > 0) {
    blocks.push(cssBlock(':root', light));
  }
  if (Object.keys(dark).length > 0) {
    blocks.push(cssBlock(':root[data-mode="dark"]', dark));
    blocks.push(`@media (prefers-color-scheme: dark) {\n${cssBlock(':root[data-mode="auto"]', dark, '  ')}\n}`);
  }

  return blocks.join('\n\n');
}

function cssBlock(selector: string, values: Record<string, string>, indent = ''): string {
  const declarations = Object.entries(values)
    .map(([key, value]) => `${indent}  --bky-${key}: ${value};`)
    .join('\n');
  return `${indent}${selector} {\n${declarations}\n${indent}}`;
}