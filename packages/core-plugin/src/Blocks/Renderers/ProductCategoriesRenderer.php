<?php
/**
 * Product category list renderer (WooCommerce-gated at registration).
 *
 * @package Blocky\Core\Blocks\Renderers
 */

declare( strict_types=1 );

namespace Blocky\Core\Blocks\Renderers;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

/**
 * Links to product categories with product counts, as plain static markup.
 */
final class ProductCategoriesRenderer implements BlockRendererInterface {

    public function render( Node $node, RenderContext $ctx ): HtmlString {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs( $node, array( 'class' => 'text-sm text-text-muted' ) ),
                \esc_html__( 'WooCommerce is not active on this site.', 'blocky' )
            );
        }

        $terms = \get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => max( 1, min( 30, (int) ( $node->props['number'] ?? 8 ) ) ),
        ) );
        if ( ! is_array( $terms ) || array() === $terms ) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs( $node, array( 'class' => 'text-sm text-text-muted' ) ),
                \esc_html__( 'No product categories yet.', 'blocky' )
            );
        }

        $items = '';
        foreach ( $terms as $term ) {
            $link = \get_term_link( $term );
            if ( \is_wp_error( $link ) ) {
                continue;
            }
            $items .= '<li><a class="inline-flex items-center gap-2 rounded-button border border-border-subtle px-3 py-1.5 text-sm hover:bg-surface-hover" href="'
                . esc_url( $link ) . '">' . esc_html( $term->name )
                . '<span class="text-xs text-text-muted">' . (int) $term->count . '</span></a></li>';
        }

        return HtmlString::element(
            'div',
            $ctx->blockAttrs( $node, array( 'class' => '' ) ),
            '<ul class="flex flex-wrap gap-2">' . $items . '</ul>'
        );
    }
}
