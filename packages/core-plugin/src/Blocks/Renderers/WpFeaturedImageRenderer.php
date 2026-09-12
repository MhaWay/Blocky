<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpFeaturedImageRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId  = \get_the_ID() ?: 0;
        $size    = (string) ($node->props['size'] ?? 'large');
        $rounded = (string) ($node->props['rounded'] ?? 'none');

        $roundedClass = match ($rounded) {
            'base' => 'rounded-lg',
            'lg'   => 'rounded-2xl',
            'full' => 'rounded-full',
            default => '',
        };

        if ($postId > 0 && \has_post_thumbnail($postId)) {
            $img = \get_the_post_thumbnail($postId, $size, [
                'class' => trim("w-full h-auto {$roundedClass}"),
            ]);
            return HtmlString::element('div', $ctx->blockAttrs($node, []), $img);
        }

        if ($ctx->isEditorMode()) {
            $placeholder = HtmlString::element(
                'div',
                ['style' => 'background:#e5e7eb;display:flex;align-items:center;justify-content:center;min-height:180px;'],
                '<span style="font-size:13px;color:#9ca3af;font-style:italic">No featured image</span>',
            )->toString();
            return HtmlString::element('div', $ctx->blockAttrs($node, []), $placeholder);
        }

        return HtmlString::of('');
    }
}
