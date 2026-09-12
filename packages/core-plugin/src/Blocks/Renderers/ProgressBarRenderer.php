<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ProgressBarRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $label = trim((string) ($node->props['label'] ?? 'Progress'));
        $value = max(0, min(100, (int) ($node->props['value'] ?? 65)));
        $showLabel = (bool) ($node->props['showLabel'] ?? true);

        $heightClass = $ctx->resolveVariantClasses($node, [
            'size' => [
                'sm'   => 'h-2',
                'base' => 'h-3',
                'lg'   => 'h-4',
            ],
            'tone' => [
                'accent'  => 'bg-accent-base',
                'success' => 'bg-feedback-success',
                'warning' => 'bg-feedback-warning',
                'danger'  => 'bg-feedback-danger',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-full',
                'lg'   => 'rounded-full',
            ],
        ]);

        $striped = (bool) ($node->props['striped'] ?? false)
            ? 'bg-[linear-gradient(45deg,rgba(255,255,255,0.22)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.22)_50%,rgba(255,255,255,0.22)_75%,transparent_75%,transparent)] bg-[length:1rem_1rem]'
            : '';

        $header = $showLabel
            ? '<div class="mb-2 flex items-center justify-between gap-3 text-sm"><span class="font-medium">' . \esc_html($label) . '</span><span class="text-text-muted">' . $value . '%</span></div>'
            : '';

        $bar = '<div class="overflow-hidden rounded-full bg-surface-elevated">'
            . '<div class="' . \esc_attr(trim('transition-[width] duration-300 ' . $heightClass . ' ' . $striped)) . '" style="width:' . $value . '%"></div>'
            . '</div>';

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'w-full']), $header . $bar);
    }
}