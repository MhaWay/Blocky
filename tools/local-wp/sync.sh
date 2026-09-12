#!/usr/bin/env bash
# Copy built plugin/theme trees into the running wp container (no bind mounts).
set -euo pipefail
cd "$(dirname "$0")/../.."
C=local-wp-wp-1
docker ps --format "{{.Names}}" | grep -q "^${C}$" || { echo "container ${C} not running"; exit 1; }

docker cp tools/local-wp/bin/wp ${C}:/usr/local/bin/wp
docker exec "$C" sh -c "chmod 755 /usr/local/bin/wp || true"

echo "sync core-plugin ->"
docker exec "$C" sh -c "rm -rf /tmp/core && mkdir -p /tmp/core /var/www/html/wp-content/plugins/blocky-core"
tar -C packages/core-plugin -cf - --exclude node_modules --exclude .git . | docker exec -i "$C" tar -C /tmp/core -xf -
docker exec "$C" sh -c "cp -a /tmp/core/. /var/www/html/wp-content/plugins/blocky-core/ && rm -rf /tmp/core"

echo "sync builder-plugin ->"
tar -C packages/builder-plugin -cf - --exclude node_modules --exclude .git . | docker exec -i "$C" sh -c "rm -rf /tmp/bld && mkdir -p /tmp/bld /var/www/html/wp-content/plugins/blocky-builder && tar -C /tmp/bld -xf - && cp -a /tmp/bld/. /var/www/html/wp-content/plugins/blocky-builder/ && rm -rf /tmp/bld"

echo "sync theme ->"
tar -C packages/theme -cf - --exclude node_modules --exclude .git . | docker exec -i "$C" sh -c "rm -rf /tmp/tnm && mkdir -p /tmp/tnm /var/www/html/wp-content/themes/blocky-theme && tar -C /tmp/tnm -xf - && cp -a /tmp/tnm/. /var/www/html/wp-content/themes/blocky-theme/ && rm -rf /tmp/tnm"

echo "fixing perms"
docker exec "$C" sh -c "chmod -R a+rX /var/www/html/wp-content/plugins /var/www/html/wp-content/themes" 2>/dev/null || true

echo "flushing opcache"
docker compose restart wp >/dev/null 2>&1

echo "synced"
