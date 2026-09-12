<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class EmbedIframeRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $url = trim((string) ($node->props['url'] ?? ''));
        $title = trim((string) ($node->props['title'] ?? 'Embedded iframe'));
        $sandbox = trim((string) ($node->props['sandbox'] ?? 'allow-scripts allow-same-origin allow-forms'));
        $allow = trim((string) ($node->props['allow'] ?? 'fullscreen; autoplay; clipboard-read; clipboard-write'));
        $lazyLoad = (bool) ($node->props['lazyLoad'] ?? true);
        $allowFullscreen = (bool) ($node->props['allowFullscreen'] ?? true);

        $classes = $ctx->resolveVariantClasses($node, [
            'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[30rem]'],
            'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
        ]);

        if ($url === '') {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs($node, ['class' => trim('flex items-center justify-center rounded-card border border-dashed border-border-base bg-surface-elevated text-sm text-text-muted ' . $classes)]),
                $ctx->isEditorMode() ? 'Add an iframe URL to render this block.' : '',
            );
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, ['class' => trim('overflow-hidden rounded-card border border-border-subtle bg-surface-base ' . $classes)]),
            '<iframe class="h-full w-full" src="' . esc_url($url) . '" title="' . esc_attr($title) . '" sandbox="' . esc_attr($sandbox) . '" allow="' . esc_attr($allow) . '" loading="' . ($lazyLoad ? 'lazy' : 'eager') . '" referrerpolicy="strict-origin-when-cross-origin"' . ($allowFullscreen ? ' allowfullscreen' : '') . '></iframe>'
        );
    }
}