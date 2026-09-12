<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ContainerRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $varClasses = $ctx->resolveVariantClasses($node, [
            'maxWidth' => [
                'sm'   => 'max-w-2xl',
                'base' => 'max-w-4xl',
                'lg'   => 'max-w-6xl',
                'xl'   => 'max-w-7xl',
                'full' => 'max-w-none',
            ],
            'align' => [
                'start'  => 'mr-auto',
                'center' => 'mx-auto',
                'end'    => 'ml-auto',
            ],
            'padding' => [
                'none' => 'p-0',
                'sm'   => 'p-4',
                'base' => 'p-6',
                'lg'   => 'p-8',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
            ],
        ]);

        $inner = '<div' . HtmlString::attrs($ctx->slotAttrs($node, 'default', ['class' => 'contents'])) . '>'
            . $ctx->renderSlot($node)->toString()
            . '</div>';

        return HtmlString::element(
            'div',
            $ctx->containerAttrs($node, ['class' => trim("w-full {$varClasses}")]),
            $inner,
        );
    }
}
