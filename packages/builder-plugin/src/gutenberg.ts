import './styles/gutenberg-button.css';

interface ConvertConfig {
  restUrl: string;
  nonce: string;
  builderUrl: string;
  postId: number;
  hasDocument: boolean;
  label: string;
  savingLabel: string;
}

interface EditorStore {
  getCurrentPostId?: () => number;
  isCurrentPostAutomaticDraft?: () => boolean;
}

interface EditorDispatch {
  saveDraft?: () => Promise<unknown>;
}

function editorStore(): EditorStore | null {
  const wp = (window as unknown as { wp?: { data?: { select?: (s: string) => unknown } } }).wp;
  return (wp?.data?.select?.('core/editor') as EditorStore | undefined) ?? null;
}

function editorDispatch(): EditorDispatch | null {
  const wp = (window as unknown as { wp?: { data?: { dispatch?: (s: string) => unknown } } }).wp;
  return (wp?.data?.dispatch?.('core/editor') as EditorDispatch | undefined) ?? null;
}

function currentPostId(config: ConvertConfig): number {
  const id = editorStore()?.getCurrentPostId?.() ?? 0;
  return id > 0 ? id : config.postId;
}

async function openInBlocky(config: ConvertConfig, button: HTMLButtonElement): Promise<void> {
  const original = button.textContent ?? '';
  button.disabled = true;

  let postId = currentPostId(config);
  const store = editorStore();
  if (store?.isCurrentPostAutomaticDraft?.()) {
    button.textContent = config.savingLabel;
    try {
      await editorDispatch()?.saveDraft?.();
    } catch {
      // The redirect attempt below still uses the current id.
    }
    postId = currentPostId(config);
  }

  try {
    const response = await fetch(config.restUrl + 'convert/' + postId, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
    });
    const data = (await response.json()) as { url?: string };
    if (typeof data.url === 'string' && data.url !== '') {
      window.location.href = data.url;
      return;
    }
  } catch {
    // Fall through to re-enable the button.
  }
  button.disabled = false;
  button.textContent = original;
}

function insertButton(config: ConvertConfig): boolean {
  const container = document.querySelector<HTMLElement>(
    '.edit-post-header__toolbar, .document-header__primary, .edit-post-header'
  );
  if (!container) {
    return false;
  }
  if (container.querySelector('.blocky-open-builder')) {
    return true;
  }
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'blocky-open-builder';
  button.textContent = config.label;
  button.addEventListener('click', () => {
    void openInBlocky(config, button);
  });
  const first = container.firstElementChild;
  if (first?.nextElementSibling) {
    container.insertBefore(button, first.nextElementSibling);
  } else {
    container.appendChild(button);
  }
  return true;
}

const config = (window as unknown as { blockyConvertConfig?: ConvertConfig }).blockyConvertConfig;
if (config) {
  let tries = 0;
  const timer = window.setInterval(() => {
    tries += 1;
    if (insertButton(config) || tries > 40) {
      window.clearInterval(timer);
    }
  }, 250);
}
