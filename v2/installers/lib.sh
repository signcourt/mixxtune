#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="/var/www/backstage-distribution"
V2_ROOT="$PROJECT_ROOT/v2"
LOG_DIR="$V2_ROOT/logs"
BACKUP_DIR="$V2_ROOT/backups"
REPORT_DIR="$V2_ROOT/reports"
STATE_DIR="$V2_ROOT/runtime/state"

mkdir -p \
    "$LOG_DIR" \
    "$BACKUP_DIR" \
    "$REPORT_DIR" \
    "$STATE_DIR"

timestamp() {
    date +"%Y%m%d-%H%M%S"
}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || {
        fail "Required command not found: $1"
    }
}

backup_project_files() {
    local backup_path
    backup_path="$BACKUP_DIR/v2-safety-backup-$(timestamp).tar.gz"

    log "Creating safety backup..."

    tar \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='public/build' \
        --exclude='storage/logs' \
        --exclude='v2/backups' \
        --exclude='v2/logs' \
        -czf "$backup_path" \
        -C "$PROJECT_ROOT" \
        app \
        routes \
        resources \
        database \
        config \
        composer.json \
        package.json

    log "Backup created: $backup_path"
}

clear_caches() {
    log "Clearing Laravel caches..."

    cd "$PROJECT_ROOT"
    php artisan optimize:clear
}

route_check() {
    local report
    report="$REPORT_DIR/routes-$(timestamp).txt"

    log "Checking Laravel routes..."

    cd "$PROJECT_ROOT"
    php artisan route:list > "$report"

    log "Route report created: $report"
}

health_check() {
    local report
    report="$REPORT_DIR/health-$(timestamp).txt"

    log "Running health check..."

    {
        echo "===== DATE ====="
        date

        echo ""
        echo "===== PHP ====="
        php -v

        echo ""
        echo "===== LARAVEL ====="
        cd "$PROJECT_ROOT"
        php artisan --version

        echo ""
        echo "===== NODE ====="
        node -v 2>/dev/null || true

        echo ""
        echo "===== NPM ====="
        npm -v 2>/dev/null || true

        echo ""
        echo "===== MIGRATIONS ====="
        php artisan migrate:status
    } > "$report" 2>&1

    log "Health report created: $report"
}
