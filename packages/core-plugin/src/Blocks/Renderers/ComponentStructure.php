<?php
/**
 * @package Blocky\Core\Blocks\Renderers
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

/**
 * Renders a component (loop-item template) document for the CURRENT post
 * pointer. Loop renderers call this after the_post(), so any dynamic or
 * data-field block inside the component resolves against the loop item.
 */
final class ComponentStructure
{
    private const MAX_DEPTH = 3;

    private static int $depth = 0;

    /**
     * Returns null when no usable structure is configured, so callers fall
     * back to their built-in item markup.
     */
    public static function render(RenderContext $ctx, Node $node): ?HtmlString
    {
        $structureId = (int) ($node->props['structureId'] ?? 0);
        if ($structureId <= 0 || self::$depth >= self::MAX_DEPTH) {
            return null;
        }

        $document = get_post_meta($structureId, '_blocky_document', true);
        if (!is_string($document) || $document === '') {
            return null;
        }

        self::$depth++;
        try {
            $html = $ctx->renderDocument($document);
        } finally {
            self::$depth--;
        }

        return $html->toString() === '' ? null : $html;
    }

    /**
     * Editor-only wrapper: hovering a loop block with a component blurs the
     * preview and reveals an 'Edit element' button that jumps into the
     * component document. Loop blocks without a component get a hint to
     * pick one.
     */
    public static function decorate(RenderContext $ctx, Node $node, string $content): HtmlString
    {
        if (!$ctx->isEditorMode()) {
            return HtmlString::of($content);
        }

        $structureId = (int) ($node->props['structureId'] ?? 0);
        if ($structureId > 0) {
            $label = \esc_html(\__('Edit element', 'blocky'));
            return HtmlString::of(
                '<div class="relative" data-bky-structure-id="' . $structureId . '">'
                . '<div class="bky-structure-content">' . $content . '</div>'
                . '<div class="bky-structure-edit"><button type="button" data-bky-edit-structure="' . $structureId . '">' . $label . '</button></div>'
                . '</div>'
            );
        }

        $hint = \esc_html(\__('Using the default card. Open Structure to assign a component and design your own item layout.', 'blocky'));
        return HtmlString::of('<div class="bky-structure-hint">' . $hint . '</div>' . $content);
    }
}
