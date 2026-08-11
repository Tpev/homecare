#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/var/www/homecare"
VOICE_AGENT_DIR="$APP_DIR/voice-agent"
VOICE_AGENT_BIN_DIR="$VOICE_AGENT_DIR/bin"
VOICE_AGENT_BIN="$VOICE_AGENT_BIN_DIR/voice-agent"
BRANCH="master"
PHP_BIN="php"
COMPOSER_BIN="composer"
NPM_BIN="npm"
GO_BIN="/usr/local/go/bin/go"
FPM_SERVICE="php8.3-fpm"
VOICE_AGENT_SERVICE="homecare-voice-agent"
LOCK_FILE="/tmp/homecare-deploy.lock"
APP_HEALTH_URL="http://127.0.0.1/"
VOICE_HEALTH_URL="http://127.0.0.1:8088/healthz"

if [[ ! -x "$GO_BIN" ]]; then
  GO_BIN="$(command -v go || true)"
fi

if [[ -z "$GO_BIN" || ! -x "$GO_BIN" ]]; then
  echo "Go binary not found. Install Go or update GO_BIN in deploy.sh."
  exit 1
fi

exec 9>"$LOCK_FILE"
flock -n 9 || { echo "Another deploy is already running."; exit 1; }

cd "$APP_DIR"
umask 002

cleanup() {
  $PHP_BIN artisan up || true
}
trap cleanup EXIT

echo "Starting HomeCare deployment..."

echo "Preparing writable directories..."
sudo mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache "$VOICE_AGENT_BIN_DIR"
sudo touch storage/logs/laravel.log
sudo chown -R ubuntu:www-data storage bootstrap/cache "$VOICE_AGENT_BIN_DIR"
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
sudo find "$VOICE_AGENT_BIN_DIR" -type d -exec chmod 775 {} \;
sudo find "$VOICE_AGENT_BIN_DIR" -type f -exec chmod 775 {} \;

echo "Entering maintenance mode..."
$PHP_BIN artisan down --render="errors::503" --retry=60 || true

echo "Pulling latest code..."
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "Installing PHP dependencies..."
$COMPOSER_BIN install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "Installing Node dependencies..."
$NPM_BIN ci --no-audit --no-fund

echo "Building assets..."
$NPM_BIN run build

echo "Running migrations..."
$PHP_BIN artisan migrate --force

echo "Generating responsive caregiver photos..."
$PHP_BIN artisan caregiver-photos:generate-variants --no-interaction

echo "Clearing and caching Laravel..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "Refreshing permissions..."
sudo chown -R ubuntu:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;

echo "Restarting queue workers..."
$PHP_BIN artisan queue:restart || true
$PHP_BIN artisan horizon:terminate || true

echo "Restarting PHP-FPM..."
sudo systemctl restart "$FPM_SERVICE"

echo "Bringing Laravel app back up..."
$PHP_BIN artisan up
trap - EXIT

echo "Building voice-agent..."
cd "$VOICE_AGENT_DIR"
$GO_BIN build -o "$VOICE_AGENT_BIN" ./cmd/server
cd "$APP_DIR"

echo "Restarting voice-agent..."
sudo systemctl restart "$VOICE_AGENT_SERVICE"

echo "Reloading nginx..."
sudo nginx -t
sudo systemctl reload nginx

echo "Running health checks..."
curl -fsS "$APP_HEALTH_URL" >/dev/null
curl -fsS "$VOICE_HEALTH_URL" >/dev/null

echo "Deployment complete!"
