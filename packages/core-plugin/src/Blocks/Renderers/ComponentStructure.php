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
}
