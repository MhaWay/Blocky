<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class TableOfContentsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'On this page'));
        $minLevel = max(1, min(6, (int) ($node->props['minLevel'] ?? 2)));
        $maxLevel = max($minLevel, min(6, (int) ($node->props['maxLevel'] ?? 4)));
        $ordered = (bool) ($node->props['ordered'] ?? false);
        $entries = $this->headingEntries($ctx->currentPostId() > 0 ? $ctx->currentPostId() : $this->resolveCurrentPostId(), $minLevel, $maxLevel);

        if ($entries === []) {
            $message = $ctx->isEditorMode()
                ? 'Add headings to the page to generate the table of contents.'
                : '';
            return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Table of contents', 'class' => 'text-sm text-text-muted']), $message);
        }

        $tag = $ordered ? 'ol' : 'ul';
        $items = implode('', array_map(static function (array $entry): string {
            return '<li class="leading-6" style="padding-left:' . max(0, ((int) $entry['level'] - 2) * 0.85) . 'rem"><a href="#' . esc_attr((string) $entry['anchor']) . '" class="text-text-muted hover:text-accent-text">' . esc_html((string) $entry['label']) . '</a></li>';
        }, $entries));

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Table of contents', 'class' => 'rounded-card border border-border-subtle bg-surface-base p-5']), ($title !== '' ? '<h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-text-muted">' . esc_html($title) . '</h3>' : '') . '<' . $tag . ' class="mt-4 space-y-2 pl-0">' . $items . '</' . $tag . '>');
    }

    /** @return array<int, array{label:string, anchor:string, level:int}> */
    private function headingEntries(int $postId, int $minLevel, int $maxLevel): array
    {
        if ($postId <= 0) {
            return [];
        }

        $document = get_post_meta($postId, '_blocky_document', true);
        if (!is_string($document) || $document === '') {
            return [];
        }

        $decoded = json_decode($document, true);
        if (!is_array($decoded) || !isset($decoded['root'], $decoded['nodes']) || !is_array($decoded['nodes'])) {
            return [];
        }

        $entries = [];
        $this->collectHeadings((string) $decoded['root'], $decoded['nodes'], $entries, $minLevel, $maxLevel);
        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<int, array{label:string, anchor:string, level:int}> $entries
     */
    private function collectHeadings(string $nodeId, array $nodes, array &$entries, int $minLevel, int $maxLevel): void
    {
        $node = $nodes[$nodeId] ?? null;
        if (!is_array($node)) {
            return;
        }

        $type = (string) ($node['type'] ?? '');
        $props = isset($node['props']) && is_array($node['props']) ? $node['props'] : [];
        if ($type === 'bky/heading') {
            $level = max(1, min(6, (int) ($props['level'] ?? 2)));
            $text = trim(wp_strip_all_tags((string) ($props['text'] ?? '')));
            if ($text !== '' && $level >= $minLevel && $level <= $maxLevel) {
                $entries[] = [
                    'label' => $text,
                    'anchor' => $this->resolveHeadingAnchor($nodeId, $props),
                    'level' => $level,
                ];
            }
        }

        $slots = [];
        if (isset($node['slots']) && is_array($node['slots'])) {
            $slots = $node['slots'];
        } elseif (isset($node['children']) && is_array($node['children'])) {
            $slots = ['default' => $node['children']];
        }

        foreach ($slots as $slotChildren) {
            if (!is_array($slotChildren)) {
                continue;
            }
            foreach ($slotChildren as $childId) {
                if (is_string($childId) && $childId !== '') {
                    $this->collectHeadings($childId, $nodes, $entries, $minLevel, $maxLevel);
                }
            }
        }
    }

    /** @param array<string, mixed> $props */
    private function resolveHeadingAnchor(string $nodeId, array $props): string
    {
        $configured = sanitize_title((string) ($props['anchorId'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $text = html_entity_decode(wp_strip_all_tags((string) ($props['text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $slug = sanitize_title($text);
        return $slug !== '' ? $slug . '-' . substr($nodeId, 0, 6) : 'heading-' . substr($nodeId, 0, 6);
    }

    private function resolveCurrentPostId(): int
    {
        $postId = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($postId > 0) {
            return $postId;
        }

        return function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    }
}