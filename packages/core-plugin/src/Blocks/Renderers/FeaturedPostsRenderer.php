<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class FeaturedPostsRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Featured posts'));
        $perPage = max(1, min(12, (int) ($node->props['perPage'] ?? 3)));
        $layout = (string) ($node->props['layout'] ?? 'grid');
        $showExcerpt = (bool) ($node->props['showExcerpt'] ?? true);
        $stickyOnly = (bool) ($node->props['stickyOnly'] ?? true);

        $stickyPosts = array_values(array_filter(array_map('intval', (array) get_option('sticky_posts', []))));
        $queryArgs = [
            'post_type' => 'post',
            'posts_per_page' => $perPage,
            'post_status' => 'publish',
            'ignore_sticky_posts' => false,
        ];

        if ($stickyOnly && $stickyPosts !== []) {
            $queryArgs['post__in'] = array_slice($stickyPosts, 0, $perPage);
            $queryArgs['orderby'] = 'post__in';
        }

        $query = new \WP_Query($queryArgs);

        if (!$query->have_posts()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Featured posts will render here.' : '');
        }

        $cards = '';
        while ($query->have_posts()) {
            $query->the_post();
            $structure = ComponentStructure::render($ctx, $node);
            if ($structure !== null) {
                $cards .= '<article class="h-full">' . $structure->toString() . '</article>';
                continue;
            }
            $thumbnail = has_post_thumbnail()
                ? '<a href="' . esc_url((string) get_permalink()) . '" class="block overflow-hidden rounded-card">' . get_the_post_thumbnail(get_the_ID(), 'medium_large', ['class' => 'aspect-video w-full object-cover']) . '</a>'
                : '';

            $cards .= '<article class="flex h-full flex-col gap-3 rounded-card border border-border-subtle bg-surface-base p-4">'
                . $thumbnail
                . '<div class="space-y-2">'
                . '<h3 class="text-lg font-semibold"><a href="' . esc_url((string) get_permalink()) . '" class="hover:text-accent-text">' . esc_html((string) get_the_title()) . '</a></h3>'
                . ($showExcerpt ? '<p class="text-sm leading-6 text-text-muted">' . esc_html(wp_strip_all_tags((string) get_the_excerpt())) . '</p>' : '')
                . '</div>'
                . '</article>';
        }
        wp_reset_postdata();

        $layoutClasses = $layout === 'list' ? 'space-y-4' : 'grid gap-6 md:grid-cols-2 xl:grid-cols-3';
        $content = ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . '<div class="mt-4 ' . esc_attr($layoutClasses) . '">' . $cards . '</div>';

        return HtmlString::element('section', $ctx->blockAttrs($node, ['class' => 'space-y-0']), $content);
    }
}