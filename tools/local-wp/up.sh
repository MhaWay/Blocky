#!/usr/bin/env bash
# Bring up a deterministic local WordPress for e2e + manual testing.
# Usage: tools/local-wp/up.sh   (then: tools/local-wp/init.sh once)
set -euo pipefail
cd "$(dirname "$0")"
docker compose up -d
echo "Waiting for WordPress…"
for i in $(seq 1 60); do
  if curl -sf -o /dev/null http://127.0.0.1:8888/wp-admin/install.php; then break; fi
  sleep 2
done
echo "WordPress is up on http://127.0.0.1:8888"
