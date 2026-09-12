<?php
/**
 * Blocky dev helpers — MU plugin loaded by wp-env in development.
 * DO NOT use in production.
 */

declare(strict_types=1);

if (!defined('ABSPATH') || !(defined('BLOCKY_DEV') && BLOCKY_DEV)) {
    return;
}

function blocky_dev_request_scheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

    if ($https === 'on' || $https === '1' || strtolower((string) $forwardedProto) === 'https') {
        return 'https';
    }

    return 'http';
}

function blocky_dev_request_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost:8888';
    return is_string($host) && $host !== '' ? $host : 'localhost:8888';
}

function blocky_dev_base_url(): string
{
    return blocky_dev_request_scheme() . '://' . blocky_dev_request_host();
}

function blocky_dev_asset_url(int $port): string
{
    $configured = $port === 5174
        ? (defined('BLOCKY_BUILDER_ASSET_URL') ? BLOCKY_BUILDER_ASSET_URL : '')
        : (defined('BLOCKY_THEME_ASSET_URL') ? BLOCKY_THEME_ASSET_URL : '');

    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    if (defined('BLOCKY_ASSET_URL') && is_string(BLOCKY_ASSET_URL) && BLOCKY_ASSET_URL !== '') {
        return rtrim(BLOCKY_ASSET_URL, '/');
    }

    $host = preg_replace('/:\d+$/', '', blocky_dev_request_host()) ?: 'localhost';
    return blocky_dev_request_scheme() . '://' . $host . ':' . $port;
}

function blocky_theme_has_dist_manifest(): bool
{
    $themeDir = function_exists('get_stylesheet_directory') ? get_stylesheet_directory() : '';
    if (!is_string($themeDir) || $themeDir === '') {
        return false;
    }

    return file_exists($themeDir . '/dist/.vite/manifest.json');
}

function blocky_should_inject_theme_hmr(): bool
{
    return !blocky_theme_has_dist_manifest();
}

add_filter('pre_option_home', static fn() => blocky_dev_base_url());
add_filter('pre_option_siteurl', static fn() => blocky_dev_base_url());
add_filter('allowed_redirect_hosts', static function (array $hosts): array {
    $hosts[] = preg_replace('/:\d+$/', '', blocky_dev_request_host()) ?: 'localhost';
    return array_values(array_unique($hosts));
});

// Disable object cache
add_filter('enable_loading_object_cache_dropin', '__return_false');

// Suppress admin email verification
add_filter('admin_email_check_interval', '__return_zero');

// Disable fatal error mailer
add_filter('wp_fatal_error_handler_enabled', '__return_false');

// Show all errors
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// CORS headers for Vite dev server
add_action('init', static function (): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $themeDevUrl = blocky_dev_asset_url(5173);
    $builderDevUrl = blocky_dev_asset_url(5174);

    if ($origin === $themeDevUrl || $origin === $builderDevUrl) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }
    }
}, 0);

// Add Vite HMR module tag
add_action('wp_head', static function (): void {
    if (!blocky_should_inject_theme_hmr()) {
        return;
    }

    $devUrl = blocky_dev_asset_url(5173);
    echo '<script type="module" src="' . esc_url($devUrl) . '/@vite/client"></script>' . "\n";
}, 1);

// Force page to reload when Vite updates
add_action('wp_footer', static function (): void {
    if (!blocky_should_inject_theme_hmr()) {
        return;
    }

    echo '<script>
if (import.meta?.hot) {
  import.meta.hot.on("vite:beforeFullReload", () => window.location.reload());
}
</script>';
}, 100);
