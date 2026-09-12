import { useEffect } from 'preact/hooks';
import type { FunctionComponent } from 'preact';
import { useDocumentStore } from '../store/document';
import { useUiStore } from '../store/ui';
import { useBlockRegistry } from '../store/blockRegistry';
import { useThemeStore } from '../store/theme';
import { Toolbar } from './Toolbar';
import { Footer } from './Footer';
import { PageLibrary } from './PageLibrary';
import { OutlineFloat } from './OutlineFloat';
import { Sidebar } from '../sidebar/Sidebar';
import { Canvas } from '../canvas/Canvas';
import { Inspector } from '../inspector/Inspector';

export const App: FunctionComponent = () => {
  const isLoading    = useDocumentStore(s => s.isLoading);
  const isPageLibraryOpen = useDocumentStore(s => s.isPageLibraryOpen);
  const leftVisible  = useUiStore(s => s.leftPanelVisible);
  const rightVisible = useUiStore(s => s.rightPanelVisible);
  const outlineVisible = useUiStore(s => s.outlineVisible);
  const themeCss     = useThemeStore(s => s.css);

  useEffect(() => {
    // Load block type definitions for the sidebar
    void useBlockRegistry.getState().load();
    void useThemeStore.getState().load();

    // Auto-load post if ?post_id=N is present in the URL
    const params      = new URLSearchParams(window.location.search);
    const postIdParam = params.get('post_id');
    if (postIdParam) {
      const id = parseInt(postIdParam, 10);
      if (!isNaN(id) && id > 0) {
        void useDocumentStore.getState().loadPost(id);
        return;
      }
    }

    useDocumentStore.getState().openPageLibrary();
    void useDocumentStore.getState().loadPageLibrary();
  }, []);

  return (
    <div class="blocky-builder-root relative">
      {themeCss && <style id="blocky-ui-theme">{themeCss}</style>}
      <Toolbar />
      <div class="flex flex-1 overflow-hidden">
        {!isPageLibraryOpen && leftVisible && <Sidebar />}
        <main class="flex-1 overflow-auto bg-surface-base">
          {isLoading ? (
            <div class="flex h-full items-center justify-center text-text-muted">
              Loading…
            </div>
          ) : isPageLibraryOpen ? (
            <PageLibrary />
          ) : (
            <Canvas />
          )}
        </main>
        {!isPageLibraryOpen && rightVisible && <Inspector />}
      </div>
      <Footer />
      {outlineVisible && <OutlineFloat hidden={isPageLibraryOpen} />}
    </div>
  );
};
