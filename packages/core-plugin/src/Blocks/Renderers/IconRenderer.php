<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class IconRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $icon = trim((string) ($node->props['icon'] ?? '★'));
        $href = trim((string) ($node->props['href'] ?? ''));
        $target = (string) ($node->props['target'] ?? '_self');
        $rel = $target === '_blank' ? 'noopener noreferrer' : null;

        $classes = $ctx->resolveVariantClasses($node, [
            'size' => [
                'sm'   => 'text-base p-2',
                'base' => 'text-2xl p-3',
                'lg'   => 'text-3xl p-4',
                'xl'   => 'text-4xl p-5',
            ],
            'tone' => [
                'base'   => 'text-text-base',
                'muted'  => 'text-text-muted',
                'accent' => 'text-accent-base',
                'inverse' => 'text-text-inverse',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
                'dark'        => 'bg-text-base',
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
                'full' => 'rounded-full',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
            ],
            'align' => [
                'start'  => 'justify-start',
                'center' => 'justify-center',
                'end'    => 'justify-end',
            ],
        ]);

        $iconHtml = HtmlString::element(
            'span',
            ['class' => trim("inline-flex items-center justify-center leading-none {$classes}"), 'aria-hidden' => 'true'],
            \esc_html($icon === '' ? '★' : $icon),
        )->toString();

        $content = $href !== ''
            ? HtmlString::element('a', $ctx->containerAttrs($node, [
                'href' => \esc_url($href),
                'target' => $target,
                'rel' => $rel,
                'class' => 'inline-flex',
            ]), $iconHtml)->toString()
            : HtmlString::element('div', $ctx->containerAttrs($node, ['class' => 'inline-flex']), $iconHtml)->toString();

        return HtmlString::element('div', ['class' => trim('flex ' . $ctx->resolveVariantClasses($node, [
            'align' => [
                'start'  => 'justify-start',
                'center' => 'justify-center',
                'end'    => 'justify-end',
            ],
        ]))], $content);
    }
}