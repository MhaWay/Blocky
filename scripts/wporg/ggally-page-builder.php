<?php
/**
 * Plugin Name:       GG-Ally Page Builder
 * Plugin URI:        https://github.com/MhaWay/Blocky
 * Description:       The Tailwind-native page builder by GG-Ally. Design visually; output is static hashed CSS with no runtime.lated UI, and a deterministic static-CSS engine. No bloat, no arbitrary CSS classes, no runtime overhead.
 * Version:           0.1.3
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            GG-Ally
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ggally-page-builder
 */

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

defined('ABSPATH') || exit;

// The engine registers its activation hooks against the MAIN plugin file
// (engine/engine.php is only a bootstrap include, never the activated file).
if (!defined('GGALLY_MAIN_PLUGIN_FILE')) {
    define('GGALLY_MAIN_PLUGIN_FILE', __FILE__);
}

// Engine (block registry, tokens, compiler, REST).
require_once __DIR__ . '/engine/engine.php';
// Builder (visual editor SPA, admin integrations).
require_once __DIR__ . '/builder/builder.php';
