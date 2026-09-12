import type { FunctionComponent } from 'preact';
import { useState } from 'preact/hooks';
import { createMegaMenuItem, defaultMegaMenuItems, normalizeMegaMenuItems, type MegaMenuItem } from '../megaMenu/items';
import { t } from '../i18n';

interface MegaMenuItemsControlProps {
  label:    string;
  value:    unknown;
  onChange: (items: MegaMenuItem[]) => void;
}

export const MegaMenuItemsControl: FunctionComponent<MegaMenuItemsControlProps> = ({ label, value, onChange }) => {
  const items = normalizeMegaMenuItems(value);
  const [expandedIds, setExpandedIds] = useState<Set<string>>(() => new Set(items.map(item => item.id)));

  const updateItems = (nextItems: MegaMenuItem[]): void => {
    onChange(nextItems.length > 0 ? nextItems : defaultMegaMenuItems());
  };

  const appendTopLevelItem = (): void => {
    const nextItem = createMegaMenuItem({ label: t('inspector.megaMenu.newOption', 'New option') });
    setExpandedIds(prev => new Set(prev).add(nextItem.id));
    updateItems([...items, nextItem]);
  };

  return (
    <div class="space-y-3">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-xs font-medium text-text-muted">{label}</p>
          <p class="text-xs text-text-faint">{t('inspector.megaMenu.hint', 'Build top-level options, submenu items, and optional custom block areas.')}</p>
        </div>
        <button
          type="button"
          onClick={appendTopLevelItem}
          class="rounded-input border border-border-base bg-surface-base px-2.5 py-1.5 text-xs font-medium text-text-base transition-colors hover:border-accent-base hover:text-accent-text"
        >
          {t('inspector.megaMenu.addOption', 'Add option')}
        </button>
      </div>

      <div class="space-y-3">
        {items.map((item, index) => (
          <MegaMenuTreeItemEditor
            key={item.id}
            item={item}
            depth={0}
            path={[index]}
            expandedIds={expandedIds}
            onToggleExpanded={itemId => {
              setExpandedIds(prev => {
                const next = new Set(prev);
                if (next.has(itemId)) {
                  next.delete(itemId);
                } else {
                  next.add(itemId);
                }
                return next;
              });
            }}
            onChangeItem={(path, updater) => updateItems(updateItemAtPath(items, path, updater))}
            onRemoveItem={path => updateItems(removeItemAtPath(items, path))}
            onAddChild={path => {
              const nextItem = createMegaMenuItem({ label: t('inspector.megaMenu.newSubItem', 'New sub item') });
              setExpandedIds(prev => new Set(prev).add(nextItem.id));
              updateItems(addChildAtPath(items, path, nextItem));
            }}
          />
        ))}
      </div>
    </div>
  );
};

interface MegaMenuTreeItemEditorProps {
  item:             MegaMenuItem;
  depth:            number;
  path:             number[];
  expandedIds:      Set<string>;
  onToggleExpanded: (itemId: string) => void;
  onChangeItem:     (path: number[], updater: (item: MegaMenuItem) => MegaMenuItem) => void;
  onRemoveItem:     (path: number[]) => void;
  onAddChild:       (path: number[]) => void;
}

const MegaMenuTreeItemEditor: FunctionComponent<MegaMenuTreeItemEditorProps> = ({
  item,
  depth,
  path,
  expandedIds,
  onToggleExpanded,
  onChangeItem,
  onRemoveItem,
  onAddChild,
}) => {
  const isExpanded = expandedIds.has(item.id);
  const hasChildren = item.children.length > 0;
  const canUseCustomContent = depth > 0;

  return (
    <div class={`space-y-3 rounded-lg border border-border-subtle bg-surface-base p-3 ${depth > 0 ? 'ml-4' : ''}`}>
      <div class="flex items-start justify-between gap-3">
        <button
          type="button"
          onClick={() => onToggleExpanded(item.id)}
          class="min-w-0 flex-1 text-left"
        >
          <span class="flex items-center gap-2 text-sm font-semibold text-text-base">
            <span aria-hidden="true" class="text-text-faint">{isExpanded ? '▾' : '▸'}</span>
            <span class="truncate">{item.label || t('inspector.megaMenu.untitled', 'Untitled item')}</span>
          </span>
          <span class="mt-1 block text-xs text-text-faint">
            {depth === 0
              ? t('inspector.megaMenu.topLevelHint', 'Top-level option')
              : item.useCustomContent
                ? t('inspector.megaMenu.customContent', 'Custom block content')
                : hasChildren
                  ? t('inspector.megaMenu.submenuCount', '%s nested items with flyout', [String(item.children.length)])
                  : t('inspector.megaMenu.linkItem', 'Simple link item')}
          </span>
        </button>
        <button
          type="button"
          onClick={() => onRemoveItem(path)}
          class="text-xs font-medium text-feedback-danger hover:underline"
        >
          {t('inspector.remove', 'Remove')}
        </button>
      </div>

      {isExpanded && (
        <div class="space-y-3">
          <InspectorTextField
            label={t('inspector.megaMenu.label', 'Label')}
            value={item.label}
            onInput={nextValue => onChangeItem(path, current => ({ ...current, label: nextValue || t('inspector.megaMenu.untitled', 'Untitled item') }))}
          />
          <InspectorTextField
            label={t('inspector.megaMenu.link', 'Link')}
            value={item.href}
            onInput={nextValue => onChangeItem(path, current => ({ ...current, href: nextValue || '#' }))}
          />
          <InspectorTextareaField
            label={t('inspector.megaMenu.description', 'Description')}
            value={item.description}
            onInput={nextValue => onChangeItem(path, current => ({ ...current, description: nextValue }))}
          />
          <label class="flex items-center justify-between gap-3">
            <span class="text-xs font-medium text-text-muted">{t('inspector.megaMenu.openInNewTab', 'Open in new tab')}</span>
            <input
              type="checkbox"
              checked={item.openInNewTab}
              onChange={event => onChangeItem(path, current => ({ ...current, openInNewTab: (event.target as HTMLInputElement).checked }))}
              class="h-4 w-4 rounded accent-accent-base"
            />
          </label>

          {depth > 0 && (
            <label class="flex items-center justify-between gap-3">
              <span class="text-xs font-medium text-text-muted">{t('inspector.megaMenu.hideLabel', 'Hide label')}</span>
              <input
                type="checkbox"
                checked={item.hideLabel}
                onChange={event => onChangeItem(path, current => ({ ...current, hideLabel: (event.target as HTMLInputElement).checked }))}
                class="h-4 w-4 rounded accent-accent-base"
              />
            </label>
          )}

          {canUseCustomContent && (
            <label class="flex items-center justify-between gap-3">
              <span class="text-xs font-medium text-text-muted">{t('inspector.megaMenu.useCustomBlocks', 'Use custom blocks')}</span>
              <input
                type="checkbox"
                checked={item.useCustomContent}
                onChange={event => onChangeItem(path, current => ({ ...current, useCustomContent: (event.target as HTMLInputElement).checked }))}
                class="h-4 w-4 rounded accent-accent-base"
              />
            </label>
          )}

          {item.useCustomContent && canUseCustomContent && (
            <p class="rounded-input border border-border-subtle bg-surface-elevated px-3 py-2 text-xs leading-5 text-text-faint">
              {t('inspector.megaMenu.customBlocksHint', 'Add blocks in the canvas drop zone for this item. Those blocks will control the content shown in the panel.')}
            </p>
          )}

          {!item.useCustomContent && (
            <div class="space-y-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-medium text-text-muted">
                  {depth === 0 ? t('inspector.megaMenu.subItems', 'Dropdown items') : t('inspector.megaMenu.nestedItems', 'Nested items')}
                </p>
                <button
                  type="button"
                  onClick={() => onAddChild(path)}
                  class="rounded-input border border-border-base bg-surface-base px-2.5 py-1.5 text-xs font-medium text-text-base transition-colors hover:border-accent-base hover:text-accent-text"
                >
                  {depth === 0 ? t('inspector.megaMenu.addSubItem', 'Add sub item') : t('inspector.megaMenu.addNestedItem', 'Add nested item')}
                </button>
              </div>

              {item.children.length === 0 && (
                <p class="text-xs text-text-faint">
                  {depth === 0
                    ? t('inspector.megaMenu.noSubItems', 'Add submenu items to turn this option into a dropdown panel.')
                    : t('inspector.megaMenu.noNestedItems', 'This item currently behaves like a simple link until you add another flyout level or custom content.')}
                </p>
              )}

              {item.children.map((child, index) => (
                <MegaMenuTreeItemEditor
                  key={child.id}
                  item={child}
                  depth={depth + 1}
                  path={[...path, index]}
                  expandedIds={expandedIds}
                  onToggleExpanded={onToggleExpanded}
                  onChangeItem={onChangeItem}
                  onRemoveItem={onRemoveItem}
                  onAddChild={onAddChild}
                />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

interface InspectorFieldProps {
  label:   string;
  value:   string;
  onInput: (value: string) => void;
}

const InspectorTextField: FunctionComponent<InspectorFieldProps> = ({ label, value, onInput }) => (
  <label class="block space-y-1">
    <span class="text-xs font-medium text-text-muted">{label}</span>
    <input
      type="text"
      value={value}
      onInput={event => onInput((event.target as HTMLInputElement).value)}
      class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
    />
  </label>
);

const InspectorTextareaField: FunctionComponent<InspectorFieldProps> = ({ label, value, onInput }) => (
  <label class="block space-y-1">
    <span class="text-xs font-medium text-text-muted">{label}</span>
    <textarea
      rows={3}
      value={value}
      onInput={event => onInput((event.target as HTMLTextAreaElement).value)}
      class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
    />
  </label>
);

function updateItemAtPath(items: MegaMenuItem[], path: number[], updater: (item: MegaMenuItem) => MegaMenuItem): MegaMenuItem[] {
  const [index, ...rest] = path;
  return items.map((item, currentIndex) => {
    if (currentIndex !== index) {
      return item;
    }

    if (rest.length === 0) {
      return updater(item);
    }

    return {
      ...item,
      children: updateItemAtPath(item.children, rest, updater),
    };
  });
}

function removeItemAtPath(items: MegaMenuItem[], path: number[]): MegaMenuItem[] {
  const [index, ...rest] = path;

  if (rest.length === 0) {
    return items.filter((_, currentIndex) => currentIndex !== index);
  }

  return items.map((item, currentIndex) => {
    if (currentIndex !== index) {
      return item;
    }

    return {
      ...item,
      children: removeItemAtPath(item.children, rest),
    };
  });
}

function addChildAtPath(items: MegaMenuItem[], path: number[], child: MegaMenuItem): MegaMenuItem[] {
  return updateItemAtPath(items, path, item => ({
    ...item,
    useCustomContent: false,
    children: [...item.children, child],
  }));
}