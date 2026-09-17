<?php
/**
 * Single featured product renderer (WooCommerce-gated at registration).
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
 * Spotlight a single product: image, title, price, short description and a
 * no-JS add-to-cart link.
 */
final class ProductFeaturedRenderer implements BlockRendererInterface {

    public function render( Node $node, RenderContext $ctx ): HtmlString {
        $notice = HtmlString::element(
            'div',
            $ctx->blockAttrs( $node, array( 'class' => 'text-sm text-text-muted' ) ),
            \esc_html__( 'Select a WooCommerce product to feature.', 'blocky' )
        );

        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            return $notice;
        }

        $product = wc_get_product( (int) ( $node->props['productId'] ?? 0 ) );
        if ( ! $product instanceof \WC_Product ) {
            return $notice;
        }

        $image_side = ( 'right' === (string) ( $node->props['imageSide'] ?? 'left' ) ) ? 'lg:flex-row-reverse' : '';
        $thumb      = get_the_post_thumbnail( $product->get_id(), 'large', array( 'class' => 'w-full rounded-card object-cover lg:w-1/2', 'loading' => 'lazy' ) );
        $price      = function_exists( 'wc_price' ) ? wc_price( wp_strip_all_tags( (string) $product->get_price_html() ) ) : '';
        $desc       = trim( wp_strip_all_tags( (string) $product->get_short_description() ) );

        $inner = ( '' !== $thumb ? (string) $thumb : '' )
            . '<div class="flex min-w-0 flex-1 flex-col justify-center gap-3">'
            . '<h2 class="text-2xl font-semibold"><a class="hover:underline" href="' . esc_url( (string) get_permalink( $product->get_id() ) ) . '">' . esc_html( (string) $product->get_name() ) . '</h2>'
            . ( '' !== $price ? '<div class="text-lg">' . $price . '</div>' : '' )
            . ( '' !== $desc ? '<p class="text-sm leading-6 text-text-muted">' . esc_html( $desc ) . '</p>' : '' )
            . '<div><a class="inline-flex items-center justify-center rounded-button bg-accent-base px-4 py-2 text-sm text-text-on-accent" href="' . esc_url( home_url( '?add-to-cart=' . $product->get_id() ) ) . '">' . esc_html__( 'Add to cart', 'blocky' ) . '</a></div>'
            . '</div>';

        return HtmlString::element(
            'div',
            $ctx->blockAttrs( $node, array( 'class' => 'flex flex-col gap-6 lg:flex-row lg:items-center ' . $image_side ) ),
            $inner
        );
    }
}
