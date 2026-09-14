<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

final class AlertRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Heads up'));
        $message = trim((string) ($node->props['message'] ?? ''));
        $icon = trim((string) ($node->props['icon'] ?? 'ℹ'));

        $classes = $ctx->resolveVariantClasses($node, [
            'tone' => [
                'info'    => 'border-sky-300 bg-sky-50 text-sky-900',
                'success' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
                'danger'  => 'border-rose-300 bg-rose-50 text-rose-900',
            ],
            'radius' => [
                'none' => 'rounded-none',
                'base' => 'rounded-base',
                'lg'   => 'rounded-lg',
                'xl'   => 'rounded-xl',
            ],
        ]);

        return HtmlString::element(
            'div',
            $ctx->containerAttrs($node, ['class' => trim('flex items-start gap-3 border p-4 ' . $classes), 'role' => 'alert']),
            '<span class="mt-0.5 text-lg leading-none">' . \esc_html($icon === '' ? 'ℹ' : $icon) . '</span>' .
            '<div class="min-w-0 flex-1">' .
                ($title !== '' ? '<p class="font-semibold">' . \esc_html($title) . '</p>' : '') .
                ($message !== '' ? '<p class="mt-1 text-sm leading-6">' . \esc_html($message) . '</p>' : '') .
            '</div>'
        );
    }
}