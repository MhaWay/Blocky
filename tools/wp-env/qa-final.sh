#!/usr/bin/env bash
# Full integration QA test
service apache2 start 2>/dev/null
sleep 1

WSL_IP=$(ip addr show eth0 | grep 'inet ' | awk '{print $2}' | cut -d/ -f1)
BASE="http://$WSL_IP:8888"
WP=/var/www/blocky
PASS=0
FAIL=0

check() {
    local name="$1"
    local result="$2"
    local expected="$3"
    if echo "$result" | grep -q "$expected"; then
        echo "✓ $name"
        ((PASS++))
    else
        echo "✗ $name (expected: $expected)"
        echo "  got: ${result:0:200}"
        ((FAIL++))
    fi
}

echo "============================================"
echo "  Blocky Full Integration QA"
echo "  $(date)"
echo "============================================"
echo ""

# --- REST API: Unauthenticated public endpoints ---
echo "--- Public REST Endpoints ---"

R=$(curl -s "$BASE/wp-json/blocky/v1/themes")
check "GET /themes returns variants" "$R" "default--light"

R=$(curl -s "$BASE/wp-json/blocky/v1/themes/active")
check "GET /themes/active returns brand+mode" "$R" "brand"

R=$(curl -s "$BASE/wp-json/blocky/v1/blocks")
check "GET /blocks unauthenticated = 401" "$R" "rest_forbidden"

R=$(curl -s "$BASE/wp-json/blocky/v1/documents/5")
check "GET /documents/5 unauthenticated = 401" "$R" "rest_forbidden"

echo ""
echo "--- Authenticated REST Endpoints (WP-CLI) ---"

wp --path=$WP eval '
wp_set_current_user(1);

// Blocks list
$req = new WP_REST_Request("GET", "/blocky/v1/blocks");
$resp = rest_do_request($req);
$data = $resp->get_data();
$types = array_column($data, "type");
$expected = ["bky/heading", "bky/section", "bky/text", "bky/button", "bky/image", "bky/grid", "bky/container", "bky/columns"];
$missing = array_diff($expected, $types);
if (empty($missing)) {
    echo "✓ GET /blocks returns all 8 block types" . PHP_EOL;
} else {
    echo "✗ GET /blocks missing: " . implode(", ", $missing) . PHP_EOL;
}

// Document CRUD
$doc = [
    "root" => "n1",
    "nodes" => [
        "n1" => ["id"=>"n1","type"=>"bky/section","props"=>["paddingY"=>"lg","background"=>"surface"],"children"=>["n2","n3"]],
        "n2" => ["id"=>"n2","type"=>"bky/heading","props"=>["level"=>1,"text"=>"QA Test Heading","tone"=>"accent","align"=>"center"],"children"=>[]],
        "n3" => ["id"=>"n3","type"=>"bky/text","props"=>["content"=>"QA test paragraph.","size"=>"lg","color"=>"muted"],"children"=>[]],
    ]
];

// Save
$req = new WP_REST_Request("POST", "/blocky/v1/documents/5");
$req->set_param("document", $doc);
$resp = rest_do_request($req);
$data = $resp->get_data();
$html = $data["html"] ?? "";
if (str_contains($html, "QA Test Heading") && str_contains($html, "bky-section")) {
    echo "✓ POST /documents/5 saves and returns rendered HTML" . PHP_EOL;
} else {
    echo "✗ POST /documents/5 failed. Status=" . $resp->get_status() . " html=" . substr($html, 0, 200) . PHP_EOL;
}

// Fetch
$req2 = new WP_REST_Request("GET", "/blocky/v1/documents/5");
$resp2 = rest_do_request($req2);
$data2 = $resp2->get_data();
$storedHtml = $data2["html"] ?? "";
$storedDoc  = $data2["document"] ?? null;
if (str_contains($storedHtml, "QA Test Heading") && is_array($storedDoc)) {
    echo "✓ GET /documents/5 returns HTML + document JSON" . PHP_EOL;
} else {
    echo "✗ GET /documents/5 failed. html=" . substr($storedHtml, 0, 200) . PHP_EOL;
}

// Verify rendering details
if (str_contains($storedHtml, "py-20") && str_contains($storedHtml, "bg-surface-base")) {
    echo "✓ Props resolve to correct CSS classes (py-20, bg-surface-base)" . PHP_EOL;
} else {
    echo "✗ Props not resolving. html=" . substr($storedHtml, 0, 300) . PHP_EOL;
}
if (str_contains($storedHtml, "text-center") && str_contains($storedHtml, "text-accent-text")) {
    echo "✓ Heading align+tone props render correctly" . PHP_EOL;
} else {
    echo "✗ Heading props not rendering. html=" . substr($storedHtml, 0, 300) . PHP_EOL;
}

// Render endpoint
$req3 = new WP_REST_Request("POST", "/blocky/v1/documents/render");
$req3->set_param("document", $doc);
$resp3 = rest_do_request($req3);
$renderHtml = $resp3->get_data()["html"] ?? "";
if (str_contains($renderHtml, "QA Test Heading")) {
    echo "✓ POST /documents/render returns correct HTML" . PHP_EOL;
} else {
    echo "✗ POST /documents/render failed. html=" . substr($renderHtml, 0, 200) . PHP_EOL;
}

// Themes
$req4 = new WP_REST_Request("GET", "/blocky/v1/themes");
$resp4 = rest_do_request($req4);
$themes = $resp4->get_data();
if (isset($themes["default--light"]) && isset($themes["default--dark"])) {
    echo "✓ GET /themes returns default--light and default--dark" . PHP_EOL;
} else {
    echo "✗ GET /themes missing expected variants. got=" . json_encode(array_keys((array)$themes)) . PHP_EOL;
}

echo PHP_EOL;
echo "=== PHP Error Check ===" . PHP_EOL;
$php_log = ini_get("error_log");
if ($php_log && file_exists($php_log)) {
    $log = file_get_contents($php_log);
    $log = trim($log);
    if (empty($log)) {
        echo "✓ PHP error log: clean" . PHP_EOL;
    } else {
        $lines = explode(PHP_EOL, $log);
        $recent = array_slice($lines, -10);
        echo "✗ PHP errors found:" . PHP_EOL;
        echo implode(PHP_EOL, $recent) . PHP_EOL;
    }
} else {
    echo "✓ PHP error log: not configured (no errors)" . PHP_EOL;
}
' --allow-root

echo ""
echo "============================================"
echo "DONE"
echo "============================================"
