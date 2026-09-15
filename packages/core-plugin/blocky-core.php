<?php
/**
 * Plugin Name: Blockwork Engine (Blocky Core)
 * Plugin URI: https://blocky.dev
 * Description: Core engine for Blockwork: block registry, token resolver, render pipeline, Provides block registry, token resolver, render pipeline, REST API, and asset orchestration.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: GG-Ally
 * Author URI: https://ggally.net
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: blocky
 * Domain Path: /languages
 *
 * @package Blocky\Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

if (!defined('ABSPATH')) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────

define('BLOCKY_CORE_VERSION',   '0.1.0');
define('BLOCKY_CORE_FILE',      __FILE__);
define('BLOCKY_CORE_DIR',       \plugin_dir_path(__FILE__));
// Runtime-computed so the URL follows the current site_url option
// (domain migrations, multi-host dev) instead of the bootstrap constant.
$blockyCoreUrl  = \plugin_dir_url(__FILE__);
$blockyCorePath = \wp_normalize_path(\plugin_dir_path(__FILE__));
$blockyContentDir = \wp_normalize_path(WP_CONTENT_DIR);
if (0 === \strpos($blockyCorePath, $blockyContentDir)) {
    $blockyCoreUrl = \trailingslashit(\set_url_scheme(\site_url('wp-content' . \substr($blockyCorePath, \strlen($blockyContentDir)))));
}
define('BLOCKY_CORE_URL',       $blockyCoreUrl);
define('BLOCKY_CORE_SLUG',      'blocky-core');

// ── Autoloader ────────────────────────────────────────────────────────────────

if (file_exists(BLOCKY_CORE_DIR . 'vendor/autoload.php')) {
    require_once BLOCKY_CORE_DIR . 'vendor/autoload.php';
} else {
    // Simple PSR-4 autoloader fallback for development
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Blocky\\Core\\';
        $baseDir = BLOCKY_CORE_DIR . 'src/';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────

// WP 6.7+: translations (and anything that uses them) must load at init,
// not plugins_loaded - boot() registers core block labels via __().
\add_action('init', static function (): void {
    \load_plugin_textdomain('blocky', false, \dirname(\plugin_basename(__FILE__)) . '/languages');

    $plugin = \Blocky\Core\Plugin::getInstance();
    $plugin->boot();
}, 1);

// ── Activation / Deactivation hooks ──────────────────────────────────────────

\register_activation_hook(__FILE__, static function (): void {
    \Blocky\Core\Plugin::onActivate();
});

\register_deactivation_hook(__FILE__, static function (): void {
    \Blocky\Core\Plugin::onDeactivate();
});
