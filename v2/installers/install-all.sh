#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/var/www/backstage-distribution"
V2_ROOT="$PROJECT_ROOT/v2"

echo "=============================================="
echo "BACKSTAGE DISTRIBUTION V2 FOUNDATION"
echo "=============================================="

mkdir -p "$V2_ROOT/runtime/state" "$V2_ROOT/logs" "$V2_ROOT/backups" "$V2_ROOT/reports"

BACKUP="$V2_ROOT/backups/foundation-$(date +%Y%m%d-%H%M%S).tar.gz"

echo "Creating safety backup..."
tar --exclude=node_modules --exclude=vendor --exclude=public/build --exclude=storage/logs --exclude=v2/backups --exclude=v2/logs -czf "$BACKUP" -C "$PROJECT_ROOT" app routes resources database config composer.json package.json

printf "{\n  \"module\": \"Core\",\n  \"version\": \"2.0.0\",\n  \"installed\": true,\n  \"installed_at\": \"%s\"\n}\n" "$(date --iso-8601=seconds)" > "$V2_ROOT/runtime/state/core-installed.json"

cd "$PROJECT_ROOT"
php artisan optimize:clear
php artisan route:list > "$V2_ROOT/reports/routes.txt"
php artisan migrate:status > "$V2_ROOT/reports/migrations.txt"

echo "=============================================="
echo "V2 FOUNDATION INSTALLATION COMPLETE"
echo "=============================================="
