#!/usr/bin/env bash
# Enable WP debug and check if the theme function fires
wp --path=/var/www/blocky eval '
// Simulate a full request by checking when/how action fires
$trace_file = "/tmp/blocky_trace.txt";
@unlink($trace_file);

// Add early hook to trace
add_action("blocky/register_themes", function($engine) use ($trace_file) {
    $variants_before = count($engine->allVariants());
    
    // Try the same thing the theme does
    $template_uri = get_template_directory_uri();
    $engine->registerVariant(new Blocky\Core\Tokens\ThemeVariant(
        id:      "default--light",
        brand:   "default",
        mode:    "light",
        tokens:  [],
        parent:  null,
        cssFile: $template_uri . "/dist/theme-default-light.css",
    ));
    
    $variants_after = count($engine->allVariants());
    file_put_contents($trace_file, "before=$variants_before after=$variants_after" . PHP_EOL, FILE_APPEND);
    
    // Check if same instance as singleton
    $singleton = Blocky\Core\Tokens\ThemeEngine::getInstance();
    $same = ($engine === $singleton) ? "YES" : "NO";
    file_put_contents($trace_file, "same_as_singleton=$same" . PHP_EOL, FILE_APPEND);
    file_put_contents($trace_file, "singleton_count=" . count($singleton->allVariants()) . PHP_EOL, FILE_APPEND);
}, 5);

// Re-trigger the action as plugin would
$engine = Blocky\Core\Tokens\ThemeEngine::getInstance();
do_action("blocky/register_themes", $engine);

echo file_get_contents($trace_file);
echo "Final engine count: " . count($engine->allVariants()) . PHP_EOL;
echo "Final singleton count: " . count(Blocky\Core\Tokens\ThemeEngine::getInstance()->allVariants()) . PHP_EOL;
' --allow-root 2>&1
