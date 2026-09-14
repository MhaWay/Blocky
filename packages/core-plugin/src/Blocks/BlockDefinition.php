<?php
/**
 * @package Blocky\Core\Blocks
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

/**
 * Immutable block definition record.
 */
final class BlockDefinition
{
    /**
     * @param string                                $type         Block type slug (e.g. "bky/heading")
     * @param array<string, mixed>                  $schema       JSON Schema for props validation
     * @param array<string, array<string, string>>  $variants     CSS class maps keyed by variant name
     * @param BlockRendererInterface                $renderer     Server-side renderer
     * @param array<string, mixed>                  $editorConfig Inspector configuration for the builder
     * @param bool                                  $interactive  Whether the block needs JS island hydration
     * @param string[]                              $cssHandles   Asset handles this block requires
     * @param string[]                              $jsHandles    JS island handles
     * @param string[]                              $keywords     Search keywords for the builder library
     */
    public function __construct(
        public readonly string                 $type,
        public readonly array                  $schema,
        public readonly array                  $variants,
        public readonly BlockRendererInterface $renderer,
        public readonly array                  $editorConfig = [],
        public readonly bool                   $interactive = false,
        public readonly array                  $cssHandles = [],
        public readonly array                  $jsHandles = [],
        public readonly string                 $label = '',
        public readonly string                 $category = 'basic',
        public readonly string                 $description = '',
        public readonly array                  $keywords = [],
        public readonly string                 $icon = '',
    ) {}

    /**
     * Render this block with the given node and context.
     */
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        return $this->renderer->render($node, $ctx);
    }
}
