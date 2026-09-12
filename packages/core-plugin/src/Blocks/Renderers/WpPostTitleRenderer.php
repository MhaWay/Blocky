<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpPostTitleRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $level = max(1, min(6, (int) ($node->props['level'] ?? 1)));
        $tag   = 'h' . $level;

        $postId = \get_the_ID() ?: 0;
        $title  = $postId > 0 ? \get_the_title($postId) : '';

        if ($title === '') {
            $inner = $ctx->isEditorMode()
                ? '<em style="opacity:.45">Post Title</em>'
                : '';
        } else {
            $inner = \esc_html($title);
        }

        $sizeClass = match ($level) {
            1 => 'text-5xl font-display font-bold leading-tight',
            2 => 'text-4xl font-display font-bold leading-tight',
            3 => 'text-3xl font-display font-semibold leading-snug',
            4 => 'text-2xl font-semibold leading-snug',
            5 => 'text-xl font-semibold',
            6 => 'text-lg font-semibold',
        };

        return HtmlString::element($tag, $ctx->blockAttrs($node, ['class' => $sizeClass]), $inner);
    }
}
