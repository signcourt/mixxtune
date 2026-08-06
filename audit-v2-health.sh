#!/usr/bin/env bash

set -u

APP="/var/www/backstage-distribution"
cd "$APP" || exit 1

STAMP="$(date +%Y%m%d-%H%M%S)"
REPORT="storage/logs/v2-health-audit-$STAMP.log"
AUDIT_BUILD="storage/app/v2-audit-build"

exec > >(tee "$REPORT") 2>&1

echo "========================================"
echo " V2 NON-DESTRUCTIVE HEALTH AUDIT"
echo " Date: $(date)"
echo "========================================"

echo
echo "===== 1. APPLICATION ====="
php -v | head -1
php artisan --version
node --version
npm --version

echo
echo "===== 2. ENVIRONMENT ====="
grep -E \
'^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION)=' \
.env \
| sed -E 's/(DB_PASSWORD=).*/\1***HIDDEN***/'

echo
echo "===== 3. NGINX PROJECT CONFIG ====="
grep -RInE \
"server_name|root |fastcgi_pass" \
/etc/nginx/sites-enabled/backstage-distribution \
/etc/nginx/sites-available/backstage-distribution \
2>/dev/null || true

echo
echo "===== 4. LARAVEL BASIC HEALTH ====="

php artisan about || true

echo
echo "===== 5. MIGRATION STATUS ====="

php artisan migrate:status || true

echo
echo "===== 6. V2 ROUTES ====="

php artisan route:list \
| grep -E \
"v2/|v2\." \
| tail -200 || true

echo
echo "===== 7. OWNERSHIP ROUTES ====="

php artisan route:list \
| grep "v2/admin/ownership" || true

echo
echo "===== 8. PHP SYNTAX ====="

PHP_ERRORS=0

while IFS= read -r file; do
    if ! php -l "$file" >/tmp/php-lint-result 2>&1; then
        echo "PHP ERROR: $file"
        cat /tmp/php-lint-result
        PHP_ERRORS=$((PHP_ERRORS + 1))
    fi
done < <(
    find app routes database/migrations \
        -type f \
        -name "*.php" \
        -print
)

echo "PHP syntax errors: $PHP_ERRORS"

echo
echo "===== 9. OWNERSHIP FILES ====="

for file in \
    app/Services/V2/CatalogueOwnershipService.php \
    app/Services/V2/OwnershipRoyaltyService.php \
    app/Http/Controllers/V2/Admin/OwnershipTransferController.php \
    app/Console/Commands/V2OwnershipRepair.php \
    resources/js/Pages/V2/Admin/Ownership/Index.jsx \
    resources/js/V2/Shared/Layouts/PanelLayout.jsx
do
    if [ -f "$file" ]; then
        echo "FOUND: $file"
    else
        echo "MISSING: $file"
    fi
done

echo
echo "===== 10. OWNERSHIP PAGE IMPORT ====="

grep -nE \
"import .*PanelLayout|<PanelLayout|</PanelLayout>" \
resources/js/Pages/V2/Admin/Ownership/Index.jsx \
2>/dev/null || true

echo
echo "===== 11. BROKEN PANEL LAYOUT IMPORTS ====="

grep -RIn \
"@/V2/Shared/PanelLayout" \
resources/js \
2>/dev/null || true

echo
echo "===== 12. FRONTEND AUDIT BUILD ====="
echo "This build uses storage/app/v2-audit-build."
echo "Live public/build will not be changed."

rm -rf "$AUDIT_BUILD"

npx vite build \
    --outDir "$AUDIT_BUILD" \
    --emptyOutDir

VITE_STATUS=$?

echo
echo "Frontend audit build exit code: $VITE_STATUS"

echo
echo "===== 13. CURRENT LIVE BUILD ====="

if [ -f public/build/manifest.json ]; then
    echo "Live manifest found."
    stat public/build/manifest.json
else
    echo "WARNING: public/build/manifest.json missing."
fi

echo
echo "===== 14. DATABASE OWNERSHIP HEALTH ====="

php artisan tinker --execute='
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$checks = [
    "report_rows" => Schema::hasTable("report_rows"),
    "royalty_statements" => Schema::hasTable("royalty_statements"),
    "royalty_allocations" => Schema::hasTable("royalty_allocations"),
    "catalogue_transfers" => Schema::hasTable("catalogue_transfers"),
];

foreach ($checks as $table => $exists) {
    echo $table.": ".($exists ? "OK" : "MISSING").PHP_EOL;
}

if (Schema::hasTable("report_rows")) {
    echo "Report rows: ".DB::table("report_rows")->count().PHP_EOL;

    if (Schema::hasColumn("report_rows", "mapping_status")) {
        echo "Mapped: "
            .DB::table("report_rows")
                ->where("mapping_status", "mapped")
                ->count()
            .PHP_EOL;

        echo "Unmapped: "
            .DB::table("report_rows")
                ->where("mapping_status", "unmapped")
                ->count()
            .PHP_EOL;
    }
}

if (Schema::hasTable("royalty_statements")) {
    echo "Statements: "
        .DB::table("royalty_statements")->count()
        .PHP_EOL;
}

if (Schema::hasTable("royalty_allocations")) {
    echo "Allocations: "
        .DB::table("royalty_allocations")->count()
        .PHP_EOL;
}
' || true

echo
echo "===== 15. RECENT LARAVEL ERRORS ====="

grep -nE \
"production.ERROR|local.ERROR|PHP Fatal|ParseError|QueryException|Vite manifest" \
storage/logs/laravel.log \
2>/dev/null \
| tail -100 || true

echo
echo "===== 16. STORAGE PERMISSIONS ====="

ls -ld \
storage \
storage/logs \
storage/framework \
bootstrap/cache \
public/build \
2>/dev/null || true

echo
echo "========================================"
echo " AUDIT COMPLETE"
echo " Report: $REPORT"
echo " PHP errors: $PHP_ERRORS"
echo " Frontend audit exit code: $VITE_STATUS"
echo "========================================"

exit 0
