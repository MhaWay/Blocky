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

if (!function_exists('esc_attr')) {
	function esc_attr(string $text, $quote_style = ENT_QUOTES): string
	{
		return htmlspecialchars($text, $quote_style, 'UTF-8');
	}
}
if (!function_exists('esc_html')) {
	function esc_html(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_url')) {
	function esc_url(string $url): string
	{
		if (preg_match('%^\s*(?:javascript|vbscript|data)\s*:%i', $url)) {
			return '';
		}
		return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('wp_kses_post')) {
	function wp_kses_post(string $content): string
	{
		return strip_ignorable_xss($content);
	}
}
if (!function_exists('strip_ignorable_xss')) {
	function strip_ignorable_xss(string $content): string
	{
		// Approximates kses for tests: drops script/style tags and on* handlers.
		$out = preg_replace('%<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>%is', '', $content) ?? '';
		$out = preg_replace('%\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)%i', '', $out) ?? '';
		return $out;
	}
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

if (!function_exists('wp_kses')) {
	function wp_kses(string $content, array $allowed): string
	{
		return strip_ignorable_xss($content);
	}
}
