<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class CounterRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $prefix = (string) ($node->props['prefix'] ?? '');
        $suffix = (string) ($node->props['suffix'] ?? '');
        $value = (string) ($node->props['value'] ?? '128');
        $label = trim((string) ($node->props['label'] ?? ''));

        $classes = $ctx->resolveVariantClasses($node, [
            'align' => [
                'start'  => 'items-start text-start',
                'center' => 'items-center text-center',
                'end'    => 'items-end text-end',
            ],
            'size' => [
                'sm'   => 'text-3xl',
                'base' => 'text-5xl',
                'lg'   => 'text-6xl',
            ],
            'tone' => [
                'base'   => 'text-text-base',
                'muted'  => 'text-text-muted',
                'accent' => 'text-accent-text',
            ],
        ]);

        $content = '<div class="font-display font-bold leading-none ' . \esc_attr($classes) . '">' . \esc_html($prefix . $value . $suffix) . '</div>';
        if ($label !== '') {
            $content .= '<p class="mt-3 text-sm font-medium text-text-muted">' . \esc_html($label) . '</p>';
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'flex flex-col']), $content);
    }
}