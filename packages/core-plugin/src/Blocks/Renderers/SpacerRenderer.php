<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class SpacerRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $sizeMap = [
            'xs'   => 'h-4',
            'sm'   => 'h-8',
            'base' => 'h-12',
            'lg'   => 'h-20',
            'xl'   => 'h-32',
        ];

        $fallback = (string) ($node->props['size'] ?? 'base');
        $mobile = (string) ($node->props['mobileSize'] ?? $fallback);
        $tablet = (string) ($node->props['tabletSize'] ?? $mobile);
        $desktop = (string) ($node->props['desktopSize'] ?? $tablet);

        $classes = trim('w-full '
            . ($sizeMap[$mobile] ?? $sizeMap['base']) . ' '
            . 'md:' . ($sizeMap[$tablet] ?? $sizeMap['base']) . ' '
            . 'lg:' . ($sizeMap[$desktop] ?? $sizeMap['base']));

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => $classes]),
            '',
        );
    }
}