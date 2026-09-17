import { create } from 'zustand';

type Panel = 'blocks' | 'pages' | 'patterns' | 'templates' | 'theme';
type DeviceMode = 'desktop' | 'tablet' | 'mobile';
type ResponsiveBreakpoint = 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
type PreviewThemeMode = 'light' | 'dark';
const PREVIEW_THEME_COOKIE = 'bky_builder_preview_mode';

interface FloatingPosition {
  x: number;
  y: number;
}

interface UiState {
  postId: number | null;
  selectedNodeId: string | null;
  hoveredNodeId: string | null;
  previewOverlayNodeId: string | null;
  activePanel: Panel;
  leftPanelVisible: boolean;
  rightPanelVisible: boolean;
  deviceMode: DeviceMode;
  responsiveBreakpoint: ResponsiveBreakpoint;
  previewThemeMode: PreviewThemeMode;
  previewFrameNonce: number;
  outlineVisible: boolean;
  outlinePosition: FloatingPosition;

  selectNode: (id: string | null) => void;
  hoverNode: (id: string | null) => void;
  setPreviewOverlay: (nodeId: string | null) => void;
  setActivePanel: (panel: Panel) => void;
  setPostId: (id: number) => void;
  toggleLeftPanel: () => void;
  toggleRightPanel: () => void;
  setDeviceMode: (mode: DeviceMode) => void;
  setResponsiveBreakpoint: (breakpoint: ResponsiveBreakpoint) => void;
  togglePreviewThemeMode: () => void;
  invalidatePreviewFrame: () => void;
  toggleOutline: () => void;
  setOutlineVisible: (visible: boolean) => void;
  setOutlinePosition: (position: FloatingPosition) => void;
}

export const useUiStore = create<UiState>()((set) => ({
  postId: null,
  selectedNodeId: null,
  hoveredNodeId: null,
  previewOverlayNodeId: null,
  activePanel: 'blocks',
  leftPanelVisible: true,
  rightPanelVisible: true,
  deviceMode: 'desktop',
  responsiveBreakpoint: 'base',
  previewThemeMode: readPreviewThemeCookie(),
  previewFrameNonce: 0,
  outlineVisible: false,
  outlinePosition: defaultOutlinePosition(),

  selectNode: (id) => set({ selectedNodeId: id }),
  hoverNode: (id) => set({ hoveredNodeId: id }),
  setPreviewOverlay: (nodeId) => set({ previewOverlayNodeId: nodeId }),
  setActivePanel: (panel) => set({ activePanel: panel }),
  setPostId: (id) => set({ postId: id }),
  toggleLeftPanel: () => set((s) => ({ leftPanelVisible: !s.leftPanelVisible })),
  toggleRightPanel: () => set((s) => ({ rightPanelVisible: !s.rightPanelVisible })),
  setDeviceMode: (mode) => set({ deviceMode: mode }),
  setResponsiveBreakpoint: (breakpoint) => set({ responsiveBreakpoint: breakpoint }),
  togglePreviewThemeMode: () =>
    set((s) => {
      const nextMode = s.previewThemeMode === 'dark' ? 'light' : 'dark';
      writeCookie(PREVIEW_THEME_COOKIE, nextMode);
      return {
        previewThemeMode: nextMode,
        previewFrameNonce: s.previewFrameNonce + 1,
      };
    }),
  invalidatePreviewFrame: () => set((s) => ({ previewFrameNonce: s.previewFrameNonce + 1 })),
  toggleOutline: () => set((s) => ({ outlineVisible: !s.outlineVisible })),
  setOutlineVisible: (visible) => set({ outlineVisible: visible }),
  setOutlinePosition: (position) => set({ outlinePosition: position }),
}));

function defaultOutlinePosition(): FloatingPosition {
  if (typeof window === 'undefined') {
    return { x: 24, y: 88 };
  }

  return {
    x: Math.max(24, window.innerWidth - 364),
    y: 88,
  };
}

function readPreviewThemeCookie(): PreviewThemeMode {
  if (typeof document === 'undefined') return 'light';
  const value = readCookie(PREVIEW_THEME_COOKIE);
  return value === 'dark' ? 'dark' : 'light';
}

function readCookie(name: string): string {
  if (typeof document === 'undefined') return '';
  const encodedName = `${encodeURIComponent(name)}=`;
  const entry = document.cookie
    .split(';')
    .map((value) => value.trim())
    .find((value) => value.startsWith(encodedName));

  if (!entry) return '';

  try {
    return decodeURIComponent(entry.slice(encodedName.length));
  } catch {
    return entry.slice(encodedName.length);
  }
}

function writeCookie(name: string, value: string, days = 365): void {
  if (typeof document === 'undefined') return;
  const expires = new Date();
  expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
  document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
}
