<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class StickyBarRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Keep this page within reach'));
        $text = trim(wp_strip_all_tags((string) ($node->props['text'] ?? 'Pin an important CTA, promo, or status message while visitors scroll.')));
        $buttonLabel = trim((string) ($node->props['buttonLabel'] ?? 'Get started'));
        $buttonUrl = (string) ($node->props['buttonUrl'] ?? '#');
        $position = ($node->props['position'] ?? 'bottom') === 'top' ? 'top' : 'bottom';
        $showAfter = max(0, min(100, (int) ($node->props['showAfter'] ?? 15)));
        $dismissible = (bool) ($node->props['dismissible'] ?? true);
        $tone = (string) ($node->props['tone'] ?? 'surface');

        [$surfaceClasses, $buttonClasses, $dismissClasses] = match ($tone) {
            'accent' => ['border border-accent-base/30 bg-accent-base text-text-on-accent shadow-xl', 'bg-white/15 text-white hover:bg-white/25', 'text-white/80 hover:text-white'],
            'contrast' => ['border border-text-base/10 bg-text-base text-text-inverse shadow-xl', 'bg-white/10 text-white hover:bg-white/20', 'text-white/80 hover:text-white'],
            default => ['border border-border-base bg-surface-base text-text-base shadow-lg', 'bg-accent-base text-text-on-accent hover:bg-accent-hover', 'text-text-muted hover:text-text-base'],
        };

        $visibleClasses = $ctx->isEditorMode()
            ? 'translate-y-0 opacity-100 pointer-events-auto'
            : ($position === 'top' ? '-translate-y-6 opacity-0 pointer-events-none' : 'translate-y-6 opacity-0 pointer-events-none');

        $rootClasses = trim('fixed left-1/2 z-40 flex w-[min(calc(100%-1.5rem),64rem)] -translate-x-1/2 items-center transition-all duration-300 ' . ($position === 'top' ? 'top-4' : 'bottom-4') . ' ' . $visibleClasses);
        $content = '<div class="flex flex-1 flex-col gap-1 min-w-0">'
            . ($title !== '' ? '<p class="text-sm font-semibold leading-5">' . esc_html($title) . '</p>' : '')
            . ($text !== '' ? '<p class="text-sm leading-6 opacity-90">' . esc_html($text) . '</p>' : '')
            . '</div>';

        if ($buttonLabel !== '') {
            $content .= '<a href="' . esc_url($buttonUrl) . '" class="inline-flex shrink-0 items-center justify-center rounded-button px-4 py-2 text-sm font-medium transition-colors ' . esc_attr($buttonClasses) . '">' . esc_html($buttonLabel) . '</a>';
        }

        if ($dismissible) {
            $content .= '<button type="button" data-bky-sticky-dismiss="1" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-colors ' . esc_attr($dismissClasses) . '" aria-label="' . esc_attr__('Dismiss sticky bar', 'blocky') . '">×</button>';
        }

        return HtmlString::element(
            'aside',
            $ctx->blockAttrs($node, [
                'data-bky-sticky-bar' => '1',
                'data-bky-sticky-position' => $position,
                'data-bky-show-after' => (string) $showAfter,
                'data-bky-editor-mode' => $ctx->isEditorMode() ? 'true' : 'false',
                'class' => $rootClasses,
            ]),
            '<div class="flex w-full flex-wrap items-center gap-3 rounded-card px-4 py-3 md:flex-nowrap ' . esc_attr($surfaceClasses) . '">' . $content . '</div>',
        );
    }
}