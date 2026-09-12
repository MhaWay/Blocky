#!/usr/bin/env bash
# QA test script — runs from inside WSL against localhost:8888
# Usage: bash qa-test.sh
set -e

WP=/var/www/blocky
BASE=http://localhost:8888

echo "================================================================"
echo " Blocky QA Test Suite"
echo "================================================================"

# ── 1. Plugin status ────────────────────────────────────────────────
echo ""
echo "=== 1. Plugin & Theme Status ==="
wp --path=$WP plugin list --allow-root --fields=name,status,version
wp --path=$WP theme list --allow-root --fields=name,status

# ── 2. Create test post ─────────────────────────────────────────────
echo ""
echo "=== 2. Create test post ==="
POST_ID=$(wp --path=$WP post create \
  --post_title="Blocky QA Test" \
  --post_status=publish \
  --post_type=page \
  --allow-root \
  --porcelain)
echo "Created post ID: $POST_ID"

# ── 3. Get app nonce via WP-CLI eval ────────────────────────────────
echo ""
echo "=== 3. Generate WP REST nonce ==="
NONCE=$(wp --path=$WP eval 'echo wp_create_nonce("wp_rest");' --allow-root)
echo "Nonce: $NONCE"

# ── 4. Get admin cookie via WP-CLI ──────────────────────────────────
echo ""
echo "=== 4. Login via curl and capture cookie ==="
COOKIE_JAR=/tmp/wp_cookies.txt
curl -s -c $COOKIE_JAR -d "log=admin&pwd=admin123&wp-submit=Log+In&redirect_to=%2Fwp-admin%2F&testcookie=1" \
  -b "wordpress_test_cookie=WP+Cookie+check" \
  "$BASE/wp-login.php" -o /dev/null -w "Login HTTP: %{http_code}\n"
echo "Cookies saved to $COOKIE_JAR"

# ── 5. REST blocks list (authenticated) ─────────────────────────────
echo ""
echo "=== 5. REST /blocky/v1/blocks (auth) ==="
BLOCKS=$(curl -s -b $COOKIE_JAR "$BASE/wp-json/blocky/v1/blocks" -H "X-WP-Nonce: $NONCE")
echo "$BLOCKS" | head -200

# ── 6. REST themes list ─────────────────────────────────────────────
echo ""
echo "=== 6. REST /blocky/v1/themes ==="
curl -s "$BASE/wp-json/blocky/v1/themes"
echo ""

# ── 7. REST document save ────────────────────────────────────────────
echo ""
echo "=== 7. REST POST /blocky/v1/documents/$POST_ID ==="
DOC_PAYLOAD='{"root":"node-1","nodes":{"node-1":{"id":"node-1","type":"bky/section","props":{"padding":"md"},"children":["node-2"]},"node-2":{"id":"node-2","type":"bky/heading","props":{"level":1,"text":"Hello Blocky QA"},"children":[]}}}'
SAVE_RESP=$(curl -s -b $COOKIE_JAR -X POST "$BASE/wp-json/blocky/v1/documents/$POST_ID" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d "{\"document\": $DOC_PAYLOAD}")
echo "Save response: $SAVE_RESP"

# ── 8. REST document fetch ───────────────────────────────────────────
echo ""
echo "=== 8. REST GET /blocky/v1/documents/$POST_ID ==="
GET_RESP=$(curl -s -b $COOKIE_JAR "$BASE/wp-json/blocky/v1/documents/$POST_ID" \
  -H "X-WP-Nonce: $NONCE")
echo "Fetch response: $GET_RESP" | head -300

# ── 9. REST render preview ───────────────────────────────────────────
echo ""
echo "=== 9. REST POST /blocky/v1/documents/render ==="
RENDER_RESP=$(curl -s -b $COOKIE_JAR -X POST "$BASE/wp-json/blocky/v1/documents/render" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $NONCE" \
  -d "{\"document\": $DOC_PAYLOAD, \"post_id\": $POST_ID}")
echo "Render response: $RENDER_RESP"

# ── 10. Frontend page render ─────────────────────────────────────────
echo ""
echo "=== 10. Frontend page render ==="
PAGE_URL=$(wp --path=$WP post get $POST_ID --field=guid --allow-root)
echo "Page URL: $PAGE_URL"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$PAGE_URL")
echo "HTTP status: $HTTP_CODE"

# ── 11. PHP error log check ──────────────────────────────────────────
echo ""
echo "=== 11. PHP/Apache error log (last 20 lines) ==="
tail -20 /var/log/apache2/blocky-error.log 2>/dev/null || echo "(no error log)"

echo ""
echo "================================================================"
echo " QA Complete — post ID $POST_ID"
echo "================================================================"
