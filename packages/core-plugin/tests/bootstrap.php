<?php
declare(strict_types=1);

define('ABSPATH', '/tmp/blocky-abspath/');
define('BLOCKY_CORE_DIR', dirname(__DIR__) . '/');
define('BLOCKY_CORE_URL', 'https://example.test/wp-content/plugins/core-plugin/');
define('BLOCKY_CORE_VERSION', '0.1.0');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Blocky\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BLOCKY_CORE_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
