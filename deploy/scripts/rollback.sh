#!/usr/bin/env bash
# ==============================================================================
# OneStall Marketplace — Instant Zero-Downtime Rollback Script
# Usage: ./deploy/scripts/rollback.sh
# ==============================================================================

set -euo pipefail

BASE_DIR="/var/www/multivendorecommerce"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_DIR="${BASE_DIR}/current"

echo "=========================================================="
echo "⏪ Initiating Emergency Rollback..."
echo "=========================================================="

cd "${RELEASES_DIR}"

# Fetch the previous release directory
PREVIOUS_RELEASE=$(ls -1dt [0-9]* | sed -n '2p')

if [[ -z "${PREVIOUS_RELEASE}" ]]; then
    echo "❌ ERROR: No previous release found to rollback to!"
    exit 1
fi

TARGET_PATH="${RELEASES_DIR}/${PREVIOUS_RELEASE}"
echo "🎯 Rolling back to previous release: ${TARGET_PATH}"

# Atomic Symlink Switch
ln -sfn "${TARGET_PATH}" "${CURRENT_DIR}.tmp"
mv -Tf "${CURRENT_DIR}.tmp" "${CURRENT_DIR}"

# Reload PHP-FPM and Workers
echo "♻️ Reloading services..."
sudo systemctl reload php8.2-fpm || true
cd "${CURRENT_DIR}"
php artisan horizon:terminate || true
php artisan queue:restart || true

if sudo supervisorctl status onestall-reverb:* &> /dev/null; then
    sudo supervisorctl restart onestall-reverb:* || true
fi

# Verify Health
HEALTH_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/healthz || echo "000")
echo "🩺 Health Check Status: HTTP ${HEALTH_CODE}"

echo "=========================================================="
echo "✅ Rollback successfully finished to: ${PREVIOUS_RELEASE}"
echo "=========================================================="
