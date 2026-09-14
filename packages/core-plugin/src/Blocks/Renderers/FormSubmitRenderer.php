<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class FormSubmitRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $label = trim((string) ($node->props['label'] ?? 'Submit'));
        $fullWidth = (bool) ($node->props['fullWidth'] ?? false);
        $alignClass = $ctx->resolveVariantClasses($node, [
            'align' => [
                'start' => 'justify-start',
                'center' => 'justify-center',
                'end' => 'justify-end',
                'stretch' => 'justify-start',
            ],
        ]);

        $buttonClasses = $ctx->resolveVariantClasses($node, [
            'variant' => [
                'primary' => 'bg-accent-base text-text-on-accent hover:bg-accent-hover',
                'secondary' => 'bg-surface-elevated text-text-base border border-border-base hover:bg-surface-overlay',
                'ghost' => 'bg-transparent text-accent-text hover:bg-accent-subtle',
                'outline' => 'bg-transparent text-accent-base border border-accent-base hover:bg-accent-subtle',
            ],
            'size' => [
                'sm' => 'px-3 py-1.5 text-sm rounded-button',
                'base' => 'px-4 py-2 text-base rounded-button',
                'lg' => 'px-6 py-3 text-lg rounded-button',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-base',
                'lg' => 'rounded-lg',
                'full' => 'rounded-full',
                'button' => 'rounded-button',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm' => 'shadow-sm',
                'base' => 'shadow',
                'md' => 'shadow-md',
                'lg' => 'shadow-lg',
            ],
        ]);

        $button = HtmlString::element('button', $ctx->blockAttrs($node, [
            'type' => 'submit',
            'class' => trim(($fullWidth ? 'w-full ' : '') . 'inline-flex items-center justify-center font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-base ' . $buttonClasses),
        ]), \esc_html($label))->toString();

        return HtmlString::element('div', [
            'class' => trim('flex w-full ' . $alignClass),
        ], $button);
    }
}