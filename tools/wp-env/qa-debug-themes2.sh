#!/usr/bin/env bash
wp --path=/var/www/blocky eval '
wp_set_current_user(1);

// Manually call the theme variant registration function
$engine = Blocky\Core\Tokens\ThemeEngine::getInstance();
echo "Before manual call: " . count($engine->allVariants()) . " variants" . PHP_EOL;

// Call the function directly
Blocky\Theme\register_theme_variants($engine);

echo "After manual call: " . count($engine->allVariants()) . " variants" . PHP_EOL;
foreach ($engine->allVariants() as $id => $v) {
    echo "  - $id" . PHP_EOL;
}

// Now test the REST endpoint
$req = new WP_REST_Request("GET", "/blocky/v1/themes");
$resp = rest_do_request($req);
echo "REST /themes: " . json_encode($resp->get_data()) . PHP_EOL;
' --allow-root 2>&1
