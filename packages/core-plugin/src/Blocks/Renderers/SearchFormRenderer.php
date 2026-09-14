<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class SearchFormRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $placeholder = (string) ($node->props['placeholder'] ?? 'Search…');
        $buttonLabel = (string) ($node->props['buttonLabel'] ?? 'Search');

        $layoutClasses = $ctx->resolveVariantClasses($node, [
            'layout' => ['inline' => 'flex-row', 'stacked' => 'flex-col'],
            'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
        ]);

        $buttonClasses = $ctx->resolveVariantClasses($node, [
            'buttonTone' => [
                'primary' => 'bg-accent-base text-text-on-accent',
                'neutral' => 'bg-surface-elevated text-text-base border border-border-base',
                'ghost' => 'bg-transparent text-text-base border border-border-base',
            ],
            'radius' => ['none' => 'rounded-none', 'button' => 'rounded-button', 'full' => 'rounded-full'],
        ]);

        $input = '<input type="search" name="s" value="" placeholder="' . \esc_attr($placeholder) . '" class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-4 py-3 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none" />';
        $button = '<button type="submit" class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium transition-colors ' . \esc_attr($buttonClasses) . '">' . \esc_html($buttonLabel) . '</button>';

        return HtmlString::element('form', $ctx->containerAttrs($node, [
            'role' => 'search',
            'method' => 'get',
            'action' => \esc_url(\home_url('/')),
            'class' => trim('flex ' . $layoutClasses),
        ]), $input . $button);
    }
}