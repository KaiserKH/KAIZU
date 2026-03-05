#!/bin/bash
set -e

# ── Wait for MySQL to be ready ────────────────────────────────────────────────
echo "[entrypoint] Waiting for database at ${DB_HOST:-localhost}…"
MAX_TRIES=30
TRIES=0
until mysql -h"${DB_HOST:-localhost}" -u"${DB_USER:-root}" -p"${DB_PASS:-}" \
      -e "SELECT 1" > /dev/null 2>&1; do
    TRIES=$((TRIES+1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "[entrypoint] ERROR: Could not connect to database after ${MAX_TRIES} attempts."
        exit 1
    fi
    echo "[entrypoint] Attempt $TRIES/$MAX_TRIES – retrying in 2s…"
    sleep 2
done
echo "[entrypoint] Database is reachable."

# ── Auto-initialise database if empty ────────────────────────────────────────
DB_NAME="${DB_NAME:-shopphp}"
TABLE_COUNT=$(mysql -h"${DB_HOST:-localhost}" -u"${DB_USER:-root}" -p"${DB_PASS:-}" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
    2>/dev/null | tail -1)

if [ "${TABLE_COUNT:-0}" -eq "0" ]; then
    echo "[entrypoint] Database is empty – importing database.sql…"
    mysql -h"${DB_HOST:-localhost}" -u"${DB_USER:-root}" -p"${DB_PASS:-}" \
          "${DB_NAME}" < /var/www/html/database.sql
    echo "[entrypoint] Database initialised successfully."
else
    echo "[entrypoint] Database already has ${TABLE_COUNT} tables – skipping import."
fi

# ── Set PHP environment from shell env (Railway injects these) ────────────────
{
    echo "DB_HOST=${DB_HOST:-localhost}"
    echo "DB_USER=${DB_USER:-root}"
    echo "DB_PASS=${DB_PASS:-}"
    echo "DB_NAME=${DB_NAME:-shopphp}"
    echo "SITE_URL=${SITE_URL:-}"
} > /var/www/html/.env.runtime

# ── Hand off to Apache ────────────────────────────────────────────────────────
echo "[entrypoint] Starting Apache…"
exec "$@"
