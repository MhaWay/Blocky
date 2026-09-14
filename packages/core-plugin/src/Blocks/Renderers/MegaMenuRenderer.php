<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class MegaMenuRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $items = $this->resolveItems($node);
        $rootClasses = $ctx->resolveVariantClasses($node, [
            'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
            'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
        ]);
        $columnsClass = $ctx->resolveVariantClasses($node, ['columns' => ['2' => 'grid-cols-1 md:grid-cols-2', '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3', '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4']]);
        $panelWidthClass = $ctx->resolveVariantClasses($node, ['panelWidth' => ['md' => 'w-[36rem] max-w-[calc(100vw-2rem)]', 'lg' => 'w-[48rem] max-w-[calc(100vw-2rem)]', 'xl' => 'w-[60rem] max-w-[calc(100vw-2rem)]', 'full' => 'w-[min(72rem,calc(100vw-2rem))]']]);
        $openOn = (string) ($node->props['openOn'] ?? 'hover');

        $html = implode('', array_map(fn(array $item): string => $this->renderItem($node, $item, $ctx, $openOn, $columnsClass, $panelWidthClass), $items));

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Mega navigation']), '<ul class="flex flex-wrap items-center list-none pl-0 ' . \esc_attr($rootClasses) . '">' . $html . '</ul>');
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveItems(Node $node): array
    {
        $menuLocation = trim((string) ($node->props['menuLocation'] ?? ''));
        if ($menuLocation !== '') {
            $wpItems = $this->fromWpMenuLocation($menuLocation);
            if ($wpItems !== []) {
                return $wpItems;
            }
        }

        $rawItems = $node->props['items'] ?? [];
        if (is_string($rawItems)) {
            return $this->parseItems($rawItems);
        }

        if (is_array($rawItems)) {
            $items = $this->normalizeItems($rawItems);
            if ($items !== []) {
                return $items;
            }
        }

        return $this->defaultItems();
    }

    /** @return array<int, array<string, mixed>> */
    private function fromWpMenuLocation(string $location): array
    {
        if (!function_exists('get_nav_menu_locations') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $locations = get_nav_menu_locations();
        $menuId = isset($locations[$location]) ? (int) $locations[$location] : 0;
        if ($menuId <= 0) {
            return [];
        }

        $entries = wp_get_nav_menu_items($menuId);
        if (!is_array($entries)) {
            return [];
        }

        $byParent = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof \WP_Post) {
                continue;
            }
            $parent = (int) ($entry->menu_item_parent ?? 0);
            $byParent[$parent][] = $entry;
        }

        $items = [];
        foreach ($byParent[0] ?? [] as $entry) {
            $children = [];
            foreach ($byParent[(int) $entry->ID] ?? [] as $child) {
                $children[] = [
                    'id' => 'item-' . (int) $child->ID,
                    'label' => trim((string) $child->title) !== '' ? (string) $child->title : 'Link',
                    'href' => (string) \get_post_meta($child->ID, '_menu_item_url', true) ?: '#',
                    'description' => trim((string) $child->post_excerpt),
                    'hideLabel' => false,
                    'openInNewTab' => false,
                    'useCustomContent' => false,
                    'children' => [],
                ];
            }

            $items[] = [
                'id' => 'item-' . (int) $entry->ID,
                'label' => trim((string) $entry->title) !== '' ? (string) $entry->title : 'Section',
                'href' => (string) \get_post_meta($entry->ID, '_menu_item_url', true) ?: '#',
                'description' => trim((string) $entry->post_excerpt),
                'hideLabel' => false,
                'openInNewTab' => false,
                'useCustomContent' => false,
                'children' => $children,
            ];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseItems(string $raw): array
    {
        $raw = str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", "\n"], $raw);
        $items = [];
        $currentIndex = -1;

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $isChild = str_starts_with($line, '>');
            if ($isChild) {
                $line = ltrim(substr($line, 1));
            }

            [$label, $href, $description] = array_pad(array_map('trim', explode('|', $line, 3)), 3, '');
            $entry = [
                'id' => 'item-' . substr(md5($line . ':' . (string) count($items)), 0, 8),
                'label' => $label !== '' ? $label : 'Link',
                'href' => $href !== '' ? $href : '#',
                'description' => $description,
                'hideLabel' => false,
                'openInNewTab' => false,
                'useCustomContent' => false,
                'children' => [],
            ];

            if ($isChild && $currentIndex >= 0) {
                $items[$currentIndex]['children'][] = $entry;
                continue;
            }

            $items[] = $entry;
            $currentIndex = count($items) - 1;
        }

        return $items !== [] ? $items : $this->defaultItems();
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeItems(array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }
            $normalized = $this->normalizeItem($rawItem);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $rawItem
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $rawItem): ?array
    {
        $children = [];
        foreach ($rawItem['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $normalizedChild = $this->normalizeItem($child);
            if ($normalizedChild !== null) {
                $children[] = $normalizedChild;
            }
        }

        $label = trim((string) ($rawItem['label'] ?? ''));
        $href = trim((string) ($rawItem['href'] ?? ''));
        $itemId = $this->normalizeItemId((string) ($rawItem['id'] ?? ''));

        return [
            'id' => $itemId !== '' ? $itemId : 'item-' . substr(md5(\wp_json_encode($rawItem) ?: \uniqid('', true)), 0, 8),
            'label' => $label !== '' ? $label : 'Link',
            'href' => $href !== '' ? $href : '#',
            'description' => trim((string) ($rawItem['description'] ?? '')),
            'hideLabel' => !empty($rawItem['hideLabel']),
            'openInNewTab' => !empty($rawItem['openInNewTab']),
            'useCustomContent' => !empty($rawItem['useCustomContent']),
            'children' => $children,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultItems(): array
    {
        return [[
            'id' => 'products',
            'label' => 'Products',
            'href' => '/products',
            'description' => 'Explore the stack',
            'hideLabel' => false,
            'openInNewTab' => false,
            'useCustomContent' => false,
            'children' => [
                ['id' => 'builder', 'label' => 'Builder', 'href' => '/builder', 'description' => 'Compose full layouts visually', 'hideLabel' => false, 'openInNewTab' => false, 'useCustomContent' => false, 'children' => []],
                ['id' => 'themes', 'label' => 'Themes', 'href' => '/themes', 'description' => 'Ship token-driven themes', 'hideLabel' => false, 'openInNewTab' => false, 'useCustomContent' => false, 'children' => []],
            ],
        ]];
    }

    /** @param array<string, mixed> $item */
    private function renderItem(Node $node, array $item, RenderContext $ctx, string $openOn, string $columnsClass, string $panelWidthClass): string
    {
        $editorMode = $ctx->isEditorMode();
        $hasPanel = $this->itemHasPanel($item);
        $linkHtml = $this->renderLink($item, 'inline-flex items-center gap-2 text-sm font-semibold text-text-base hover:text-accent-text');

        if (!$hasPanel) {
            return '<li>' . $linkHtml . '</li>';
        }

        $toggleHtml = '<span class="inline-flex items-center gap-2">' . $linkHtml . '<span aria-hidden="true">▾</span></span>';
        $panelItems = array_map(fn(array $child): string => $this->renderPanelItem($node, $child, $ctx, 0), $item['children']);
        $panelClasses = $editorMode
            ? 'mt-4 z-20 max-w-full overflow-x-auto rounded-xl border border-border-base bg-surface-base p-5 shadow-xl'
            : 'absolute left-0 top-full mt-4 hidden z-20 rounded-xl border border-border-base bg-surface-base p-5 shadow-xl group-hover:block group-focus-within:block ' . \esc_attr($panelWidthClass);
        $panel = '<div class="' . \esc_attr($panelClasses) . '"><div class="grid gap-4 ' . \esc_attr($columnsClass) . '">' . implode('', $panelItems) . '</div></div>';

        if ($openOn === 'click' && !$editorMode) {
            return '<li><details class="group relative"><summary class="list-none cursor-pointer">' . $toggleHtml . '</summary>' . $panel . '</details></li>';
        }

        return '<li class="group relative">' . $toggleHtml . $panel . '</li>';
    }

    /** @param array<string, mixed> $item */
    private function renderPanelItem(Node $node, array $item, RenderContext $ctx, int $depth): string
    {
        $hasChildren = !empty($item['children']);
        $hasCustomContent = !empty($item['useCustomContent']);
        $hideLabel = $depth > 0 && !empty($item['hideLabel']);
        $linkHtml = $this->renderLink($item, 'block text-sm font-semibold text-text-base hover:text-accent-text', $hideLabel);
        $description = trim((string) ($item['description'] ?? ''));
        $descriptionHtml = ($description !== '' && !$hideLabel) ? '<p class="mt-1 text-sm leading-6 text-text-muted">' . \esc_html($description) . '</p>' : '';

        if ($hasChildren) {
            return $this->renderNestedFlyoutItem($node, $item, $ctx, $depth, $linkHtml, $descriptionHtml);
        }

        $body = $hasCustomContent ? $this->renderCustomContentSlot($node, $ctx, $item) : '';

        $wrapperClass = $depth === 0
            ? 'rounded-lg border border-border-subtle bg-surface-elevated px-4 py-4'
            : 'border-l border-border-subtle pl-4';

        if ($hideLabel && $body !== '') {
            return '<div class="' . \esc_attr($wrapperClass) . '">' . $body . '</div>';
        }

        return '<div class="' . \esc_attr($wrapperClass) . '">' . $linkHtml . $descriptionHtml . $body . '</div>';
    }

    /** @param array<string, mixed> $item */
    private function renderNestedFlyoutItem(Node $node, array $item, RenderContext $ctx, int $depth, string $linkHtml, string $descriptionHtml): string
    {
        $editorMode = $ctx->isEditorMode();
        $hasCustomContent = !empty($item['useCustomContent']);
        $hideLabel = $depth > 0 && !empty($item['hideLabel']);
        $triggerContent = $hideLabel
            ? ($descriptionHtml !== '' ? $descriptionHtml : '<span class="sr-only">' . \esc_html((string) ($item['label'] ?? 'Link')) . '</span>')
            : $linkHtml . $descriptionHtml;
        $childrenHtml = implode('', array_map(fn(array $child): string => $this->renderPanelItem($node, $child, $ctx, $depth + 1), $item['children']));
        $customHtml = $hasCustomContent ? $this->renderCustomContentSlot($node, $ctx, $item) : '';
        $panelContent = $customHtml . ($childrenHtml !== '' ? '<div class="space-y-3">' . $childrenHtml . '</div>' : '');
        $flyoutClasses = $editorMode
            ? 'mt-3 max-h-[24rem] max-w-full space-y-3 overflow-auto rounded-lg border border-border-base bg-surface-base p-4'
            : 'absolute left-full top-0 z-20 ml-3 hidden min-w-[18rem] space-y-3 rounded-lg border border-border-base bg-surface-base p-4 shadow-xl group-hover:block group-focus-within:block';
        $wrapperClass = $depth === 0
            ? 'group relative rounded-lg border border-border-subtle bg-surface-elevated px-4 py-4'
            : 'group relative border-l border-border-subtle pl-4';

        return '<div class="' . \esc_attr($wrapperClass) . '">'
            . '<div class="flex items-start justify-between gap-3">'
            . '<div class="min-w-0 flex-1">' . $triggerContent . '</div>'
            . '<span aria-hidden="true" class="mt-0.5 inline-flex shrink-0 text-text-faint">▸</span>'
            . '</div>'
            . '<div class="' . \esc_attr($flyoutClasses) . '">' . $panelContent . '</div>'
            . '</div>';
    }

    /** @param array<string, mixed> $item */
    private function renderCustomContentSlot(Node $node, RenderContext $ctx, array $item): string
    {
        $slotName = $this->customSlotName((string) ($item['id'] ?? ''));
        $slotHtml = $ctx->renderSlot($node, $slotName)->toString();

        if ($slotHtml === '' && !$ctx->isEditorMode()) {
            return '';
        }

        $attrs = HtmlString::attrs($ctx->slotAttrs($node, $slotName, [
            'class' => 'bky-mega-menu-custom-slot mt-3 max-h-[20rem] overflow-auto rounded-lg border border-dashed border-border-base bg-surface-base px-4 py-4',
        ]));
        $placeholder = $slotHtml === ''
            ? '<p class="text-sm leading-6 text-text-muted">Drop custom blocks here for ' . \esc_html((string) ($item['label'] ?? 'this item')) . '.</p>'
            : '';

        return '<div' . $attrs . '>' . $slotHtml . $placeholder . '</div>';
    }

    /** @param array<string, mixed> $item */
    private function renderLink(array $item, string $className, bool $hideLabel = false): string
    {
        $attrs = [
            'href' => \esc_url((string) ($item['href'] ?? '#')),
            'class' => $className,
            'aria-label' => $hideLabel ? (string) ($item['label'] ?? 'Link') : null,
        ];

        if (!empty($item['openInNewTab'])) {
            $attrs['target'] = '_blank';
            $attrs['rel'] = 'noreferrer noopener';
        }

        $label = \esc_html((string) ($item['label'] ?? 'Link'));
        return '<a' . HtmlString::attrs($attrs) . '>' . ($hideLabel ? '<span class="sr-only">' . $label . '</span>' : $label) . '</a>';
    }

    /** @param array<string, mixed> $item */
    private function itemHasPanel(array $item): bool
    {
        return !empty($item['children']) || !empty($item['useCustomContent']);
    }

    private function customSlotName(string $itemId): string
    {
        return 'mega-menu-panel-' . $this->normalizeItemId($itemId);
    }

    private function normalizeItemId(string $itemId): string
    {
        $normalized = strtolower(trim($itemId));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';
        return trim($normalized, '-');
    }
}