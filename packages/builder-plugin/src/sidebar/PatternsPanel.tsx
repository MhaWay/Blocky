import { useEffect, useState } from 'preact/hooks';
import type { FunctionComponent } from 'preact';

import { t } from '../i18n';
import { useDocumentStore } from '../store/document';

/*
 * Sidebar 'Patterns' panel: insert bundled or saved section patterns at the
 * end of the page, and save the current page as a reusable pattern.
 */

export const PatternsPanel: FunctionComponent = () => {
  const patterns = useDocumentStore((s) => s.patterns);
  const patternsLoaded = useDocumentStore((s) => s.patternsLoaded);
  const loadPatterns = useDocumentStore((s) => s.loadPatterns);
  const insertPattern = useDocumentStore((s) => s.insertPattern);
  const savePattern = useDocumentStore((s) => s.savePattern);
  const deletePattern = useDocumentStore((s) => s.deletePattern);
  const [title, setTitle] = useState('');
  const [savedFlash, setSavedFlash] = useState(false);

  useEffect(() => {
    if (!patternsLoaded) void loadPatterns();
  }, [patternsLoaded, loadPatterns]);

  const grouped = new Map<string, typeof patterns>();
  patterns.forEach((p) => {
    const list = grouped.get(p.category) ?? [];
    list.push(p);
    grouped.set(p.category, list);
  });

  const save = async (): Promise<void> => {
    const ok = await savePattern(title.trim());
    if (ok) {
      setTitle('');
      setSavedFlash(true);
      setTimeout(() => setSavedFlash(false), 2000);
    }
  };

  return (
    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2">
      <p class="text-[11px] leading-4 text-text-muted">
        {t('patterns.hint', 'Insert a ready-made section at the end of the page.')}
      </p>
      {[...grouped.entries()].map(([category, list]) => (
        <div key={category}>
          <h3 class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-text-faint">
            {category}
          </h3>
          <div class="flex flex-col gap-1">
            {list.map((p) => (
              <div key={p.id} class="flex items-center gap-1">
                <button
                  type="button"
                  class="flex-1 rounded-input border border-border-base px-2 py-1.5 text-left text-xs hover:bg-surface-hover"
                  onClick={() => insertPattern(p)}
                >
                  {p.title}
                </button>
                {p.id.startsWith('user-') && (
                  <button
                    type="button"
                    aria-label={'Delete ' + p.title}
                    class="px-1 text-xs text-text-muted hover:text-feedback-danger"
                    onClick={() => void deletePattern(p.id)}
                  >
                    ×
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
      <div class="mt-auto flex items-center gap-1 border-t border-border-subtle pt-2">
        <input
          type="text"
          class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-2 py-1 text-xs"
          placeholder={t('patterns.savePlaceholder', 'Name this page as a pattern…')}
          value={title}
          onInput={(e) => setTitle((e.target as HTMLInputElement).value)}
        />
        <button
          type="button"
          class="rounded-input bg-accent-base px-2 py-1 text-xs font-medium text-text-on-accent disabled:opacity-40"
          disabled={!title.trim()}
          onClick={() => void save()}
        >
          {t('patterns.save', 'Save')}
        </button>
      </div>
      {savedFlash && (
        <p class="text-[11px] text-feedback-success">{t('patterns.saved', 'Pattern saved.')}</p>
      )}
    </div>
  );
};
