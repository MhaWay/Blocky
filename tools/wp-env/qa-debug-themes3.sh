#!/usr/bin/env bash
wp --path=/var/www/blocky eval '
// Add debug hook to verify argument type
add_action("blocky/register_themes", function($arg) {
    echo "Action fired! Arg type: " . get_class($arg) . PHP_EOL;
    echo "Arg is ThemeEngine: " . ($arg instanceof Blocky\Core\Tokens\ThemeEngine ? "YES" : "NO") . PHP_EOL;
    echo "Variants before: " . count($arg->allVariants()) . PHP_EOL;
    $arg->registerVariant(new Blocky\Core\Tokens\ThemeVariant(
        id: "test--light", brand: "test", mode: "light", tokens: [], parent: null, cssFile: ""
    ));
    echo "Variants after inline: " . count($arg->allVariants()) . PHP_EOL;
}, 5);  // priority 5 — before priority 10 (theme function)

// Re-fire the action
do_action("blocky/register_themes", Blocky\Core\Tokens\ThemeEngine::getInstance());

echo "ThemeEngine global count: " . count(Blocky\Core\Tokens\ThemeEngine::getInstance()->allVariants()) . PHP_EOL;
' --allow-root 2>&1
