<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class LottieRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $url = trim((string) ($node->props['url'] ?? ''));
        $poster = (int) ($node->props['poster'] ?? 0);
        $autoplay = (bool) ($node->props['autoplay'] ?? true);
        $loop = (bool) ($node->props['loop'] ?? true);
        $speed = max(0.25, min(3.0, (float) ($node->props['speed'] ?? 1)));

        $ratioClasses = $ctx->resolveVariantClasses($node, [
            'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
        ]);

        if ($url === '') {
            if (!$ctx->isEditorMode()) {
                return HtmlString::of('');
            }

            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim('flex items-center justify-center rounded-card border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted ' . $ratioClasses)]),
                'Set a Lottie JSON URL',
            );
        }

        $posterHtml = '';
        if ($poster > 0) {
            $posterHtml = '<div data-bky-lottie-poster class="absolute inset-0 z-10 transition-opacity duration-300">' . (string) wp_get_attachment_image($poster, 'large', false, ['class' => 'h-full w-full object-cover']) . '</div>';
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => trim('overflow-hidden rounded-card border border-border-subtle bg-surface-base ' . $ratioClasses)]),
            '<div data-bky-lottie data-bky-lottie-src="' . esc_url($url) . '" data-bky-lottie-autoplay="' . ($autoplay ? 'true' : 'false') . '" data-bky-lottie-loop="' . ($loop ? 'true' : 'false') . '" data-bky-lottie-speed="' . esc_attr((string) $speed) . '" class="relative h-full w-full">'
            . $posterHtml
            . '<div data-bky-lottie-canvas class="h-full w-full"></div>'
            . '</div>'
        );
    }
}