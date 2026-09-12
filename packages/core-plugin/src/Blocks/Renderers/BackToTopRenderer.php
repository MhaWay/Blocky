<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class BackToTopRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $label = trim((string) ($node->props['label'] ?? 'Back to top'));
        $showLabel = (bool) ($node->props['showLabel'] ?? false);
        $position = ($node->props['position'] ?? 'right') === 'left' ? 'left' : 'right';
        $showAfter = max(0, min(100, (int) ($node->props['showAfter'] ?? 20)));
        $size = (string) ($node->props['size'] ?? 'base');
        $tone = (string) ($node->props['tone'] ?? 'accent');

        $sizeClasses = match ($size) {
            'sm' => 'h-10 px-3 text-sm',
            'lg' => 'h-14 px-5 text-base',
            default => 'h-12 px-4 text-sm',
        };

        $toneClasses = match ($tone) {
            'surface' => 'border border-border-base bg-surface-base text-text-base hover:bg-surface-overlay',
            'contrast' => 'bg-text-base text-text-inverse hover:bg-text-base/90',
            default => 'bg-accent-base text-text-on-accent hover:bg-accent-hover',
        };

        $visibleClasses = $ctx->isEditorMode() ? 'translate-y-0 opacity-100 pointer-events-auto' : 'translate-y-4 opacity-0 pointer-events-none';
        $rootClasses = trim('fixed bottom-6 z-40 transition-all duration-300 ' . ($position === 'left' ? 'left-6' : 'right-6') . ' ' . $visibleClasses);

        $buttonInner = '<span aria-hidden="true" class="text-base leading-none">↑</span>';
        if ($showLabel) {
            $buttonInner .= '<span>' . esc_html($label !== '' ? $label : 'Back to top') . '</span>';
        }

        return HtmlString::element(
            'button',
            $ctx->blockAttrs($node, [
                'type' => 'button',
                'aria-label' => $label !== '' ? $label : 'Back to top',
                'data-bky-back-to-top' => '1',
                'data-bky-show-after' => (string) $showAfter,
                'data-bky-editor-mode' => $ctx->isEditorMode() ? 'true' : 'false',
                'class' => trim('inline-flex items-center gap-2 rounded-full shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-base ' . $rootClasses . ' ' . $sizeClasses . ' ' . $toneClasses),
            ]),
            $buttonInner,
        );
    }
}