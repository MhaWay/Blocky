import { render } from 'preact';
import { App } from './app/App';
import { historyDepths, redo, undo, useDocumentStore } from './store/document';
import { useUiStore } from './store/ui';
import './styles/builder.css';

/* Test/debug surface: e2e asserts history depths without parsing DOM. */
(window as unknown as { BlockyBuilderDebug: unknown }).BlockyBuilderDebug = {
  undo,
  redo,
  historyDepths,
  store: useDocumentStore,
  ui: useUiStore,
};

const root = document.getElementById('blocky-builder-root');

if (root) {
  render(<App />, root);
}
