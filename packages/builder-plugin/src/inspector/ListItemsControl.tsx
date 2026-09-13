import type { FunctionComponent, JSX } from 'preact';
import { t } from '../i18n';
import type { ListFieldDescriptor } from './Inspector';

/**
 * Descriptor-driven editor for pipe-protocol list props (items/points).
 
 * The PHP renderers parse each line as `field1|field2|...` (explode with a
 * trailing limit, so the LAST field may contain pipes). This control mirrors
 * that grammar: rows of typed inputs, serialized back to the exact same
 * string shape. Descriptor comes from the block schema (listFields), so new
 * list blocks need no inspector code (AGENTS rule 5).
 */
export const ListItemsControl: FunctionComponent<{
  label: string;
  fields: ListFieldDescriptor[];
  value: unknown;
  onChange: (v: unknown) => void;
}> = ({ label, fields, value, onChange }) => {
  const raw = String(value ?? '');
  const rows = parseRows(raw, fields.length);

  const emit = (next: string[][]) => {
    onChange(next.map((row) => row.join('|')).join('\n'));
  };

  const setCell = (rowIndex: number, fieldIndex: number, cell: string) => {
    const next = rows.map((row) => [...row]);
    const target = next[rowIndex];
    if (!target) return;
    target[fieldIndex] = cell;
    emit(next);
  };

  const addRow = () => emit([...rows, fields.map(() => '')]);
  const removeRow = (index: number) => emit(rows.filter((_, i) => i !== index));
  const moveRow = (index: number, delta: -1 | 1) => {
    const target = index + delta;
    if (target < 0 || target >= rows.length) return;
    const next = [...rows];
    const [moved] = next.splice(index, 1);
    if (!moved) return;
    next.splice(target, 0, moved);
    emit(next);
  };

  return (
    <div class="space-y-2">
      <span class="text-xs font-medium text-text-muted">{label}</span>
      {rows.length === 0 && (
        <p class="text-xs text-text-faint">{t('inspector.listEmpty', 'No items yet.')}</p>
      )}
      {rows.map((row, rowIndex) => (
        <div
          key={rowIndex}
          class="rounded-input border border-border-subtle bg-surface-base p-2 space-y-2"
          data-list-row={rowIndex}
        >
          <div class="flex flex-col gap-2">
            {fields.map((field, fieldIndex) => (
              <label key={field.key} class="block space-y-1">
                <span class="text-[10px] uppercase tracking-wide text-text-faint">
                  {field.label}
                </span>
                <input
                  type="text"
                  inputmode={field.kind === 'number' ? 'decimal' : undefined}
                  placeholder={
                    field.kind === 'url' || field.kind === 'image' ? 'https://…' : undefined
                  }
                  class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                         text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
                  value={row[fieldIndex] ?? ''}
                  onInput={(e) =>
                    setCell(rowIndex, fieldIndex, (e.target as HTMLInputElement).value)
                  }
                />
              </label>
            ))}
          </div>
          <div class="flex items-center gap-1">
            <IconButton
              title={t('inspector.listMoveUp', 'Move up')}
              disabled={rowIndex === 0}
              onClick={() => moveRow(rowIndex, -1)}
            >
              ↑
            </IconButton>
            <IconButton
              title={t('inspector.listMoveDown', 'Move down')}
              disabled={rowIndex === rows.length - 1}
              onClick={() => moveRow(rowIndex, 1)}
            >
              ↓
            </IconButton>
            <span class="flex-1" />
            <IconButton
              danger
              title={t('inspector.listRemove', 'Remove')}
              onClick={() => removeRow(rowIndex)}
            >
              ✕
            </IconButton>
          </div>
        </div>
      ))}
      <button
        type="button"
        onClick={addRow}
        class="w-full rounded-input border border-dashed border-border-base px-3 py-2 text-sm
               text-text-muted hover:border-accent-base hover:text-accent-text"
      >
        + {t('inspector.listAdd', 'Add item')}
      </button>
    </div>
  );
};

const IconButton: FunctionComponent<{
  title: string;
  disabled?: boolean;
  danger?: boolean;
  onClick: () => void;
  children: JSX.Element | string;
}> = ({ title, disabled, danger, onClick, children }) => (
  <button
    type="button"
    title={title}
    aria-label={title}
    disabled={disabled}
    onClick={onClick}
    class={
      'h-7 w-7 rounded-input border text-xs leading-none disabled:opacity-30 ' +
      (danger
        ? ' border-feedback-danger/40 text-feedback-danger hover:bg-feedback-danger/10'
        : ' border-border-base text-text-muted hover:text-text-base')
    }
  >
    {children}
  </button>
);

function parseRows(raw: string, width: number): string[][] {
  if (raw === '') return [];
  return raw
    .split('\n')
    .filter((line) => line.trim() !== '')
    .map((line) => {
      const parts = line.split('|');
      return Array.from({ length: width }, (_, index) => {
        if (index === width - 1) return (parts.slice(index).join('|') ?? '').trim();
        return (parts[index] ?? '').trim();
      });
    });
}
