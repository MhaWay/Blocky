import { render } from 'preact';
import { App } from './app/App';
import './styles/builder.css';

const root = document.getElementById('blocky-builder-root');

if (root) {
  render(<App />, root);
}
