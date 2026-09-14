<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class IconListRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $icon = trim((string) ($node->props['icon'] ?? '✓'));
        $items = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', (string) ($node->props['items'] ?? '')) ?: [],
            static fn(string $item): bool => trim($item) !== '',
        ));

        $listClasses = $ctx->resolveVariantClasses($node, [
            'gap' => [
                'sm'   => 'space-y-2',
                'base' => 'space-y-3',
                'lg'   => 'space-y-4',
            ],
            'tone' => [
                'base'   => 'text-text-base',
                'muted'  => 'text-text-muted',
                'accent' => 'text-accent-text',
            ],
        ]);

        $children = implode('', array_map(
            static fn(string $item): string => '<li class="flex items-start gap-3"><span class="mt-0.5 inline-flex shrink-0 text-accent-base">' . \esc_html($icon === '' ? '✓' : $icon) . '</span><span>' . \esc_html(trim($item)) . '</span></li>',
            $items,
        ));

        return HtmlString::element('ul', $ctx->blockAttrs($node, ['class' => trim('list-none pl-0 ' . $listClasses)]), $children);
    }
}