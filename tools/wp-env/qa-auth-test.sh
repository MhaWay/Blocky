#!/usr/bin/env bash
# Authenticated REST API QA test
set -e

WP=/var/www/blocky
BASE=http://localhost:8888

service apache2 start 2>/dev/null
sleep 1

echo "=== Creating Application Password ==="
APP_PASS=$(wp --path=$WP user application-password create 1 'QA-Test' --allow-root --porcelain 2>&1)
echo "App password created: ${APP_PASS:0:10}..."

CREDS="admin:$APP_PASS"

echo ""
echo "=== REST /blocky/v1/blocks (auth) ==="
BLOCKS=$(curl -s -u "$CREDS" "$BASE/wp-json/blocky/v1/blocks")
echo "$BLOCKS"

echo ""
echo "=== Save document to post 5 ==="
DOC='{"root":"n1","nodes":{"n1":{"id":"n1","type":"bky/section","props":{"padding":"md"},"children":["n2"]},"n2":{"id":"n2","type":"bky/heading","props":{"level":1,"text":"Hello Blocky QA"},"children":[]}}}'
SAVE=$(curl -s -u "$CREDS" -X POST "$BASE/wp-json/blocky/v1/documents/5" \
  -H "Content-Type: application/json" \
  --data-raw "{\"document\": $DOC}")
echo "$SAVE"

echo ""
echo "=== Fetch document from post 5 ==="
GET=$(curl -s -u "$CREDS" "$BASE/wp-json/blocky/v1/documents/5")
echo "$GET"

echo ""
echo "=== Render preview ==="
RENDER=$(curl -s -u "$CREDS" -X POST "$BASE/wp-json/blocky/v1/documents/render" \
  -H "Content-Type: application/json" \
  --data-raw "{\"document\": $DOC, \"post_id\": 5}")
echo "$RENDER"

echo ""
echo "=== Builder preview endpoint ==="
BPREVIEW=$(curl -s -u "$CREDS" -X POST "$BASE/wp-json/blocky/v1/builder/preview" \
  -H "Content-Type: application/json" \
  --data-raw "{\"document\": $DOC, \"postId\": 5}")
echo "$BPREVIEW"

echo ""
echo "=== Frontend page 5 ==="
PAGE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/blocky-qa-test/")
echo "HTTP: $PAGE_STATUS"

echo ""
echo "=== PHP error log ==="
tail -10 /var/log/apache2/blocky-error.log 2>/dev/null || echo "(empty)"

echo ""
echo "=== DONE ==="
