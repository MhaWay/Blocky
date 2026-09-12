#!/usr/bin/env bash
service apache2 start 2>/dev/null
sleep 1

echo "=== Themes endpoint (authenticated) ==="
wp --path=/var/www/blocky eval '
wp_set_current_user(1);
$req = new WP_REST_Request("GET", "/blocky/v1/themes");
$resp = rest_do_request($req);
echo json_encode($resp->get_data(), JSON_PRETTY_PRINT) . PHP_EOL;
' --allow-root

echo ""
echo "=== ThemeEngine direct ==="
wp --path=/var/www/blocky eval '
$variants = Blocky\Core\Tokens\ThemeEngine::getInstance()->allVariants();
echo "Count: " . count($variants) . PHP_EOL;
foreach ($variants as $id => $v) {
    echo "  - $id (brand={$v->brand}, mode={$v->mode})" . PHP_EOL;
}
' --allow-root

echo ""
echo "=== PHP error log (last 20 lines) ==="
tail -20 /var/log/apache2/error.log 2>/dev/null || echo "(no apache error log)"
