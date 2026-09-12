<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class CallToActionRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $attachmentId = (int) ($node->props['attachmentId'] ?? 0);
        $eyebrow = trim((string) ($node->props['eyebrow'] ?? ''));
        $title = trim((string) ($node->props['title'] ?? 'Call to action'));
        $text = trim((string) ($node->props['text'] ?? ''));
        $primaryLabel = trim((string) ($node->props['primaryLabel'] ?? ''));
        $primaryUrl = trim((string) ($node->props['primaryUrl'] ?? ''));
        $secondaryLabel = trim((string) ($node->props['secondaryLabel'] ?? ''));
        $secondaryUrl = trim((string) ($node->props['secondaryUrl'] ?? ''));

        $wrapperClasses = $ctx->resolveVariantClasses($node, [
            'layout' => ['stacked' => 'flex-col', 'split' => 'flex-col lg:flex-row'],
            'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
            'mediaPosition' => ['start' => 'lg:flex-row-reverse', 'end' => ''],
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            'padding' => ['base' => 'p-6', 'lg' => 'p-8'],
        ]);

        $mediaHtml = '';
        if ($attachmentId > 0) {
            $mediaHtml = '<div class="w-full lg:w-5/12">' . (string) \wp_get_attachment_image($attachmentId, 'large', false, ['class' => 'aspect-[4/3] w-full rounded-card object-cover']) . '</div>';
        }

        $buttons = '';
        if ($primaryLabel !== '') {
            $buttons .= '<a href="' . \esc_url($primaryUrl !== '' ? $primaryUrl : '#') . '" class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm font-medium text-text-on-accent">' . \esc_html($primaryLabel) . '</a>';
        }
        if ($secondaryLabel !== '') {
            $buttons .= '<a href="' . \esc_url($secondaryUrl !== '' ? $secondaryUrl : '#') . '" class="inline-flex items-center justify-center rounded-button border border-border-base px-4 py-2 text-sm font-medium text-current">' . \esc_html($secondaryLabel) . '</a>';
        }

        $content = '<div class="flex w-full flex-1 flex-col gap-4 lg:w-7/12">'
            . ($eyebrow !== '' ? '<span class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">' . \esc_html($eyebrow) . '</span>' : '')
            . '<h3 class="text-3xl font-semibold leading-tight">' . \esc_html($title) . '</h3>'
            . ($text !== '' ? '<p class="max-w-2xl text-sm leading-6 opacity-90">' . \esc_html($text) . '</p>' : '')
            . ($buttons !== '' ? '<div class="flex flex-wrap gap-3 pt-2">' . $buttons . '</div>' : '')
            . '</div>';

        return HtmlString::element('section', $ctx->blockAttrs($node, ['class' => trim('flex gap-6 rounded-card ' . $wrapperClasses)]), $mediaHtml . $content);
    }
}