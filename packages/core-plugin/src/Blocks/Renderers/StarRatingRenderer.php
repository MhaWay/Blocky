<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class StarRatingRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $value = max(0.0, min(5.0, (float) ($node->props['value'] ?? 4.5)));
        $max = max(1, min(10, (int) ($node->props['max'] ?? 5)));
        $label = trim((string) ($node->props['label'] ?? ''));

        $toneClass = $ctx->resolveVariantClasses($node, [
            'tone' => [
                'gold'   => 'text-amber-400',
                'accent' => 'text-accent-base',
                'muted'  => 'text-text-muted',
            ],
            'size' => [
                'sm'   => 'text-lg',
                'base' => 'text-2xl',
                'lg'   => 'text-3xl',
            ],
            'align' => [
                'start'  => 'justify-start',
                'center' => 'justify-center',
                'end'    => 'justify-end',
            ],
        ]);

        $stars = '';
        for ($index = 1; $index <= $max; $index += 1) {
            $filled = $value >= $index ? 'opacity-100' : ($value >= $index - 0.5 ? 'opacity-60' : 'opacity-20');
            $stars .= '<span class="' . $filled . '">★</span>';
        }

        $content = '<div class="flex gap-1 ' . \esc_attr($toneClass) . '" aria-hidden="true">' . $stars . '</div>';
        if ($label !== '') {
            $content .= '<p class="mt-2 text-sm text-text-muted">' . \esc_html($label) . '</p>';
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'flex flex-col', 'aria-label' => sprintf('Rated %.1f out of %d', $value, $max)]), $content);
    }
}