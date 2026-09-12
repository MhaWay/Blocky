<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class PostsListRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $query = new \WP_Query([
            'post_type' => (string) ($node->props['postType'] ?? 'post'),
            'posts_per_page' => max(1, (int) ($node->props['perPage'] ?? 3)),
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Posts list will render here.' : '');
        }

        $showExcerpt = (bool) ($node->props['showExcerpt'] ?? true);
        $items = '';
        while ($query->have_posts()) {
            $query->the_post();
            $items .= '<article class="space-y-2 rounded-card border border-border-subtle bg-surface-base p-5">'
                . '<h3 class="text-lg font-semibold"><a href="' . \esc_url((string) \get_permalink()) . '" class="hover:text-accent-text">' . \esc_html((string) \get_the_title()) . '</a></h3>'
                . ($showExcerpt ? '<p class="text-sm leading-6 text-text-muted">' . \esc_html(wp_strip_all_tags((string) \get_the_excerpt())) . '</p>' : '')
                . '</article>';
        }
        \wp_reset_postdata();

        return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'space-y-4']), $items);
    }
}