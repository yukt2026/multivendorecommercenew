#!/usr/bin/env bash
# ==============================================================================
# OneStall Marketplace — Automated Daily Database Backup Script
# Usage: ./deploy/scripts/backup-db.sh
# ==============================================================================

set -euo pipefail

# Configuration
ENV_FILE="/var/www/multivendorecommerce/shared/.env"
BACKUP_DIR="/var/backups/onestall/db"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DATE_PREFIX="$(date +%Y%m%d)"
BACKUP_FILE="${BACKUP_DIR}/onestall_prod_${TIMESTAMP}.sql.gz"
S3_BUCKET="${BACKUP_S3_BUCKET:-onestall-db-backups}"
RETENTION_DAYS=14

mkdir -p "${BACKUP_DIR}"

# Extract DB credentials from .env
if [[ -f "${ENV_FILE}" ]]; then
    DB_HOST=$(grep -E '^DB_HOST=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_PORT=$(grep -E '^DB_PORT=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_NAME=$(grep -E '^DB_DATABASE=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_USER=$(grep -E '^DB_USERNAME=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    DB_PASS=$(grep -E '^DB_PASSWORD=' "${ENV_FILE}" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
else
    echo "❌ ERROR: Environment file not found at ${ENV_FILE}"
    exit 1
fi

echo "=========================================================="
echo "💾 Starting Database Backup: ${DB_NAME} at ${TIMESTAMP}"
echo "=========================================================="

# 1. Execute consistent mysqldump
export MYSQL_PWD="${DB_PASS}"
mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --hex-blob \
    --default-character-set=utf8mb4 \
    "${DB_NAME}" | gzip -9 > "${BACKUP_FILE}"
unset MYSQL_PWD

FILE_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "✅ Compressed backup generated: ${BACKUP_FILE} (${FILE_SIZE})"

# 2. Generate SHA256 Checksum
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# 3. Upload to Cloud Storage (AWS S3 or Cloudflare R2 if AWS CLI configured)
if command -v aws &> /dev/null && [[ -n "${S3_BUCKET}" ]]; then
    echo "☁️ Uploading backup to S3 bucket: s3://${S3_BUCKET}/${DATE_PREFIX}/..."
    aws s3 cp "${BACKUP_FILE}" "s3://${S3_BUCKET}/${DATE_PREFIX}/" --quiet
    aws s3 cp "${BACKUP_FILE}.sha256" "s3://${S3_BUCKET}/${DATE_PREFIX}/" --quiet
    echo "✅ S3 Upload completed successfully."
fi

# 4. Prune Local Backups Older Than Retention Period
echo "🧹 Pruning local backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "onestall_prod_*.sql.gz*" -type f -mtime "+${RETENTION_DAYS}" -delete

echo "=========================================================="
echo "🎉 Backup completed successfully: ${BACKUP_FILE}"
echo "=========================================================="
