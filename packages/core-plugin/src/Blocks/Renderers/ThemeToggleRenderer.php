<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Blocks\Node;

final class ThemeToggleRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $label   = trim((string) ($node->props['label']   ?? 'Toggle Theme'));
        $size    = (string) ($node->props['size']    ?? 'md');
        $variant = (string) ($node->props['variant'] ?? 'outline');

        $sizeClass = match ($size) {
            'sm'  => 'px-3 py-1.5 text-sm',
            'lg'  => 'px-6 py-3 text-lg',
            default => 'px-4 py-2 text-base',
        };

        $variantClass = match ($variant) {
            'solid'  => 'bg-surface-elevated text-text-base border border-border-base rounded-button hover:bg-surface-base',
            'ghost'  => 'text-text-base hover:bg-surface-elevated rounded-button',
            default  => 'border border-border-base text-text-base rounded-button hover:bg-surface-elevated',
        };

        $labelText  = $label !== '' ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : 'Toggle Theme';
        $buttonClass = "bky-theme-toggle inline-flex items-center gap-2 font-medium transition-colors {$sizeClass} {$variantClass}";

        if ($ctx->isEditorMode()) {
            $inner = '<span class="pointer-events-none select-none">'
                . '<span aria-hidden="true" style="font-size:1.1em">☀️</span>'
                . ' ' . $labelText
                . '</span>';

            return HtmlString::element('span', $ctx->blockAttrs($node, ['class' => $buttonClass, 'style' => 'cursor:default']), $inner);
        }


        $inner = '<span aria-hidden="true" style="font-size:1.1em" class="bky-theme-toggle-icon">☀️</span>'
            . '<span class="bky-theme-toggle-label">' . $labelText . '</span>';

        return HtmlString::element('button', $ctx->blockAttrs($node, [
            'type'    => 'button',
            'class'   => $buttonClass,
            'data-bky-theme-toggle' => 'true',
        ]), $inner);
    }
}
