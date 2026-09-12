#!/usr/bin/env bash
# Add debug output to ThemesController temporarily
# by using wp eval to simulate the exact HTTP request flow

wp --path=/var/www/blocky eval '
// Check what happens when rest_api_init fires
do_action("rest_api_init");

// Now check the ThemeEngine state
$engine = Blocky\Core\Tokens\ThemeEngine::getInstance();
echo "After rest_api_init: " . count($engine->allVariants()) . " variants" . PHP_EOL;

// Simulate a GET /blocky/v1/themes request
wp_set_current_user(0);
$req = new WP_REST_Request("GET", "/blocky/v1/themes");
$resp = rest_do_request($req);
echo "REST /themes response: " . json_encode($resp->get_data()) . PHP_EOL;
echo "ThemeEngine after request: " . count(Blocky\Core\Tokens\ThemeEngine::getInstance()->allVariants()) . " variants" . PHP_EOL;
' --allow-root 2>&1
