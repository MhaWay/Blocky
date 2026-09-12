<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class IconBoxRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Icon box'));
        $text = trim((string) ($node->props['text'] ?? ''));
        $icon = trim((string) ($node->props['icon'] ?? '✨'));
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

        $iconClasses = $ctx->resolveVariantClasses($node, [
            'iconSize' => [
                'sm'   => 'text-xl',
                'base' => 'text-3xl',
                'lg'   => 'text-4xl',
            ],
            'iconTone' => [
                'base'   => 'text-text-base',
                'muted'  => 'text-text-muted',
                'accent' => 'text-accent-base',
                'inverse' => 'text-text-inverse',
            ],
        ]);

        $inner =
            '<span class="inline-flex shrink-0 leading-none ' . \esc_attr($iconClasses) . '">' . \esc_html($icon === '' ? '✨' : $icon) . '</span>' .
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

        return HtmlString::element($tag, $ctx->containerAttrs($node, $attrs), $inner);
    }
}