<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class TaxonomyListRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $title = trim((string) ($node->props['title'] ?? 'Browse topics'));
        $taxonomy = sanitize_key((string) ($node->props['taxonomy'] ?? 'category'));
        $layout = (string) ($node->props['layout'] ?? 'pills');
        $showCount = (bool) ($node->props['showCount'] ?? true);
        $limit = max(1, min(50, (int) ($node->props['limit'] ?? 8)));

        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Set a valid taxonomy slug to render terms.' : '');
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => $limit,
        ]);

        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return HtmlString::element('div', $ctx->blockAttrs($node, ['class' => 'text-sm text-text-muted']), $ctx->isEditorMode() ? 'Taxonomy terms will render here.' : '');
        }

        $items = '';
        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }
            $count = $showCount ? '<span class="text-xs opacity-75">' . (int) $term->count . '</span>' : '';
            if ($layout === 'list') {
                $items .= '<li class="flex items-center justify-between gap-3 rounded-card border border-border-subtle bg-surface-base px-4 py-3"><a href="' . esc_url((string) get_term_link($term)) . '" class="font-medium hover:text-accent-text">' . esc_html($term->name) . '</a>' . $count . '</li>';
            } else {
                $items .= '<li><a href="' . esc_url((string) get_term_link($term)) . '" class="inline-flex items-center gap-2 rounded-full border border-border-subtle bg-surface-base px-3 py-2 text-sm font-medium text-text-base transition-colors hover:border-accent-base hover:text-accent-text">' . esc_html($term->name) . $count . '</a></li>';
            }
        }

        $listClasses = $layout === 'list' ? 'mt-4 space-y-3' : 'mt-4 flex flex-wrap gap-3';
        $content = ($title !== '' ? '<h3 class="text-lg font-semibold text-text-base">' . esc_html($title) . '</h3>' : '')
            . '<ul class="' . esc_attr($listClasses) . '">' . $items . '</ul>';

        return HtmlString::element('section', $ctx->blockAttrs($node), $content);
    }
}