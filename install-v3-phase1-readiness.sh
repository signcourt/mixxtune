#!/usr/bin/env bash

set -euo pipefail

PROJECT="/var/www/backstage-distribution"
TARGET_DOMAIN="mixxtune.com"
TARGET_IP="82.29.160.18"
BACKUP_ROOT="/root/mixxtune-single-domain-20260802-103700"

cd "$PROJECT"

echo "========================================"
echo " MIXX TUNE V3 - PHASE 1 READINESS"
echo "========================================"

echo ""
echo "===== BACKUP CHECK ====="

test -f "$BACKUP_ROOT/project-files.tar.gz" \
    || { echo "Project backup missing."; exit 1; }

test -f "$BACKUP_ROOT/database.sql.gz" \
    || { echo "Database backup missing."; exit 1; }

gzip -t "$BACKUP_ROOT/database.sql.gz"

echo "Project backup: OK"
echo "Database backup: OK"

echo ""
echo "===== LARAVEL CHECK ====="

php artisan route:list >/dev/null

php artisan tinker --execute='
DB::connection()->getPdo();
echo "DATABASE=WORKING".PHP_EOL;
echo "USERS=".DB::table("users")->count().PHP_EOL;
'

echo ""
echo "===== DNS CHECK ====="

DNS_IPS="$(
    getent ahostsv4 "$TARGET_DOMAIN" \
    | awk '{print $1}' \
    | sort -u
)"

echo "$TARGET_DOMAIN resolves to:"
echo "$DNS_IPS"
echo "Required VPS IP: $TARGET_IP"

if echo "$DNS_IPS" | grep -qx "$TARGET_IP"; then
    DNS_READY="YES"
else
    DNS_READY="NO"
fi

echo "DNS_READY=$DNS_READY"

echo ""
echo "===== SSL CHECK ====="

if [ -f "/etc/letsencrypt/live/$TARGET_DOMAIN/fullchain.pem" ] \
   && [ -f "/etc/letsencrypt/live/$TARGET_DOMAIN/privkey.pem" ]; then
    SSL_READY="YES"
else
    SSL_READY="NO"
fi

echo "SSL_READY=$SSL_READY"

echo ""
echo "===== CURRENT APP CONFIG ====="

grep -E \
'^(APP_URL|SESSION_DOMAIN|SESSION_DRIVER|CACHE_STORE|QUEUE_CONNECTION)=' \
.env

echo ""
echo "===== CURRENT DOMAIN ROUTES ====="

grep -n "Route::domain" routes/web.php || true

echo ""
echo "===== CURRENT SUBDOMAIN REFERENCES ====="

grep -RniE \
'home\.mixxtune\.com|admin\.mixxtune\.com|artist\.mixxtune\.com|label\.mixxtune\.com' \
app routes resources config \
--exclude='*.backup*' \
--exclude='*.before-*' \
| head -100 || true

echo ""
echo "===== RESULT ====="

if [ "$DNS_READY" = "YES" ] && [ "$SSL_READY" = "YES" ]; then
    echo "READY FOR PHASE 2"
else
    echo "NOT READY FOR MIGRATION"

    [ "$DNS_READY" = "YES" ] \
        || echo "DNS must point mixxtune.com to $TARGET_IP"

    [ "$SSL_READY" = "YES" ] \
        || echo "SSL certificate for mixxtune.com is missing"
fi

echo ""
echo "NO FILES WERE CHANGED"
