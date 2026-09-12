import { create } from 'zustand';
import type { BlockDefinition } from '../sdk/types';

const API_BASE = window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/';
const NONCE    = window.BlockyBuilderConfig?.nonce    ?? '';

interface BlockRegistryState {
  definitions: BlockDefinition[];
  isLoaded:    boolean;
  load: () => Promise<void>;
}

export const useBlockRegistry = create<BlockRegistryState>()(set => ({
  definitions: [],
  isLoaded:    false,

  async load() {
    try {
      const res = await fetch(`${API_BASE}blocks`, {
        headers: { 'X-WP-Nonce': NONCE },
      });
      if (!res.ok) {
        set({ isLoaded: true });
        return;
      }
      const data = await res.json() as BlockDefinition[];
      set({ definitions: Array.isArray(data) ? data : [], isLoaded: true });
    } catch {
      set({ isLoaded: true });
    }
  },
}));
