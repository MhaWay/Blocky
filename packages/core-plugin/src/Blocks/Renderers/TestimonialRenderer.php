<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class TestimonialRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $attachmentId = (int) ($node->props['attachmentId'] ?? 0);
        $quote = trim((string) ($node->props['quote'] ?? ''));
        $author = trim((string) ($node->props['author'] ?? ''));
        $role = trim((string) ($node->props['role'] ?? ''));

        $classes = $ctx->resolveVariantClasses($node, [
            'layout' => ['card' => 'items-start text-left', 'centered' => 'items-center text-center'],
            'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
        ]);

        $avatar = '';
        if ($attachmentId > 0) {
            $avatar = '<div class="shrink-0">' . (string) \wp_get_attachment_image($attachmentId, 'thumbnail', false, ['class' => 'h-16 w-16 rounded-full object-cover']) . '</div>';
        }

        $byline = '';
        if ($author !== '' || $role !== '') {
            $byline = '<div class="space-y-1">'
                . ($author !== '' ? '<div class="font-semibold">' . \esc_html($author) . '</div>' : '')
                . ($role !== '' ? '<div class="text-sm opacity-75">' . \esc_html($role) . '</div>' : '')
                . '</div>';
        }

        return HtmlString::element(
            'figure',
            $ctx->blockAttrs($node, ['class' => trim('flex flex-col gap-5 rounded-card p-6 ' . $classes)]),
            '<div class="text-3xl leading-none opacity-50">&ldquo;</div>'
            . '<blockquote class="text-lg leading-8">' . \esc_html($quote) . '</blockquote>'
            . (($avatar !== '' || $byline !== '') ? '<figcaption class="flex items-center gap-4">' . $avatar . $byline . '</figcaption>' : '')
        );
    }
}