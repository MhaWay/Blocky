<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class ArchivePostsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        global $wp_query;

        $query = $wp_query instanceof \WP_Query ? $wp_query : new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => max(1, (int) ($node->props['perPage'] ?? 6)),
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Archive posts will render here.' : '');
        }

        $showExcerpt = (bool) ($node->props['showExcerpt'] ?? true);
        $html = '<div class="space-y-4">';
        foreach ($query->posts as $post) {
            \setup_postdata($post);
            $structure = ComponentStructure::render($ctx, $node);
            if ($structure !== null) {
                $html .= '<article class="h-full">' . $structure->toString() . '</article>';
                \wp_reset_postdata();
                continue;
            }
            $html .= '<article class="rounded-card border border-border-subtle bg-surface-base p-5">'
                . '<h3 class="text-lg font-semibold"><a href="' . \esc_url((string) \get_permalink($post)) . '" class="hover:text-accent-text">' . \esc_html((string) \get_the_title($post)) . '</a></h3>'
                . ($showExcerpt ? '<p class="mt-2 text-sm leading-6 text-text-muted">' . \esc_html(wp_strip_all_tags((string) \get_the_excerpt($post))) . '</p>' : '')
                . '</article>';
            \wp_reset_postdata();
        }
        $html .= '</div>';

        return ComponentStructure::decorate($ctx, $node, HtmlString::element('div', $ctx->blockAttrs($node), $html)->toString());
    }
}