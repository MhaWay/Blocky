#!/usr/bin/env bash
wp --path=/var/www/blocky eval '
// Check if the action hook exists
global $wp_filter;
$hook = "blocky/register_themes";
if (isset($wp_filter[$hook])) {
    echo "Hook registered: YES" . PHP_EOL;
    foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $cb) {
            echo "  priority=$priority function=" . print_r($cb["function"], true) . PHP_EOL;
        }
    }
} else {
    echo "Hook registered: NO" . PHP_EOL;
}

// Check when Plugin boot runs
$did = did_action("blocky/register_themes");
echo "Times fired: $did" . PHP_EOL;

// Check ThemeEngine
$variants = Blocky\Core\Tokens\ThemeEngine::getInstance()->allVariants();
echo "Variant count: " . count($variants) . PHP_EOL;

// Check active theme
echo "Active theme: " . wp_get_theme()->get("Name") . PHP_EOL;

// Check if theme functions.php is loaded
echo "Function exists register_theme_variants: " . (function_exists("Blocky\\Theme\\register_theme_variants") ? "YES" : "NO") . PHP_EOL;
' --allow-root 2>&1
