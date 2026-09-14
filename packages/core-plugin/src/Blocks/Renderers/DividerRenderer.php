<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class DividerRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $style = (string) ($node->props['style'] ?? 'solid');
        $ornament = trim((string) ($node->props['ornament'] ?? '◆'));
        $varClasses = $ctx->resolveVariantClasses($node, [
            'tone' => [
                'subtle' => 'border-border-subtle',
                'base'   => 'border-border-base',
                'strong' => 'border-border-strong',
                'accent' => 'border-accent-base',
            ],
            'width' => [
                'sm'   => 'max-w-xs',
                'base' => 'max-w-2xl',
                'full' => 'w-full',
            ],
        ]);

        $toneClasses = match ((string) ($node->props['tone'] ?? 'subtle')) {
            'base' => 'border-border-base text-text-base bg-border-base',
            'strong' => 'border-border-strong text-text-base bg-border-strong',
            'accent' => 'border-accent-base text-accent-text bg-accent-base',
            default => 'border-border-subtle text-text-muted bg-border-subtle',
        };

        if ($style === 'gradient') {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim('my-6 overflow-hidden ' . $ctx->resolveVariantClasses($node, ['width' => ['sm' => 'max-w-xs', 'base' => 'max-w-2xl', 'full' => 'w-full']]))]),
                '<div class="h-px w-full bg-gradient-to-r from-transparent via-current to-transparent ' . esc_attr(str_replace('border-', 'text-', (string) ($node->props['tone'] ?? 'subtle') === 'accent' ? 'text-accent-text' : ((string) ($node->props['tone'] ?? 'subtle') === 'strong' ? 'text-text-base' : 'text-text-muted'))) . '"></div>',
            );
        }

        if ($style === 'ornament') {
            $ornamentLabel = $ornament !== '' ? $ornament : '◆';
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim('my-6 flex items-center gap-4 ' . $ctx->resolveVariantClasses($node, ['width' => ['sm' => 'max-w-xs', 'base' => 'max-w-2xl', 'full' => 'w-full']]))]),
                '<span class="h-px flex-1 ' . esc_attr($toneClasses) . '"></span>'
                . '<span class="shrink-0 text-xs uppercase tracking-[0.4em] ' . esc_attr(str_contains($toneClasses, 'text-accent-text') ? 'text-accent-text' : (str_contains($toneClasses, 'text-text-base') ? 'text-text-base' : 'text-text-muted')) . '">' . esc_html($ornamentLabel) . '</span>'
                . '<span class="h-px flex-1 ' . esc_attr($toneClasses) . '"></span>',
            );
        }

        $styleClasses = match ($style) {
            'dashed' => 'border-dashed',
            'dotted' => 'border-dotted',
            default => 'border-solid',
        };

        return HtmlString::void(
            'hr',
            $ctx->blockAttrs($node, ['class' => trim("my-6 border-0 border-t {$styleClasses} {$varClasses}")]),
        );
    }
}