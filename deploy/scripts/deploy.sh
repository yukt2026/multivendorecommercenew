#!/usr/bin/env bash
# ==============================================================================
# OneStall Marketplace — Zero-Downtime Deployment Script
# Usage: ./deploy/scripts/deploy.sh [branch_or_tag]
# ==============================================================================

set -euo pipefail

# Configuration
APP_NAME="onestall"
DEPLOY_USER="www-data"
BASE_DIR="/var/www/multivendorecommerce"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_DIR="${BASE_DIR}/shared"
CURRENT_DIR="${BASE_DIR}/current"
TIMESTAMP="$(date +%Y%m%d%H%M%S)"
TARGET_RELEASE="${RELEASES_DIR}/${TIMESTAMP}"
BRANCH="${1:-main}"

echo "=========================================================="
echo "🚀 Starting OneStall Zero-Downtime Deployment: [${TIMESTAMP}]"
echo "Target Branch/Tag: ${BRANCH}"
echo "=========================================================="

# 1. Ensure required shared directories exist
echo "📁 Ensuring shared directories..."
mkdir -p "${SHARED_DIR}/storage/app/public"
mkdir -p "${SHARED_DIR}/storage/app/private"
mkdir -p "${SHARED_DIR}/storage/framework/cache/data"
mkdir -p "${SHARED_DIR}/storage/framework/sessions"
mkdir -p "${SHARED_DIR}/storage/framework/views"
mkdir -p "${SHARED_DIR}/storage/logs"
mkdir -p "${RELEASES_DIR}"

if [[ ! -f "${SHARED_DIR}/.env" ]]; then
    echo "❌ ERROR: Shared environment file (${SHARED_DIR}/.env) not found!"
    exit 1
fi

# 2. Clone latest code into new release directory
echo "📦 Cloning source repository into release: ${TARGET_RELEASE}..."
git clone --depth 1 --branch "${BRANCH}" https://github.com/your-org/multivendorecommerce.git "${TARGET_RELEASE}"

cd "${TARGET_RELEASE}"

# 3. Link Shared Environment & Storage
echo "🔗 Linking shared persistent storage and environment..."
rm -rf "${TARGET_RELEASE}/storage"
rm -f "${TARGET_RELEASE}/.env"

ln -sfn "${SHARED_DIR}/.env" "${TARGET_RELEASE}/.env"
ln -sfn "${SHARED_DIR}/storage" "${TARGET_RELEASE}/storage"

# 4. Install PHP Dependencies (Optimized, No-Dev)
echo "🐘 Installing Composer dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Build Frontend Production Assets
echo "🎨 Compiling Frontend Assets (Vite)..."
if command -v npm &> /dev/null; then
    npm ci --silent
    npm run build
fi

# 6. Execute Atomic Database Migrations
echo "🗄️ Running Database Migrations..."
php artisan migrate --force --no-interaction

# 7. Warm Application Caches
echo "⚡ Warming Production Caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Create Public Storage Symlink
php artisan storage:link || true

# 9. Atomic Symlink Switch
echo "🔄 Switching active symlink to new release..."
ln -sfn "${TARGET_RELEASE}" "${CURRENT_DIR}.tmp"
mv -Tf "${CURRENT_DIR}.tmp" "${CURRENT_DIR}"

# 10. Reload PHP-FPM (Clear OPcache)
echo "♻️ Reloading PHP-FPM..."
if sudo systemctl is-active --quiet php8.2-fpm; then
    sudo systemctl reload php8.2-fpm
fi

# 11. Gracefully Reload Horizon and Queue Workers
echo "📨 Gracefully reloading Horizon and Queue Workers..."
cd "${CURRENT_DIR}"
php artisan horizon:terminate || true
php artisan queue:restart || true

# 12. Restart Reverb WebSockets
echo "🔌 Restarting Reverb WebSocket processes..."
if sudo supervisorctl status onestall-reverb:* &> /dev/null; then
    sudo supervisorctl restart onestall-reverb:* || true
fi

# 13. Deep Health Check Verification
echo "🩺 Verifying application health check..."
sleep 2
HEALTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/healthz || echo "000")

if [[ "${HEALTH_RESPONSE}" == "200" ]]; then
    echo "✅ Health check PASSED (HTTP 200)!"
else
    echo "⚠️ WARNING: Health check returned HTTP ${HEALTH_RESPONSE}. Inspect /var/log/nginx/onestall_error.log."
fi

# 14. Clean Up Old Releases (Keep latest 5)
echo "🧹 Purging old releases..."
cd "${RELEASES_DIR}"
ls -1dt [0-9]* | tail -n +6 | xargs -r rm -rf

echo "=========================================================="
echo "🎉 Deployment successfully completed to: ${TARGET_RELEASE}"
echo "=========================================================="
