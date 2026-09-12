#!/usr/bin/env bash
service apache2 start 2>/dev/null
sleep 1

WSL_IP=$(ip addr show eth0 | grep 'inet ' | awk '{print $2}' | cut -d/ -f1)
echo "WSL IP: $WSL_IP"

echo "=== REST /blocky/v1/themes (public) ==="
curl -s "http://$WSL_IP:8888/wp-json/blocky/v1/themes" | python3 -m json.tool 2>/dev/null || curl -s "http://$WSL_IP:8888/wp-json/blocky/v1/themes"

echo ""
echo "=== REST /blocky/v1/themes/active (public) ==="
curl -s "http://$WSL_IP:8888/wp-json/blocky/v1/themes/active" | python3 -m json.tool 2>/dev/null || curl -s "http://$WSL_IP:8888/wp-json/blocky/v1/themes/active"
