#!/usr/bin/env bash
set -e

WP_DIR="/var/www/blocky"
WP_URL="http://localhost:8888"

echo "[1/6] Starting MariaDB..."
service mariadb start || true

echo "[2/6] Creating database..."
mysql -u root -e "CREATE DATABASE IF NOT EXISTS blocky_wp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
mysql -u root -e "CREATE USER IF NOT EXISTS 'blocky'@'localhost' IDENTIFIED BY 'blocky123';" 2>/dev/null || true
mysql -u root -e "GRANT ALL PRIVILEGES ON blocky_wp.* TO 'blocky'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || true
echo "  DB ready."

echo "[3/6] Installing WP-CLI..."
curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp
chmod +x /usr/local/bin/wp
wp --allow-root --version

echo "[4/6] Downloading WordPress..."
mkdir -p "$WP_DIR"
wp core download --path="$WP_DIR" --version=6.7 --locale=en_US --allow-root --quiet

echo "[5/6] Configuring WordPress..."
wp config create \
  --path="$WP_DIR" \
  --dbname=blocky_wp \
  --dbuser=blocky \
  --dbpass=blocky123 \
  --dbhost=localhost \
  --allow-root --quiet

wp config set BLOCKY_DEV true --raw --type=constant --path="$WP_DIR" --allow-root --quiet
wp config set BLOCKY_ASSET_URL 'http://localhost:5173' --type=constant --path="$WP_DIR" --allow-root --quiet

wp core install \
  --path="$WP_DIR" \
  --url="$WP_URL" \
  --title="Blocky Dev" \
  --admin_user=admin \
  --admin_password=admin123 \
  --admin_email=admin@blocky.local \
  --skip-email \
  --allow-root

echo "[6/6] Linking plugins + theme..."
PROJ="/mnt/d/SourceProject/Blocky"

mkdir -p "$WP_DIR/wp-content/mu-plugins"
ln -sfn "$PROJ/tools/wp-env/dev-helpers.php" "$WP_DIR/wp-content/mu-plugins/dev-helpers.php"

# Symlink theme
ln -sfn "$PROJ/packages/theme" "$WP_DIR/wp-content/themes/blocky"

# Symlink plugins  
ln -sfn "$PROJ/packages/core-plugin"    "$WP_DIR/wp-content/plugins/blocky-core"
ln -sfn "$PROJ/packages/builder-plugin" "$WP_DIR/wp-content/plugins/blocky-builder"

# Activate theme + plugins
wp --path="$WP_DIR" theme activate blocky --allow-root
wp --path="$WP_DIR" plugin activate blocky-core --allow-root
wp --path="$WP_DIR" plugin activate blocky-builder --allow-root

# Enable pretty permalinks
wp --path="$WP_DIR" rewrite structure '/%postname%/' --allow-root

echo ""
echo "=============================="
echo " WordPress ready at $WP_URL"
echo " Admin: $WP_URL/wp-admin"
echo " user: admin / admin123"
echo "=============================="
