<?php
declare(strict_types=1);

// Constants defined by the plugin bootstrap at runtime.
define('BLOCKY_CORE_DIR', __DIR__ . '/../');
define('BLOCKY_CORE_URL', 'https://example.test/wp-content/plugins/blocky-core/');
define('BLOCKY_CORE_VERSION', '0.1.0');

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
