<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class FlipBoxRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $frontTitle = trim((string) ($node->props['frontTitle'] ?? ''));
        $frontText = trim((string) ($node->props['frontText'] ?? ''));
        $backTitle = trim((string) ($node->props['backTitle'] ?? ''));
        $backText = trim((string) ($node->props['backText'] ?? ''));
        $buttonLabel = trim((string) ($node->props['buttonLabel'] ?? ''));
        $buttonUrl = trim((string) ($node->props['buttonUrl'] ?? ''));

        $panelClasses = $ctx->resolveVariantClasses($node, [
            'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[26rem]'],
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
        ]);
        $verticalAlignClasses = $ctx->resolveVariantClasses($node, [
            'verticalAlign' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
        ]);

        $frontContent = '<div class="text-xs font-semibold uppercase tracking-[0.18em] opacity-65">Front</div>'
            . ($frontTitle !== '' ? '<h3 class="text-2xl font-semibold">' . \esc_html($frontTitle) . '</h3>' : '')
            . ($frontText !== '' ? '<p class="text-sm leading-6 opacity-85">' . \esc_html($frontText) . '</p>' : '');

        $front = '<div class="absolute inset-0 flex flex-col gap-3 rounded-card p-6 [backface-visibility:hidden] ' . \esc_attr($verticalAlignClasses) . '">'
            . $frontContent
            . '</div>';

        $button = $buttonLabel !== ''
            ? '<a href="' . \esc_url($buttonUrl !== '' ? $buttonUrl : '#') . '" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($buttonLabel) . '</a>'
            : '';
        $buttonMeasure = $buttonLabel !== ''
            ? '<span class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($buttonLabel) . '</span>'
            : '';
        $backContent = '<div class="space-y-3">'
            . '<div class="text-xs font-semibold uppercase tracking-[0.18em] opacity-65">Back</div>'
            . ($backTitle !== '' ? '<h3 class="text-2xl font-semibold">' . \esc_html($backTitle) . '</h3>' : '')
            . ($backText !== '' ? '<p class="text-sm leading-6 opacity-85">' . \esc_html($backText) . '</p>' : '')
            . '</div>';
        $back = '<div class="absolute inset-0 flex flex-col gap-4 rounded-card p-6 [backface-visibility:hidden] [transform:rotateY(180deg)] ' . \esc_attr($verticalAlignClasses) . '">'
            . $backContent
            . $button
            . '</div>';
        $measure = '<div aria-hidden="true" class="pointer-events-none invisible grid w-full rounded-card ' . \esc_attr($panelClasses) . '">'
            . '<div class="col-start-1 row-start-1 flex flex-col gap-3 rounded-card p-6 ' . \esc_attr($verticalAlignClasses) . '">'
            . $frontContent
            . '</div>'
            . '<div class="col-start-1 row-start-1 flex flex-col gap-4 rounded-card p-6 ' . \esc_attr($verticalAlignClasses) . '">'
            . $backContent
            . $buttonMeasure
            . '</div>'
            . '</div>';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => 'group relative -m-3 p-3 [perspective:1000px]']),
            '<div class="relative w-full rounded-card transition-transform duration-500 [transform-style:preserve-3d] group-hover:[transform:rotateY(180deg)] group-focus-within:[transform:rotateY(180deg)] ' . \esc_attr($panelClasses) . '">' . $measure . $front . $back . '</div>'
        );
    }
}