<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class WpPostContentRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $postId  = \get_the_ID() ?: 0;
        $content = $postId > 0
            ? \apply_filters('the_content', \get_post_field('post_content', $postId)) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying core the_content filter intentionally.
            : '';

        if ($content === '') {
            $inner = $ctx->isEditorMode()
                ? '<p style="opacity:.45;font-style:italic">Post content will appear here.</p>'
                : '';
        } else {
            $inner = $content; // already filtered / trusted
        }

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'wp-content prose max-w-none']), $inner);
    }
}
