<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class ButtonRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $label  = trim((string) ($node->props['label']  ?? ''));
        $href   = (string) ($node->props['href']   ?? '#');
        $target = (string) ($node->props['target'] ?? '_self');
        $slotHtml = $ctx->renderSlot($node)->toString();
        $hasSlotContent = $slotHtml !== '';

        $buttonClasses = $ctx->resolveVariantClasses($node, [
            'variant' => [
                'primary'   => 'bg-accent-base text-text-on-accent hover:bg-accent-hover',
                'secondary' => 'bg-surface-elevated text-text-base border border-border-base hover:bg-surface-overlay',
                'ghost'     => 'bg-transparent text-accent-text hover:bg-accent-subtle',
                'outline'   => 'bg-transparent text-accent-base border border-accent-base hover:bg-accent-subtle',
            ],
            'size' => [
                'sm'   => 'px-3 py-1.5 text-sm rounded-button',
                'base' => 'px-4 py-2 text-base rounded-button',
                'lg'   => 'px-6 py-3 text-lg rounded-button',
            ],
            'background' => [
                'transparent' => '',
                'surface'     => 'bg-surface-base',
                'elevated'    => 'bg-surface-elevated',
                'accent'      => 'bg-accent-subtle',
                'dark'        => 'bg-text-base',
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
                'accent' => 'border border-accent-base',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'sm'   => 'rounded-sm',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
                'full' => 'rounded-full',
                'button' => 'rounded-button',
            ],
            'shadow' => [
                'none' => 'shadow-none',
                'sm'   => 'shadow-sm',
                'base' => 'shadow',
                'md'   => 'shadow-md',
                'lg'   => 'shadow-lg',
            ],
            'overflow' => [
                'visible' => 'overflow-visible',
                'hidden'  => 'overflow-hidden',
            ],
        ]);

        $alignClass = $ctx->resolveVariantClasses($node, [
            'align' => [
                'start'   => 'justify-start',
                'center'  => 'justify-center',
                'end'     => 'justify-end',
                'stretch' => 'justify-start',
            ],
        ]);

        $rel = $target === '_blank' ? 'noopener noreferrer' : null;
        $fullWidth = (bool) ($node->props['fullWidth'] ?? false);
        $align = (string) ($node->props['align'] ?? 'start');
        $slotLabel = $label !== '' ? $label : 'Drop button content here';

        $slotAttrs = [
            'class' => trim('bky-button-slot flex min-w-0 items-center justify-center gap-2 ' . ($fullWidth || $align === 'stretch' ? 'w-full' : '')),
        ];
        if ($ctx->isEditorMode() && !$hasSlotContent) {
            $slotAttrs['data-bky-slot-label'] = $slotLabel;
        }

        $buttonContent = $hasSlotContent
            ? $slotHtml
            : ($ctx->isEditorMode() ? '' : \esc_html($label));

        $buttonInner = '<span' . HtmlString::attrs($ctx->slotAttrs($node, 'default', $slotAttrs)) . '>' . $buttonContent . '</span>';

        $button = HtmlString::element(
            'a',
            $ctx->containerAttrs($node, [
                'href'   => \esc_url($href),
                'target' => $target,
                'rel'    => $rel,
                'class'  => trim(($fullWidth || $align === 'stretch' ? 'w-full ' : '') . "inline-flex items-center justify-center font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-base {$buttonClasses}"),
            ]),
            $buttonInner,
        )->toString();

        return HtmlString::element(
            'div',
            [
                'class'  => trim("flex {$alignClass}"),
            ],
            $button,
        );
    }
}
