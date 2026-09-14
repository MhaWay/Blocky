<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class PostsGridRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $query = new \WP_Query([
            'post_type' => (string) ($node->props['postType'] ?? 'post'),
            'posts_per_page' => max(1, (int) ($node->props['perPage'] ?? 6)),
            'post_status' => 'publish',
        ]);

        if (!$query->have_posts()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Posts grid will render here.' : '');
        }

        $columnsClass = $ctx->resolveVariantClasses($node, [
            'columns' => [
                '2' => 'grid-cols-1 md:grid-cols-2',
                '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
            ],
        ]);

        $showExcerpt = (bool) ($node->props['showExcerpt'] ?? true);
        $cards = '';
        while ($query->have_posts()) {
            $query->the_post();
            $structure = ComponentStructure::render($ctx, $node);
            if ($structure !== null) {
                $cards .= '<article class="h-full">' . $structure->toString() . '</article>';
                continue;
            }
            $cards .= '<article class="flex h-full flex-col rounded-card border border-border-subtle bg-surface-base p-5">'
                . '<h3 class="text-lg font-semibold"><a href="' . \esc_url((string) \get_permalink()) . '" class="hover:text-accent-text">' . \esc_html((string) \get_the_title()) . '</a></h3>'
                . ($showExcerpt ? '<p class="mt-2 text-sm leading-6 text-text-muted">' . \esc_html(wp_strip_all_tags((string) \get_the_excerpt())) . '</p>' : '')
                . '</article>';
        }
        \wp_reset_postdata();

        return ComponentStructure::decorate($ctx, $node, HtmlString::element('div', $ctx->blockAttrs($node, ['class' => trim('grid gap-6 ' . $columnsClass)]), $cards)->toString());
    }
}