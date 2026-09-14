<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class SitemapRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Sitemap'));
        $includePages = (bool) ($node->props['includePages'] ?? true);
        $includePosts = (bool) ($node->props['includePosts'] ?? true);
        $postsPerPage = max(1, min(20, (int) ($node->props['postsPerPage'] ?? 6)));
        $showDescriptions = (bool) ($node->props['showDescriptions'] ?? false);
        $orderBy = trim((string) ($node->props['orderBy'] ?? 'menu_order'));

        if ($ctx->isEditorMode()) {
            $preview = '<div class="space-y-6">'
                . '<div><p class="text-sm font-semibold text-text-base">Pages</p><ul class="mt-3 space-y-2 text-sm text-text-muted"><li>Home</li><li>About</li><li>Contact</li></ul></div>'
                . '<div><p class="text-sm font-semibold text-text-base">Posts</p><ul class="mt-3 space-y-2 text-sm text-text-muted"><li>Launch update</li><li>Design notes</li></ul></div>'
                . '</div>';

            return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Sitemap']), ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '') . $preview);
        }

        $sections = [];
        if ($includePages) {
            $pagesHtml = $this->renderPages($orderBy, $showDescriptions);
            if ($pagesHtml !== '') {
                $sections[] = '<section><h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-text-muted">' . esc_html__('Pages', 'blocky') . '</h4>' . $pagesHtml . '</section>';
            }
        }

        if ($includePosts) {
            $postsHtml = $this->renderPosts($postsPerPage, $showDescriptions);
            if ($postsHtml !== '') {
                $sections[] = '<section><h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-text-muted">' . esc_html__('Posts', 'blocky') . '</h4>' . $postsHtml . '</section>';
            }
        }

        $inner = ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . ($sections !== [] ? '<div class="mt-4 grid gap-6 md:grid-cols-2">' . implode('', $sections) . '</div>' : '<p class="mt-4 text-sm text-text-muted">' . esc_html__('No public content found.', 'blocky') . '</p>');

        return HtmlString::element('nav', $ctx->blockAttrs($node, ['aria-label' => 'Sitemap']), $inner);
    }

    private function renderPages(string $orderBy, bool $showDescriptions): string
    {
        $pages = get_pages([
            'sort_column' => $orderBy === 'title' ? 'post_title' : ($orderBy === 'date' ? 'post_date' : 'menu_order,post_title'),
            'sort_order' => 'ASC',
            'post_status' => 'publish',
        ]);

        if (!is_array($pages) || $pages === []) {
            return '';
        }

        $byParent = [];
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post) {
                continue;
            }
            $byParent[(int) $page->post_parent][] = $page;
        }

        return $this->renderPageTree($byParent, 0, $showDescriptions);
    }

    /** @param array<int, array<int, \WP_Post>> $byParent */
    private function renderPageTree(array $byParent, int $parentId, bool $showDescriptions): string
    {
        $pages = $byParent[$parentId] ?? [];
        if ($pages === []) {
            return '';
        }

        $items = '';
        foreach ($pages as $page) {
            $description = $showDescriptions && trim((string) $page->post_excerpt) !== ''
                ? '<span class="mt-1 block text-xs leading-5 text-text-muted">' . esc_html((string) $page->post_excerpt) . '</span>'
                : '';
            $children = $this->renderPageTree($byParent, (int) $page->ID, $showDescriptions);
            $items .= '<li><a href="' . esc_url((string) get_permalink($page)) . '" class="text-sm font-medium text-text-base hover:text-accent-text">' . esc_html((string) get_the_title($page)) . '</a>' . $description . $children . '</li>';
        }

        return '<ul class="mt-3 space-y-3 pl-0' . ($parentId > 0 ? ' border-l border-border-subtle pl-4' : '') . '">' . $items . '</ul>';
    }

    private function renderPosts(int $postsPerPage, bool $showDescriptions): string
    {
        $query = new \WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $postsPerPage,
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ]);

        if (!$query->have_posts()) {
            return '';
        }

        $items = '';
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $description = $showDescriptions
                ? trim((string) get_the_excerpt($post))
                : '';
            $items .= '<li><a href="' . esc_url((string) get_permalink($post)) . '" class="text-sm font-medium text-text-base hover:text-accent-text">' . esc_html((string) get_the_title($post)) . '</a>'
                . ($description !== '' ? '<span class="mt-1 block text-xs leading-5 text-text-muted">' . esc_html(wp_strip_all_tags($description)) . '</span>' : '')
                . '</li>';
        }

        wp_reset_postdata();

        return '<ul class="mt-3 space-y-3 pl-0">' . $items . '</ul>';
    }
}