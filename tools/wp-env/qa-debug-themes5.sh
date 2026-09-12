#!/usr/bin/env bash
# Add error handler and directly call theme function with error capture
wp --path=/var/www/blocky eval '
// Enable error capture
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "PHP ERROR [$errno]: $errstr in $errfile:$errline" . PHP_EOL;
    return true;
});

// Check if ThemeVariant class is accessible
echo "ThemeVariant class exists: " . (class_exists("Blocky\Core\Tokens\ThemeVariant") ? "YES" : "NO") . PHP_EOL;
echo "ThemeEngine class exists: " . (class_exists("Blocky\Core\Tokens\ThemeEngine") ? "YES" : "NO") . PHP_EOL;

// Get engine and call theme function
$engine = Blocky\Core\Tokens\ThemeEngine::getInstance();
echo "Before: " . count($engine->allVariants()) . " variants" . PHP_EOL;

try {
    Blocky\Theme\register_theme_variants($engine);
    echo "After: " . count($engine->allVariants()) . " variants" . PHP_EOL;
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

restore_error_handler();
' --allow-root 2>&1
