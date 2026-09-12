#!/usr/bin/env bash
# Full block-type render QA test
service apache2 start 2>/dev/null
sleep 1

wp --path=/var/www/blocky eval '
wp_set_current_user(1);

// Test document with all block types
$doc = [
    "root" => "root",
    "nodes" => [
        "root" => [
            "id" => "root", "type" => "bky/section",
            "props" => ["paddingY" => "lg", "background" => "surface"],
            "children" => ["container"]
        ],
        "container" => [
            "id" => "container", "type" => "bky/container",
            "props" => ["maxWidth" => "lg", "align" => "center"],
            "children" => ["h1", "txt", "grid", "cols", "btn"]
        ],
        "h1" => [
            "id" => "h1", "type" => "bky/heading",
            "props" => ["level" => 1, "text" => "Welcome to Blocky", "tone" => "accent", "align" => "center"],
            "children" => []
        ],
        "txt" => [
            "id" => "txt", "type" => "bky/text",
            "props" => ["content" => "Build beautiful pages visually.", "size" => "lg", "color" => "muted"],
            "children" => []
        ],
        "grid" => [
            "id" => "grid", "type" => "bky/grid",
            "props" => ["columns" => 3, "gap" => "lg"],
            "children" => ["g1", "g2", "g3"]
        ],
        "g1" => [
            "id" => "g1", "type" => "bky/heading",
            "props" => ["level" => 3, "text" => "Feature One"],
            "children" => []
        ],
        "g2" => [
            "id" => "g2", "type" => "bky/heading",
            "props" => ["level" => 3, "text" => "Feature Two"],
            "children" => []
        ],
        "g3" => [
            "id" => "g3", "type" => "bky/heading",
            "props" => ["level" => 3, "text" => "Feature Three"],
            "children" => []
        ],
        "cols" => [
            "id" => "cols", "type" => "bky/columns",
            "props" => ["count" => 2, "gap" => "base", "stackAt" => "md"],
            "slots" => [
                "column-1" => ["col1txt"],
                "column-2" => ["col2txt"]
            ]
        ],
        "col1txt" => [
            "id" => "col1txt", "type" => "bky/text",
            "props" => ["content" => "Column one content."],
            "children" => []
        ],
        "col2txt" => [
            "id" => "col2txt", "type" => "bky/text",
            "props" => ["content" => "Column two content."],
            "children" => []
        ],
        "btn" => [
            "id" => "btn", "type" => "bky/button",
            "props" => ["label" => "Get Started", "href" => "/start", "variant" => "primary", "size" => "lg"],
            "children" => []
        ],
    ]
];

$req = new WP_REST_Request("POST", "/blocky/v1/documents/render");
$req->set_param("document", $doc);
$resp = rest_do_request($req);
$data = $resp->get_data();

if (is_wp_error($data)) {
    echo "ERROR: " . $data->get_error_message() . PHP_EOL;
} else {
    $html = $data["html"] ?? "";
    echo "HTTP " . $resp->get_status() . PHP_EOL;
    echo "HTML length: " . strlen($html) . " bytes" . PHP_EOL;
    echo PHP_EOL;
    // Check each block type appears in output
    $checks = [
        "bky-section"   => "bky-section",
        "container"     => "container",
        "h1 heading"    => "Welcome to Blocky",
        "text block"    => "Build beautiful pages",
        "grid"          => "bky-grid",
        "columns"       => "bky-columns",
        "column-1"      => "Column one content",
        "column-2"      => "Column two content",
        "button"        => "Get Started",
        "button href"   => "href=\"/start\"",
    ];
    foreach ($checks as $name => $needle) {
        $found = str_contains($html, $needle) ? "✓" : "✗ MISSING";
        echo "$found  $name: $needle" . PHP_EOL;
    }
    echo PHP_EOL;
    echo "Full HTML:" . PHP_EOL;
    echo $html . PHP_EOL;
}
' --allow-root
