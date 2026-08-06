#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="/var/www/backstage-distribution"
PACKAGE_DIR="$PROJECT_DIR/artist-panel-package"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE_NAME="artist-panel-full-source-${TIMESTAMP}.tar.gz"
ARCHIVE_PATH="$PROJECT_DIR/$ARCHIVE_NAME"

cd "$PROJECT_DIR"

echo "=================================================="
echo "ARTIST PANEL PACKAGE PREPARATION STARTED"
echo "=================================================="

rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR/source"
mkdir -p "$PACKAGE_DIR/reports"
mkdir -p "$PACKAGE_DIR/database"
mkdir -p "$PACKAGE_DIR/logs"

echo ""
echo "[1/12] Creating Git-style safety backup..."

tar \
    --exclude='./node_modules' \
    --exclude='./vendor' \
    --exclude='./storage/logs/*.log' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./public/build' \
    --exclude='./artist-panel-package' \
    --exclude='./artist-panel-full-source-*.tar.gz' \
    -czf "$PACKAGE_DIR/project-backup-${TIMESTAMP}.tar.gz" \
    .

echo "[2/12] Copying application source..."

copy_if_exists() {
    local source="$1"
    local destination="$2"

    if [ -e "$source" ]; then
        mkdir -p "$(dirname "$destination")"
        cp -a "$source" "$destination"
    fi
}

copy_if_exists "routes" \
    "$PACKAGE_DIR/source/routes"

copy_if_exists "app/Http/Controllers/Artist" \
    "$PACKAGE_DIR/source/app/Http/Controllers/Artist"

copy_if_exists "app/Http/Controllers/Admin" \
    "$PACKAGE_DIR/source/app/Http/Controllers/Admin"

copy_if_exists "app/Http/Requests" \
    "$PACKAGE_DIR/source/app/Http/Requests"

copy_if_exists "app/Models" \
    "$PACKAGE_DIR/source/app/Models"

copy_if_exists "app/Services" \
    "$PACKAGE_DIR/source/app/Services"

copy_if_exists "app/Policies" \
    "$PACKAGE_DIR/source/app/Policies"

copy_if_exists "app/Notifications" \
    "$PACKAGE_DIR/source/app/Notifications"

copy_if_exists "app/Mail" \
    "$PACKAGE_DIR/source/app/Mail"

copy_if_exists "app/Providers" \
    "$PACKAGE_DIR/source/app/Providers"

copy_if_exists "resources/js/Pages/Artist" \
    "$PACKAGE_DIR/source/resources/js/Pages/Artist"

copy_if_exists "resources/js/Pages/Admin" \
    "$PACKAGE_DIR/source/resources/js/Pages/Admin"

copy_if_exists "resources/js/Layouts" \
    "$PACKAGE_DIR/source/resources/js/Layouts"

copy_if_exists "resources/js/Components" \
    "$PACKAGE_DIR/source/resources/js/Components"

copy_if_exists "resources/css" \
    "$PACKAGE_DIR/source/resources/css"

copy_if_exists "database/migrations" \
    "$PACKAGE_DIR/source/database/migrations"

copy_if_exists "database/seeders" \
    "$PACKAGE_DIR/source/database/seeders"

copy_if_exists "config" \
    "$PACKAGE_DIR/source/config"

echo "[3/12] Copying project configuration..."

for file in \
    composer.json \
    composer.lock \
    package.json \
    package-lock.json \
    vite.config.js \
    tailwind.config.js \
    postcss.config.js \
    phpunit.xml \
    artisan \
    .env.example
do
    if [ -f "$file" ]; then
        cp -a "$file" "$PACKAGE_DIR/source/"
    fi
done

echo "[4/12] Exporting Laravel routes..."

php artisan route:list \
    > "$PACKAGE_DIR/reports/all-routes.txt" 2>&1 || true

php artisan route:list | grep -i artist \
    > "$PACKAGE_DIR/reports/artist-routes.txt" 2>&1 || true

php artisan route:list | grep -Ei \
"release|track|wallet|royalt|withdraw|invoice|statement|kyc|support|catalog|notification|profile|setting" \
    > "$PACKAGE_DIR/reports/panel-routes.txt" 2>&1 || true

echo "[5/12] Exporting PHP and Laravel versions..."

{
    echo "PHP VERSION"
    php -v
    echo ""
    echo "COMPOSER VERSION"
    composer --version 2>/dev/null || true
    echo ""
    echo "NODE VERSION"
    node -v 2>/dev/null || true
    echo ""
    echo "NPM VERSION"
    npm -v 2>/dev/null || true
    echo ""
    echo "LARAVEL VERSION"
    php artisan --version 2>/dev/null || true
} > "$PACKAGE_DIR/reports/environment.txt" 2>&1

echo "[6/12] Exporting database tables..."

php artisan tinker --execute="
\$tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');

foreach (\$tables as \$row) {
    echo array_values((array) \$row)[0].PHP_EOL;
}
" > "$PACKAGE_DIR/database/tables.txt" 2>&1 || true

echo "[7/12] Exporting important table schemas..."

TABLES=(
    users
    artists
    labels
    releases
    tracks
    track_splits
    distribution_stores
    release_store_deliveries
    release_status_logs
    wallets
    wallet_transactions
    withdrawals
    royalty_ledgers
    royalty_reports
    statements
    invoices
    kyc_submissions
    support_tickets
    support_ticket_messages
    notifications
)

for table in "${TABLES[@]}"; do
    php artisan tinker --execute="
if (\Illuminate\Support\Facades\Schema::hasTable('$table')) {
    print_r(
        \Illuminate\Support\Facades\DB::select(
            'SHOW CREATE TABLE \`$table\`'
        )
    );
} else {
    echo 'TABLE_NOT_FOUND: $table'.PHP_EOL;
}
" > "$PACKAGE_DIR/database/${table}-schema.txt" 2>&1 || true
done

echo "[8/12] Exporting migration status..."

php artisan migrate:status \
    > "$PACKAGE_DIR/reports/migration-status.txt" 2>&1 || true

echo "[9/12] Exporting relevant file inventory..."

find \
    app \
    routes \
    resources/js \
    database/migrations \
    -type f \
    | sort \
    > "$PACKAGE_DIR/reports/file-inventory.txt"

echo "[10/12] Exporting current application checks..."

{
    echo "===== PHP CONTROLLER SYNTAX ====="

    find app/Http/Controllers/Artist \
        -type f \
        -name "*.php" \
        -print0 2>/dev/null \
        | while IFS= read -r -d '' file; do
            php -l "$file" || true
        done

    echo ""
    echo "===== ROUTE FILE SYNTAX ====="

    find routes \
        -type f \
        -name "*.php" \
        -print0 2>/dev/null \
        | while IFS= read -r -d '' file; do
            php -l "$file" || true
        done

    echo ""
    echo "===== MODEL SYNTAX ====="

    find app/Models \
        -type f \
        -name "*.php" \
        -print0 2>/dev/null \
        | while IFS= read -r -d '' file; do
            php -l "$file" || true
        done
} > "$PACKAGE_DIR/reports/php-syntax-check.txt" 2>&1

echo "[11/12] Exporting recent Laravel errors..."

if [ -f "storage/logs/laravel.log" ]; then
    tail -n 1500 storage/logs/laravel.log \
        > "$PACKAGE_DIR/logs/laravel-latest.log"
else
    echo "Laravel log file not found." \
        > "$PACKAGE_DIR/logs/laravel-latest.log"
fi

echo "[12/12] Creating final compressed package..."

cat > "$PACKAGE_DIR/README.txt" <<EOF
ARTIST PANEL SOURCE PACKAGE
Generated: $(date)

Project:
$PROJECT_DIR

This package contains:
- Artist and Admin panel source
- Laravel routes
- Models, controllers, requests and services
- React/Inertia pages and layouts
- Database migrations
- Important database table structures
- Route reports
- Migration status
- Recent Laravel errors
- Environment versions
- A complete pre-change project backup

SECURITY:
The real .env file is intentionally excluded.
Database passwords and application secrets are not included.
EOF

tar -czf "$ARCHIVE_PATH" \
    -C "$PROJECT_DIR" \
    "artist-panel-package"

echo ""
echo "=================================================="
echo "PACKAGE CREATED SUCCESSFULLY"
echo "=================================================="
echo ""
echo "File:"
echo "$ARCHIVE_PATH"
echo ""
ls -lh "$ARCHIVE_PATH"

echo ""
echo "SHA256:"
sha256sum "$ARCHIVE_PATH"

echo ""
echo "Upload this file in ChatGPT:"
echo "$ARCHIVE_NAME"
echo "=================================================="
