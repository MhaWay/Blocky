<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ColumnsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $count = max(2, min(6, (int) ($node->props['count'] ?? 2)));
        $stackAt = (string) ($node->props['stackAt'] ?? 'md');

        $varClasses = $ctx->resolveVariantClasses($node, [
            'gap' => [
                'none' => 'gap-0',
                'sm'   => 'gap-4',
                'base' => 'gap-8',
                'lg'   => 'gap-12',
                'xl'   => 'gap-16',
            ],
            'verticalAlign' => [
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

        $columns = '';
        for ($i = 1; $i <= $count; $i++) {
            $slotName = "column-{$i}";
            $colInner = $ctx->renderSlot($node, $slotName)->toString();
            $slotAttrs = HtmlString::attrs($ctx->slotAttrs($node, $slotName, ['class' => 'bky-column']));
            $columns .= "<div{$slotAttrs}>{$colInner}</div>";
        }

        return HtmlString::element(
            'div',
            $ctx->containerAttrs($node, ['class' => trim('grid ' . self::columnClass($count, $stackAt) . ' ' . $varClasses)]),
            $columns,
        );
    }

    private static function columnClass(int $count, string $stackAt): string
    {
        return match ($stackAt) {
            'never' => match ($count) {
                2 => 'grid-cols-2',
                3 => 'grid-cols-3',
                4 => 'grid-cols-4',
                5 => 'grid-cols-5',
                6 => 'grid-cols-6',
                default => 'grid-cols-2',
            },
            'sm' => match ($count) {
                2 => 'grid-cols-1 sm:grid-cols-2',
                3 => 'grid-cols-1 sm:grid-cols-3',
                4 => 'grid-cols-1 sm:grid-cols-4',
                5 => 'grid-cols-1 sm:grid-cols-5',
                6 => 'grid-cols-1 sm:grid-cols-6',
                default => 'grid-cols-1 sm:grid-cols-2',
            },
            'lg' => match ($count) {
                2 => 'grid-cols-1 lg:grid-cols-2',
                3 => 'grid-cols-1 lg:grid-cols-3',
                4 => 'grid-cols-1 lg:grid-cols-4',
                5 => 'grid-cols-1 lg:grid-cols-5',
                6 => 'grid-cols-1 lg:grid-cols-6',
                default => 'grid-cols-1 lg:grid-cols-2',
            },
            default => match ($count) {
                2 => 'grid-cols-1 md:grid-cols-2',
                3 => 'grid-cols-1 md:grid-cols-3',
                4 => 'grid-cols-1 md:grid-cols-4',
                5 => 'grid-cols-1 md:grid-cols-5',
                6 => 'grid-cols-1 md:grid-cols-6',
                default => 'grid-cols-1 md:grid-cols-2',
            },
        };
    }
}
