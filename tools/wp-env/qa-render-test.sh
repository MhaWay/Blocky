#!/usr/bin/env bash
# Test the render fix
service apache2 start 2>/dev/null
sleep 1

wp --path=/var/www/blocky eval '
wp_set_current_user(1);
$doc = [
    "root" => "n1",
    "nodes" => [
        "n1" => ["id" => "n1", "type" => "bky/section", "props" => ["paddingY" => "lg"], "children" => ["n2", "n3"]],
        "n2" => ["id" => "n2", "type" => "bky/heading", "props" => ["level" => 1, "text" => "Hello Blocky QA"], "children" => []],
        "n3" => ["id" => "n3", "type" => "bky/text", "props" => ["content" => "This is a test paragraph."], "children" => []],
    ]
];
$req = new WP_REST_Request("POST", "/blocky/v1/documents/render");
$req->set_param("document", $doc);
$resp = rest_do_request($req);
$data = $resp->get_data();
if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    echo "HTML: " . ($data["html"] ?? "(empty)") . PHP_EOL;
}
' --allow-root
