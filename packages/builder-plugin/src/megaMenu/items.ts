export interface MegaMenuItem {
  id: string;
  label: string;
  href: string;
  description: string;
  hideLabel: boolean;
  openInNewTab: boolean;
  useCustomContent: boolean;
  children: MegaMenuItem[];
}

export const MEGA_MENU_CUSTOM_SLOT_PREFIX = 'mega-menu-panel-';

export function createMegaMenuItem(seed: Partial<MegaMenuItem> = {}): MegaMenuItem {
  return {
    id: sanitizeMegaMenuItemId(seed.id) || makeMegaMenuItemId(),
    label: typeof seed.label === 'string' && seed.label.trim() !== '' ? seed.label : 'New item',
    href: typeof seed.href === 'string' && seed.href.trim() !== '' ? seed.href : '#',
    description: typeof seed.description === 'string' ? seed.description : '',
    hideLabel: seed.hideLabel === true,
    openInNewTab: seed.openInNewTab === true,
    useCustomContent: seed.useCustomContent === true,
    children: Array.isArray(seed.children)
      ? seed.children
          .map((child) => normalizeMegaMenuItem(child))
          .filter((child): child is MegaMenuItem => child !== null)
      : [],
  };
}

export function defaultMegaMenuItems(): MegaMenuItem[] {
  return [
    createMegaMenuItem({
      id: 'products',
      label: 'Products',
      href: '/products',
      description: 'Explore the Gennaker stack',
      children: [
        createMegaMenuItem({
          id: 'builder',
          label: 'Builder',
          href: '/builder',
          description: 'Compose full layouts visually',
        }),
        createMegaMenuItem({
          id: 'themes',
          label: 'Themes',
          href: '/themes',
          description: 'Ship token-driven themes',
        }),
      ],
    }),
    createMegaMenuItem({
      id: 'resources',
      label: 'Resources',
      href: '/resources',
      description: 'Docs and examples',
      children: [
        createMegaMenuItem({
          id: 'documentation',
          label: 'Documentation',
          href: '/docs',
          description: 'Implementation guides',
        }),
        createMegaMenuItem({
          id: 'showcase',
          label: 'Showcase',
          href: '/showcase',
          description: 'Real-world pages',
        }),
      ],
    }),
  ];
}

export function normalizeMegaMenuItems(value: unknown): MegaMenuItem[] {
  if (typeof value === 'string') {
    return parseLegacyMegaMenuItems(value);
  }

  if (!Array.isArray(value)) {
    return defaultMegaMenuItems();
  }

  const items = value
    .map((entry) => normalizeMegaMenuItem(entry))
    .filter((entry): entry is MegaMenuItem => entry !== null);

  return items.length > 0 ? items : defaultMegaMenuItems();
}

export function megaMenuCustomSlotName(itemId: string): string {
  return `${MEGA_MENU_CUSTOM_SLOT_PREFIX}${sanitizeMegaMenuItemId(itemId) || itemId}`;
}

export function isMegaMenuCustomSlotName(slotName: string): boolean {
  return slotName.startsWith(MEGA_MENU_CUSTOM_SLOT_PREFIX);
}

export function collectMegaMenuCustomSlotNames(items: MegaMenuItem[]): string[] {
  const slotNames = new Set<string>();

  const visit = (entry: MegaMenuItem): void => {
    if (entry.useCustomContent) {
      slotNames.add(megaMenuCustomSlotName(entry.id));
    }
    entry.children.forEach(visit);
  };

  items.forEach(visit);
  return Array.from(slotNames);
}

function normalizeMegaMenuItem(value: unknown): MegaMenuItem | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null;
  }

  const raw = value as Partial<MegaMenuItem> & { children?: unknown };
  const children = Array.isArray(raw.children)
    ? raw.children
        .map((entry) => normalizeMegaMenuItem(entry))
        .filter((entry): entry is MegaMenuItem => entry !== null)
    : [];

  return {
    id: sanitizeMegaMenuItemId(raw.id) || makeMegaMenuItemId(),
    label: typeof raw.label === 'string' && raw.label.trim() !== '' ? raw.label : 'Link',
    href: typeof raw.href === 'string' && raw.href.trim() !== '' ? raw.href : '#',
    description: typeof raw.description === 'string' ? raw.description : '',
    hideLabel: raw.hideLabel === true,
    openInNewTab: raw.openInNewTab === true,
    useCustomContent: raw.useCustomContent === true,
    children,
  };
}

function parseLegacyMegaMenuItems(raw: string): MegaMenuItem[] {
  const normalizedRaw = raw
    .replace(/\\r\\n/g, '\n')
    .replace(/\\n/g, '\n')
    .replace(/\\r/g, '\n');
  const items: MegaMenuItem[] = [];
  let currentIndex = -1;

  for (const sourceLine of normalizedRaw.split(/\r\n|\r|\n/)) {
    const trimmedLine = sourceLine.trim();
    if (trimmedLine === '') {
      continue;
    }

    const isChild = trimmedLine.startsWith('>');
    const line = isChild ? trimmedLine.slice(1).trim() : trimmedLine;
    const [label, href, description] = line.split('|', 3).map((part) => part.trim());
    const nextItem = createMegaMenuItem({
      label: label || (isChild ? 'Sub item' : 'Item'),
      href: href || '#',
      description: description || '',
    });

    if (isChild && currentIndex >= 0) {
      const parent = items[currentIndex];
      if (parent) {
        items[currentIndex] = {
          ...parent,
          children: [...parent.children, nextItem],
        };
      }
      continue;
    }

    items.push(nextItem);
    currentIndex = items.length - 1;
  }

  return items.length > 0 ? items : defaultMegaMenuItems();
}

function makeMegaMenuItemId(): string {
  return `item-${Math.random().toString(36).slice(2, 10)}`;
}

function sanitizeMegaMenuItemId(value: unknown): string {
  if (typeof value !== 'string') {
    return '';
  }

  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}
