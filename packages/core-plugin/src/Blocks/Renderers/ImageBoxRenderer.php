<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ImageBoxRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $attachmentId = (int) ($node->props['attachmentId'] ?? 0);
        $title = trim((string) ($node->props['title'] ?? 'Image box'));
        $text = trim((string) ($node->props['text'] ?? ''));
        $href = trim((string) ($node->props['href'] ?? ''));
        $target = (string) ($node->props['target'] ?? '_self');
        $rel = $target === '_blank' ? 'noopener noreferrer' : null;

        $wrapperClasses = $ctx->resolveVariantClasses($node, [
            'layout' => [
                'vertical'   => 'flex-col',
                'horizontal' => 'flex-row items-start',
            ],
            'align' => [
                'start'  => 'items-start text-start',
                'center' => 'items-center text-center',
                'end'    => 'items-end text-end',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
            ],
            'textColor' => [
                'inherit' => '',
                'base'    => 'text-text-base',
                'muted'   => 'text-text-muted',
                'inverse' => 'text-text-inverse',
                'accent'  => 'text-accent-text',
            ],
            'border' => [
                'none'   => '',
                'subtle' => 'border border-border-subtle',
                'base'   => 'border border-border-base',
                'strong' => 'border border-border-strong',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'sm'   => 'rounded-sm',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
            ],
            'gap' => [
                'sm'   => 'gap-3 p-4',
                'base' => 'gap-4 p-5',
                'lg'   => 'gap-6 p-6',
            ],
        ]);

        $imageClasses = $ctx->resolveVariantClasses($node, [
            'rounded' => [
                'none' => '',
                'base' => 'rounded-image',
                'lg'   => 'rounded-2xl',
                'full' => 'rounded-full',
            ],
            'aspectRatio' => [
                'auto'   => '',
                'square' => 'aspect-square object-cover',
                'video'  => 'aspect-video object-cover',
                'wide'   => 'aspect-[21/9] object-cover',
            ],
            'imageWidth' => [
                'sm'   => 'w-24',
                'base' => 'w-36',
                'lg'   => 'w-48',
                'full' => 'w-full',
            ],
        ]);

        if ($attachmentId > 0) {
            $imageHtml = (string) \wp_get_attachment_image($attachmentId, 'large', false, ['class' => trim('h-auto max-w-full ' . $imageClasses)]);
        } else {
            $imageHtml = '<div class="flex min-h-32 w-full items-center justify-center rounded-image border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted">Select image</div>';
        }

        $content =
            '<div class="shrink-0 overflow-hidden">' . $imageHtml . '</div>' .
            '<div class="flex min-w-0 flex-1 flex-col gap-2">' .
                ($title !== '' ? '<h3 class="text-lg font-semibold">' . \esc_html($title) . '</h3>' : '') .
                ($text !== '' ? '<p class="text-sm leading-6 text-inherit/80">' . \esc_html($text) . '</p>' : '') .
            '</div>';

        $tag = $href !== '' ? 'a' : 'div';
        $attrs = ['class' => trim('flex ' . $wrapperClasses)];
        if ($href !== '') {
            $attrs['href'] = \esc_url($href);
            $attrs['target'] = $target;
            $attrs['rel'] = $rel;
        }

        return HtmlString::element($tag, $ctx->containerAttrs($node, $attrs), $content);
    }
}