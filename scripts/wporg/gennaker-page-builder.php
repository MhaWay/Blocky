<?php
/**
 * Plugin Name:       Gennaker — Page Builder
 * Plugin URI:        https://github.com/MhaWay/Blocky
 * Description:       The Tailwind-native page builder: build pages with customizable blocks, fully translated UI, and a deterministic static-CSS engine. No bloat, no arbitrary CSS classes, no runtime overhead.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            GG-Ally
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gennaker-page-builder
 */

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

defined('ABSPATH') || exit;

// Engine (block registry, tokens, compiler, REST).
require_once __DIR__ . '/engine/engine.php';
// Builder (visual editor SPA, admin integrations).
require_once __DIR__ . '/builder/builder.php';
