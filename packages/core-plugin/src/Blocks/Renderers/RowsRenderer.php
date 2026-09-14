<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class RowsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $count = max(1, min(6, (int) ($node->props['count'] ?? 3)));

        $varClasses = $ctx->resolveVariantClasses($node, [
            'gap' => [
                'none' => 'gap-0',
                'sm'   => 'gap-4',
                'base' => 'gap-6',
                'lg'   => 'gap-8',
                'xl'   => 'gap-10',
            ],
            'alignItems' => [
                'start'   => 'items-start',
                'center'  => 'items-center',
                'end'     => 'items-end',
                'stretch' => 'items-stretch',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
                'dark'        => 'bg-text-base',
            ],
            'textColor' => [
                'inherit' => '',
                'base'    => 'text-text-base',
                'muted'   => 'text-text-muted',
                'inverse' => 'text-text-inverse',
                'accent'  => 'text-accent-text',
            ],
            'border' => [
                'none'   => '',
                'subtle' => 'border border-border-subtle',
                'base'   => 'border border-border-base',
                'strong' => 'border border-border-strong',
                'accent' => 'border border-accent-base',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'sm'   => 'rounded-sm',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
                'lg'   => 'shadow-lg',
            ],
            'overflow' => [
                'visible' => 'overflow-visible',
                'hidden'  => 'overflow-hidden',
            ],
        ]);

        $rows = '';
        for ($i = 1; $i <= $count; $i++) {
            $slotName = "row-{$i}";
            $rowInner = $ctx->renderSlot($node, $slotName)->toString();
            $slotAttrs = HtmlString::attrs($ctx->slotAttrs($node, $slotName, ['class' => 'bky-row w-full']));
            $rows .= "<div{$slotAttrs}>{$rowInner}</div>";
        }

        return HtmlString::element(
            'div',
            $ctx->containerAttrs($node, ['class' => trim("flex w-full flex-col {$varClasses}")]),
            $rows,
        );
    }
}