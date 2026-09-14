<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ScrollProgressRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $position = ($node->props['position'] ?? 'top') === 'bottom' ? 'bottom' : 'top';
        $height = (string) ($node->props['height'] ?? 'sm');
        $tone = (string) ($node->props['tone'] ?? 'accent');
        $showTrack = (bool) ($node->props['showTrack'] ?? true);
        $isEditor = $ctx->isEditorMode();

        $heightClass = match ($height) {
            'lg' => 'h-2.5',
            'base' => 'h-1.5',
            default => 'h-1',
        };

        $fillClass = match ($tone) {
            'success' => 'bg-feedback-success',
            'contrast' => 'bg-text-base',
            default => 'bg-accent-base',
        };

        $rootClasses = trim('pointer-events-none overflow-hidden ' . $heightClass . ' ' . ($isEditor ? 'relative my-3 w-full rounded-full' : 'fixed inset-x-0 z-40 ' . ($position === 'bottom' ? 'bottom-0' : 'top-0')) . ' ' . ($showTrack ? 'bg-surface-overlay/70' : 'bg-transparent'));
        $progressWidth = $isEditor ? '55%' : '0%';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['data-bky-scroll-progress' => '1', 'class' => $rootClasses]),
            '<div data-bky-scroll-progress-fill class="h-full rounded-full transition-[width] duration-150 ' . esc_attr($fillClass) . '" style="width:' . esc_attr($progressWidth) . '"></div>',
        );
    }
}