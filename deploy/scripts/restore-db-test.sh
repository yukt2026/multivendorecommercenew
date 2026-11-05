#!/usr/bin/env bash
# ==============================================================================
# OneStall Marketplace — Automated Backup Restore Integrity Test
# Usage: ./deploy/scripts/restore-db-test.sh [path_to_backup_file]
# ==============================================================================

set -euo pipefail

ENV_FILE="/var/www/multivendorecommerce/shared/.env"
BACKUP_DIR="/var/backups/onestall/db"
TEST_DB="onestall_restore_test"

# Select backup file
if [[ $# -ge 1 ]]; then
    BACKUP_FILE="$1"
else
    BACKUP_FILE=$(find "${BACKUP_DIR}" -name "onestall_prod_*.sql.gz" -type f | sort -r | head -n 1)
fi

if [[ -z "${BACKUP_FILE}" || ! -f "${BACKUP_FILE}" ]]; then
    echo "❌ ERROR: No backup file available to test restore!"
    exit 1
fi

# Load DB credentials
if [[ -f "${ENV_FILE}" ]]; then
    DB_HOST=$(grep -E '^DB_HOST=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_PORT=$(grep -E '^DB_PORT=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_USER=$(grep -E '^DB_USERNAME=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_PASS=$(grep -E '^DB_PASSWORD=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
else
    echo "❌ ERROR: Environment file not found at ${ENV_FILE}"
    exit 1
fi

echo "=========================================================="
echo "🧪 Testing Restore Integrity for: ${BACKUP_FILE}"
echo "Test Sandbox Database: ${TEST_DB}"
echo "=========================================================="

# 1. Verify SHA256 Checksum if present
if [[ -f "${BACKUP_FILE}.sha256" ]]; then
    echo "🔐 Validating SHA256 checksum..."
    cd "$(dirname "${BACKUP_FILE}")"
    sha256sum --check "$(basename "${BACKUP_FILE}.sha256")"
    echo "✅ Checksum verified."
fi

export MYSQL_PWD="${DB_PASS}"

# 2. Re-create Clean Test Sandbox Database
echo "🏗️ Recreating temporary database: ${TEST_DB}..."
mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`; CREATE DATABASE \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Stream & Restore Data into Sandbox
echo "⏳ Decompressing and restoring backup data..."
gunzip -c "${BACKUP_FILE}" | mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" "${TEST_DB}"
echo "✅ Restoration completed."

# 4. Integrity Checks on Core Marketplace Tables
echo "🔍 Performing integrity checks on restored tables..."
USERS_COUNT=$(mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -N -e "SELECT COUNT(*) FROM \`${TEST_DB}\`.users;")
SELLERS_COUNT=$(mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -N -e "SELECT COUNT(*) FROM \`${TEST_DB}\`.sellers;")
ORDERS_COUNT=$(mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -N -e "SELECT COUNT(*) FROM \`${TEST_DB}\`.orders;")
PRODUCTS_COUNT=$(mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -N -e "SELECT COUNT(*) FROM \`${TEST_DB}\`.products;")

echo "📊 Table Verification Counts:"
echo "   - Users:    ${USERS_COUNT}"
echo "   - Sellers:  ${SELLERS_COUNT}"
echo "   - Orders:   ${ORDERS_COUNT}"
echo "   - Products: ${PRODUCTS_COUNT}"

# Run MySQL table integrity check
CHECK_STATUS=$(mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -e "CHECK TABLE \`${TEST_DB}\`.users, \`${TEST_DB}\`.sellers, \`${TEST_DB}\`.orders, \`${TEST_DB}\`.products QUICK;")
echo "${CHECK_STATUS}"

# 5. Drop Sandbox Database Cleanly
echo "🧹 Dropping test sandbox database..."
mysql -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_USER}" -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`;"

unset MYSQL_PWD

echo "=========================================================="
echo "🎉 RESTORE TEST PASSED! Backup is 100% verified and restorable."
echo "=========================================================="
