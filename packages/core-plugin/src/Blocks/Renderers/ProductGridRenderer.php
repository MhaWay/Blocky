<?php
/**
 * Product grid renderer (WooCommerce-gated at registration).
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
 * Grid of products. Add-to-cart uses WooCommerce's native no-JS
 * ?add-to-cart={id} flow: static markup, zero custom JavaScript.
 */
final class ProductGridRenderer implements BlockRendererInterface {

    public function render( Node $node, RenderContext $ctx ): HtmlString {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return HtmlString::element(
                'div',
                $ctx->blockAttrs( $node, array( 'class' => 'text-sm text-text-muted' ) ),
                \esc_html__( 'WooCommerce is not active on this site.', 'blocky' )
            );
        }

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, min( 24, (int) ( $node->props['number'] ?? 6 ) ) ),
            'orderby'        => (string) ( $node->props['orderby'] ?? 'date' ),
        );
        $category = trim( (string) ( $node->props['category' ] ?? '' ) );
        if ( '' !== $category ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }
        if ( ! empty( $node->props['onSaleOnly'] ) && function_exists( 'wc_get_product_ids_on_sale' ) ) {
            $args['post__in'] = wc_get_product_ids_on_sale();
        }

        $query = new \WP_Query( $args );
        if ( ! $query->have_posts() ) {
            \wp_reset_postdata();
            return HtmlString::element(
                'div',
                $ctx->blockAttrs( $node, array( 'class' => 'text-sm text-text-muted' ) ),
                \esc_html__( 'No products found.', 'blocky' )
            );
        }

        /** @var array<string, string> $columns_map */
        $columns_map = array(
            '2' => 'grid-cols-1 md:grid-cols-2',
            '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
            '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
        );
        $columns_class = $ctx->resolveVariantClasses( $node, array( 'columns' => $columns_map ) );

        $show_price = (bool) ( $node->props['showPrice'] ?? true );
        $show_cart  = (bool) ( $node->props['showAddToCart'] ?? true );

        $cards = '';
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = (int) get_the_ID();
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
            if ( null === $product ) {
                continue;
            }
            $link    = (string) get_permalink();
            $thumb   = (string) get_the_post_thumbnail( $post_id, 'woocommerce_thumbnail', array( 'class' => 'w-full rounded-base object-cover', 'loading' => 'lazy' ) );
            $price   = ( $show_price && function_exists( 'wc_price' ) ) ? wc_price( $product->get_price_html() ? wp_strip_all_tags( $product->get_price_html() ) : '' ) : '';
            $badge   = ( function_exists( 'wc_get_product_is_on_sale' ) && $product->is_on_sale() )
                ? '<span class="rounded-button bg-accent-subtle px-2 py-0.5 text-xs">' . esc_html__( 'Sale', 'blocky' ) . '</span>'
                : '';
            $button  = $show_cart
                ? '<a class="mt-3 inline-flex items-center justify-center rounded-button bg-accent-base px-3 py-1.5 text-sm text-text-on-accent" href="' . esc_url( home_url( '?add-to-cart=' . (int) $product->get_id() ) ) . '">'
                    . esc_html__( 'Add to cart', 'blocky' ) . '</a>'
                : '';

            $cards .= '<article class="flex h-full flex-col gap-2 rounded-card border border-border-subtle bg-surface-base p-4">'
                . ( '' !== $thumb ? '<a href="' . esc_url( $link ) . '">' . $thumb . '</a>' : '' )
                . '<div class="flex items-center justify-between gap-2"><h3 class="text-base font-semibold"><a class="hover:underline" href="' . esc_url( $link ) . '">' . esc_html( get_the_title() ) . '</a></h3>' . $badge . '</div>'
                . ( '' !== $price ? '<div class="text-sm">' . $price . '</div>' : '' )
                . $button
                . '</article>';
        }
        \wp_reset_postdata();

        return HtmlString::element(
            'div',
            $ctx->blockAttrs( $node, array( 'class' => 'grid gap-4 ' . $columns_class ) ),
            $cards
        );
    }
}
