#!/usr/bin/env bash
# One-shot site bootstrap for the local Blocky stack.
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

# Pretty permalinks + Authorization passthrough for bearer keys.
WP option update permalink_structure "/%postname%/"
WP eval "flush_rewrite_rules();" >/dev/null 2>&1 || true
HTP=$(printf "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%%{HTTP:Authorization}]\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %%{REQUEST_FILENAME} !-f\nRewriteCond %%{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n")
docker compose exec -T wp sh -c "printf \"%s\\n\" \"$HTP\" > /var/www/html/.htaccess; chown www-data:www-data /var/www/html/.htaccess"

# Debug off, sane dev values.
WP config set WP_DEBUG_DISPLAY false --raw 2>/dev/null || true

PAGE_ID=$(WP post list --post_type=page --title=Home --field=ID 2>/dev/null | head -1 || true)
if [ -z "$PAGE_ID" ]; then
  PAGE_ID=$(WP post create --post_type=page --post_title=Home --post_status=publish --porcelain)
fi
WP option update blocky_home_page_id "$PAGE_ID"
echo "Ready. Admin http://127.0.0.1:8888/wp-admin (blocky/blocky) home=$PAGE_ID"
