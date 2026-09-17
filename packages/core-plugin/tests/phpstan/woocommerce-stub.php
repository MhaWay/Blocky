<?php
/**
 * Minimal WooCommerce surface used by the product renderers, for PHPStan only.
 * The real plugin is never a static-analysis dependency: blocks register only
 * when class_exists('WooCommerce'), so production code is fully guarded.
 */

if (!class_exists('WC_Product')) {
    class WC_Product
    {
        public function get_id(): int { return 0; }
        public function get_name(): string { return ''; }
        public function get_price_html(): string { return ''; }
        public function get_short_description(): string { return ''; }
        public function is_on_sale(): bool { return false; }
    }
}

if (!function_exists('wc_get_product')) {
    function wc_get_product($product_id = false) { return false; }
}
if (!function_exists('wc_price')) {
    function wc_price($price, $args = []) { return ''; }
}
if (!function_exists('wc_get_product_ids_on_sale')) {
    function wc_get_product_ids_on_sale() { return []; }
}
