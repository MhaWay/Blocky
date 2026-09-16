<?php
/**
 * Plugin Name: Blockwork Builder
 * Plugin URI:  https://github.com/MhaWay/Blocky
 * Description: The Tailwind-native page builder: customizable blocks, translated UI, deterministic static-CSS engine.
 * Version:     0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: GG-Ally
 * License: GPL-2.0-or-later
 * Text Domain: blocky
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace Blocky\Builder;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

if (!defined('ABSPATH')) {
    exit;
}

define('BLOCKY_BUILDER_VERSION', '0.1.0');
define('BLOCKY_BUILDER_FILE',    __FILE__);
define('BLOCKY_BUILDER_DIR',     \plugin_dir_path(__FILE__));
// Runtime-computed so the URL follows the current site_url option
// (domain migrations, multi-host dev) instead of the bootstrap constant.
$blockyBuilderUrl  = \plugin_dir_url(__FILE__);
$blockyBuilderPath = \wp_normalize_path(\plugin_dir_path(__FILE__));
$blockyContentDir  = \wp_normalize_path(WP_CONTENT_DIR);
if (0 === \strpos($blockyBuilderPath, $blockyContentDir)) {
    $blockyBuilderUrl = \trailingslashit(\set_url_scheme(\site_url('wp-content' . \substr($blockyBuilderPath, \strlen($blockyContentDir)))));
}
define('BLOCKY_BUILDER_URL',     $blockyBuilderUrl);
define('BLOCKY_BUILDER_SLUG',    'blocky-builder');

// PSR-4 autoloader for Blocky\Builder\ namespace
spl_autoload_register(static function (string $class): void {
    $prefix = 'Blocky\\Builder\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file     = BLOCKY_BUILDER_DIR . 'php/' . $relative . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Same WP 6.7 rule as core: translations load at init, not plugins_loaded.
\add_action('init', static function (): void {
    \load_plugin_textdomain('blocky', false, \dirname(\plugin_basename(__FILE__)) . '/languages');

    // Require Core Plugin
    if (!defined('BLOCKY_CORE_VERSION')) {
        \add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>'
                . \esc_html__('GG Builder requires the GG Core plugin to be active.', 'blocky')
                . '</p></div>';
        });
        return;
    }

    BuilderScreen::getInstance()->boot();
    RestExtensions::getInstance()->boot();
    AdminIntegration::getInstance()->boot();
});
