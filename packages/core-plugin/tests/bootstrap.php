<?php
declare(strict_types=1);

define('ABSPATH', '/tmp/blocky-abspath/');
define('BLOCKY_CORE_DIR', dirname(__DIR__) . '/');
define('BLOCKY_CORE_URL', 'https://example.test/wp-content/plugins/core-plugin/');
define('BLOCKY_CORE_VERSION', '0.1.0');

if (!function_exists('__')) {
	function __(string $text, ?string $domain = null): string
	{
		return $text;
	}
}
if (!function_exists('apply_filters')) {
	function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
	{
		return $value;
	}
}
if (!function_exists('_x')) {
	function _x(string $text, string $context, ?string $domain = null): string
	{
		return $text;
	}
}
if (!function_exists('esc_html__')) {
	function esc_html__(string $text, ?string $domain = null): string
	{
		return $text;
	}
}

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
