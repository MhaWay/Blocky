import type { BuilderDocument, BuilderNode } from '../sdk/types';

const MANAGED_OVERLAY_TYPES = new Set([
  'bky/modal',
  'bky/offcanvas',
  'bky/drawer',
  'bky/popover',
  'bky/tooltip',
  'bky/dialog-confirm',
  'bky/popup',
  'bky/notification-toast',
  'bky/cookie-banner',
  'bky/lightbox',
  'bky/command-palette',
]);

const SLOTTED_OVERLAY_TYPES = new Set([
  'bky/modal',
  'bky/offcanvas',
  'bky/drawer',
  'bky/popover',
  'bky/tooltip',
  'bky/dialog-confirm',
  'bky/popup',
  'bky/notification-toast',
  'bky/cookie-banner',
  'bky/command-palette',
]);

export function supportsManagedOverlayId(type: string): boolean {
  return MANAGED_OVERLAY_TYPES.has(type);
}

export function supportsOverlaySlots(type: string): boolean {
  return SLOTTED_OVERLAY_TYPES.has(type);
}

export function defaultOverlayId(type: string, nodeId: string): string {
  return type.replace('bky/', '').replace('/', '-') + '-' + nodeId;
}

export function overlayIdForNode(node: BuilderNode): string {
  const configured = String(node.props['overlayId'] ?? '').trim();
  if (configured !== '') return configured;
  return defaultOverlayId(node.type, node.id);
}

export function normalizeManagedOverlayIds(document: BuilderDocument, preferredNodeId?: string): boolean {
  const nodes = Object.values(document.nodes).filter(node => supportsManagedOverlayId(node.type));
  if (nodes.length === 0) return false;

  const orderedNodes = preferredNodeId
    ? nodes.slice().sort((left, right) => {
        if (left.id === preferredNodeId) return -1;
        if (right.id === preferredNodeId) return 1;
        return 0;
      })
    : nodes;

  const seen = new Set<string>();
  let changed = false;

  orderedNodes.forEach(node => {
    const current = String(node.props['overlayId'] ?? '').trim();
    let next = current !== '' ? current : defaultOverlayId(node.type, node.id);
    if (seen.has(next)) {
      next = defaultOverlayId(node.type, node.id);
    }

    if (current !== next) {
      node.props['overlayId'] = next;
      changed = true;
    }

    seen.add(next);
  });

  return changed;
}