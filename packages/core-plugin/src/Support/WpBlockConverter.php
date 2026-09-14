<?php
/**
 * Converts WordPress block content into a Blocky document (non-destructive).
 *
 * post_content is never modified: conversion only creates the _blocky_document
 * meta, so the page can always go back to the block editor.
 *
 * @package Blocky\Core
 */

declare(strict_types=1);

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

final class WpBlockConverter
{
    /**
     * Convert a post's content, persist the document and warm caches.
     *
     * @return array{url: string, converted: bool}
     */
    public static function convertPost(int $postId): array|\WP_Error
    {
        $post = \get_post($postId);
        if (!$post instanceof \WP_Post) {
            return new \WP_Error('blocky_invalid_post', \__('Invalid post.', 'blocky'), ['status' => 404]);
        }

        $blocks = \parse_blocks((string) $post->post_content);
        $doc    = self::convertBlocks($blocks, static fn(array $block): string => (string) \render_block($block));

        $registry = new \Blocky\Core\Blocks\Registry();
        $registry->registerCoreBlocks();
        $errors = (new \Blocky\Core\Support\PropsValidator($registry))->validate($doc);
        if ($errors !== []) {
            return new \WP_Error('blocky_invalid_document', \implode("\n", $errors), ['status' => 500]);
        }

        $json = \wp_json_encode($doc);
        if (!\is_string($json)) {
            return new \WP_Error('blocky_encode_failed', \__('Could not encode document.', 'blocky'), ['status' => 500]);
        }

        \update_post_meta($postId, '_blocky_document', \wp_slash($json));
        \update_post_meta($postId, '_blocky_editor', 'blocky');

        $compiler = new \Blocky\Core\Compiler\PageCompiler(
            new \Blocky\Core\Blocks\Renderer\Pipeline($registry, \Blocky\Core\Tokens\ThemeEngine::getInstance())
        );
        $compiler->warmFrontendCache($postId, $json);

        return [
            'url'       => \admin_url('admin.php?page=blocky-builder&post_id=' . $postId),
            'converted' => true,
        ];
    }

    /**
     * Pure mapping from parsed block arrays to a Blocky document.
     *
     * @param  array<array-key, mixed> $blocks                parse_blocks() output.
     * @param  callable(array<string, mixed>): string        $fallback Renders unmappable blocks as HTML.
     * @return array<string, mixed>
     */
    public static function convertBlocks(array $blocks, callable $fallback): array
    {
        $index = 0;
        $nodes = [];
        $slots = self::mapChildren($blocks, $index, $nodes, $fallback);

        return [
            'root'  => 'root',
            'nodes' => $nodes + [
                'root' => [
                    'id'      => 'root',
                    'type'    => 'bky/section',
                    'props'   => new \stdClass(),
                    'slots'   => ['default' => $slots],
                    'variants' => new \stdClass(),
                ],
            ],
        ];
    }

    /**
     * @param  array<array-key, mixed>             $blocks
     * @param  array<string, array<string, mixed>> $nodes
     * @return array<int, string>
     */
    private static function mapChildren(array $blocks, int &$index, array &$nodes, callable $fallback): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $id = self::mapBlock($block, $index, $nodes, $fallback);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param  array<string, mixed> $block
     * @param  array<string, array<string, mixed>> $nodes
     */
    private static function mapBlock(array $block, int &$index, array &$nodes, callable $fallback): ?string
    {
        $name = isset($block['blockName']) && is_string($block['blockName']) ? $block['blockName'] : '';
        $inner = is_string($block['innerHTML'] ?? null) ? $block['innerHTML'] : '';
        $innerBlocks = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        $put = static function (string $type, array $props, array $slots) use (&$index, &$nodes): string {
            $id = 'c' . (++$index);
            $nodes[$id] = [
                'id'      => $id,
                'type'    => $type,
                'props'   => $props,
                'slots'   => $slots,
                'variants' => new \stdClass(),
            ];
            return $id;
        };

        switch ($name) {
            case 'core/heading':
                $text = self::stripText($inner);
                if ($text === '') {
                    return null;
                }
                $level = isset($attrs['level']) ? (int) $attrs['level'] : 2;
                return $put('bky/heading', ['text' => $text, 'level' => max(1, min(6, $level))], []);

            case 'core/paragraph':
                $text = self::stripText($inner);
                if ($text === '') {
                    return null;
                }
                return $put('bky/text', ['content' => $text], []);

            case 'core/image':
                $attachmentId = isset($attrs['id']) ? (int) $attrs['id'] : 0;
                if ($attachmentId <= 0) {
                    return $put('bky/html', ['html' => $fallback($block)], []);
                }
                $props = [
                    'attachmentId' => $attachmentId,
                    'alt'          => isset($attrs['alt']) && is_string($attrs['alt']) ? $attrs['alt'] : '',
                ];
                if (isset($attrs['size']) && is_string($attrs['size']) && $attrs['size'] !== '') {
                    $props['size'] = $attrs['size'] === 'full' ? 'large' : $attrs['size'];
                }
                return $put('bky/image', $props, []);

            case 'core/button':
                $label = self::stripText($inner);
                $href  = isset($attrs['url']) && is_string($attrs['url']) && $attrs['url'] !== '' ? $attrs['url'] : '#';
                $props = ['label' => $label !== '' ? $label : 'Button', 'href' => $href];
                if (isset($attrs['target']) && $attrs['target'] === '_blank') {
                    $props['target'] = '_blank';
                }
                return $put('bky/button', $props, []);

            case 'core/buttons':
                $ids = self::mapChildren($innerBlocks, $index, $nodes, $fallback);
                return $ids === [] ? null : $put('bky/section', [], ['default' => $ids]);

            case 'core/columns':
                $columns = array_values(array_filter(
                    $innerBlocks,
                    static fn($child): bool => is_array($child) && ($child['blockName'] ?? '') === 'core/column'
                ));
                if (count($columns) < 2) {
                    $ids = self::mapChildren($innerBlocks, $index, $nodes, $fallback);
                    return $ids === [] ? null : $put('bky/section', [], ['default' => $ids]);
                }
                $slots = [];
                foreach ($columns as $slotIndex => $column) {
                    $columnBlocks = is_array($column['innerBlocks'] ?? null) ? $column['innerBlocks'] : [];
                    $slot = 'column-' . ($slotIndex + 1);
                    $slots[$slot] = self::mapChildren($columnBlocks, $index, $nodes, $fallback);
                }
                return $put('bky/columns', ['count' => max(2, min(6, count($columns)))], $slots);

            case 'core/group':
                $ids = self::mapChildren($innerBlocks, $index, $nodes, $fallback);
                return $ids === [] ? null : $put('bky/section', [], ['default' => $ids]);

            case 'core/list':
            case 'core/list-item':
                if ($name === 'core/list-item') {
                    $items = array_map([self::class, 'stripText'], array_map(
                        static fn($child): string => is_string($child['innerHTML'] ?? null) ? $child['innerHTML'] : '',
                        array_values(array_filter($innerBlocks, static fn($c): bool => is_array($c)))
                    ));
                    $items = array_values(array_filter($items, static fn(string $t): bool => $t !== ''));
                    if ($items === []) {
                        return null;
                    }
                    return $put('bky/list', ['items' => implode("\n", $items)], []);
                }
                $ordered = str_contains($inner, '<ol') || str_contains($inner, '<ol>');
                $items   = self::listItems($inner);
                if ($items === [] && $innerBlocks !== []) {
                    $items = self::listItems(implode('', array_map(
                        static fn($child): string => is_string($child['innerHTML'] ?? null) ? $child['innerHTML'] : '',
                        array_values(array_filter($innerBlocks, static fn($c): bool => is_array($c)))
                    )));
                }
                if ($items === []) {
                    return null;
                }
                return $put('bky/list', ['items' => implode("\n", $items), 'ordered' => $ordered], []);

            case 'core/quote':
                $quote = self::stripText($inner);
                if (preg_match('#<cite[^>]*>(.*?)</cite>#is', $inner, $m) === 1) {
                    $citation = self::stripText($m[1]);
                    $quote = self::stripText(preg_replace('#<cite[^>]*>.*?</cite>#is', '', $inner) ?? $inner);
                    return $put('bky/quote', ['quote' => $quote, 'citation' => $citation], []);
                }
                return $quote === '' ? null : $put('bky/quote', ['quote' => $quote], []);

            case 'core/separator':
                return $put('bky/divider', [], []);

            case 'core/spacer':
                $px = isset($attrs['height']) && is_numeric($attrs['height']) ? (int) $attrs['height'] : 0;
                $size = $px <= 0 ? 'base' : ($px <= 16 ? 'xs' : ($px <= 32 ? 'sm' : ($px <= 48 ? 'base' : ($px <= 80 ? 'lg' : 'xl'))));
                return $put('bky/spacer', ['size' => $size], []);

            default:
                break;
        }

        // Containers we do not model (media-text, cover, unknown blocks):
        // map their children when present, otherwise render as sanitized HTML.
        if ($innerBlocks !== [] && self::stripText($inner) === '') {
            $ids = self::mapChildren($innerBlocks, $index, $nodes, $fallback);
            return $ids === [] ? null : $put('bky/section', [], ['default' => $ids]);
        }
        if (trim($inner) === '') {
            return null;
        }
        return $put('bky/html', ['html' => $fallback($block)], []);
    }

    /**
     * @return array<int, string>
     */
    private static function listItems(string $html): array
    {
        if (preg_match_all('#<li[^>]*>(.*?)</li>#is', $html, $m) === false) {
            return [];
        }
        $items = array_map([self::class, 'stripText'], $m[1]);
        return array_values(array_filter($items, static fn(string $t): bool => $t !== ''));
    }

    private static function stripText(string $html): string
    {
        $text = preg_replace('#<[^>]+>#', '', $html) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($text);
    }
}
