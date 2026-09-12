#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
WP() { docker compose exec -T wp wp --allow-root --color=never "$@"; }
docker compose up -d >/dev/null 2>&1
for i in $(seq 1 90); do
  if curl -sf -o /dev/null http://127.0.0.1:8888/wp-login.php; then break; fi
  sleep 2
done
if ! WP core is-installed 2>/dev/null; then
  WP core install --url="http://127.0.0.1:8888" --title="Blocky Dev" \
    --admin_user="blocky" --admin_password="blocky" --admin_email="dev@example.test" --skip-email
fi
WP plugin activate blocky-core blocky-builder 2>/dev/null || true
WP theme activate blocky-theme
PAGE_ID=$(WP post list --post_type=page --title=Home --field=ID 2>/dev/null | head -1 || true)
if [ -z "$PAGE_ID" ]; then
  PAGE_ID=$(WP post create --post_type=page --post_title=Home --post_status=publish --porcelain)
fi
WP option update blocky_home_page_id "$PAGE_ID"
echo "Ready. Admin http://127.0.0.1:8888/wp-admin blocky/blocky page=$PAGE_ID"
