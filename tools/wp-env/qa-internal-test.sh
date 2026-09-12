#!/usr/bin/env bash
# Internal REST API QA using WP-CLI eval (no HTTP auth needed)
set -e

WP=/var/www/blocky
BASE=http://localhost:8888

service apache2 start 2>/dev/null
sleep 1

echo "=== REST /blocky/v1/blocks ==="
wp --path=$WP eval '
wp_set_current_user(1);
$req = new WP_REST_Request("GET", "/blocky/v1/blocks");
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
}
' --allow-root

echo ""
echo "=== REST /blocky/v1/themes ==="
wp --path=$WP eval '
$req = new WP_REST_Request("GET", "/blocky/v1/themes");
$resp = rest_do_request($req);
echo json_encode($resp->get_data(), JSON_PRETTY_PRINT) . PHP_EOL;
' --allow-root

echo ""
echo "=== REST POST /blocky/v1/documents/5 ==="
wp --path=$WP eval '
wp_set_current_user(1);
$doc = [
    "root" => "n1",
    "nodes" => [
        "n1" => ["id" => "n1", "type" => "bky/section", "props" => ["padding" => "md"], "children" => ["n2"]],
        "n2" => ["id" => "n2", "type" => "bky/heading", "props" => ["level" => 1, "text" => "Hello Blocky QA"], "children" => []],
    ]
];
$req = new WP_REST_Request("POST", "/blocky/v1/documents/5");
$req->set_param("document", $doc);
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
}
' --allow-root

echo ""
echo "=== REST GET /blocky/v1/documents/5 ==="
wp --path=$WP eval '
wp_set_current_user(1);
$req = new WP_REST_Request("GET", "/blocky/v1/documents/5");
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    // Print document and html snippet
    if (isset($data["html"])) {
        echo "HTML (first 300 chars): " . substr($data["html"], 0, 300) . PHP_EOL;
    }
    if (isset($data["document"])) {
        echo "Document: " . json_encode($data["document"]) . PHP_EOL;
    }
}
' --allow-root

echo ""
echo "=== REST POST /blocky/v1/documents/render ==="
wp --path=$WP eval '
wp_set_current_user(1);
$doc = [
    "root" => "n1",
    "nodes" => [
        "n1" => ["id" => "n1", "type" => "bky/section", "props" => ["padding" => "md"], "children" => ["n2"]],
        "n2" => ["id" => "n2", "type" => "bky/heading", "props" => ["level" => 1, "text" => "Hello Blocky QA"], "children" => []],
    ]
];
$req = new WP_REST_Request("POST", "/blocky/v1/documents/render");
$req->set_param("document", $doc);
$req->set_param("post_id", 5);
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    if (isset($data["html"])) {
        echo "HTML: " . $data["html"] . PHP_EOL;
    } else {
        echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
' --allow-root

echo ""
echo "=== Builder preview endpoint ==="
wp --path=$WP eval '
wp_set_current_user(1);
$doc = [
    "root" => "n1",
    "nodes" => [
        "n1" => ["id" => "n1", "type" => "bky/section", "props" => ["padding" => "md"], "children" => ["n2"]],
        "n2" => ["id" => "n2", "type" => "bky/heading", "props" => ["level" => 1, "text" => "Hello Blocky QA"], "children" => []],
    ]
];
$req = new WP_REST_Request("POST", "/blocky/v1/builder/preview");
$req->set_param("document", $doc);
$req->set_param("postId", 5);
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    if (isset($data["html"])) {
        echo "HTML: " . $data["html"] . PHP_EOL;
    } else {
        echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
' --allow-root

echo ""
echo "=== PHP error log ==="
tail -15 /var/log/apache2/blocky-error.log 2>/dev/null || echo "(no errors)"
tail -15 /var/log/php8.2-fpm.log 2>/dev/null || true

echo ""
echo "=== DONE ==="
