<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ListRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $ordered = (bool) ($node->props['ordered'] ?? false);
        $itemsRaw = (string) ($node->props['items'] ?? '');
        $items = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $itemsRaw) ?: [],
            static fn(string $item): bool => trim($item) !== '',
        ));

        $children = implode('', array_map(
            static fn(string $item): string => '<li>' . \esc_html(trim($item)) . '</li>',
            $items,
        ));

        $tag = $ordered ? 'ol' : 'ul';
        $markerClass = $ordered ? 'list-decimal' : 'list-disc';

        return HtmlString::element(
            $tag,
            $ctx->blockAttrs($node, ['class' => "{$markerClass} space-y-2 pl-6 text-text-base"]),
            $children,
        );
    }
}