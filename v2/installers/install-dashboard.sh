#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/var/www/backstage-distribution"
V2_ROOT="$PROJECT_ROOT/v2"

echo "=============================================="
echo "INSTALLING V2 DASHBOARD MODULE"
echo "=============================================="

mkdir -p "$V2_ROOT/modules/Dashboard"
mkdir -p "$V2_ROOT/runtime/state"

printf "{\n  \"module\": \"Dashboard\",\n  \"installed\": true,\n  \"version\": \"2.0.0\",\n  \"installed_at\": \"%s\"\n}\n" "$(date --iso-8601=seconds)" > "$V2_ROOT/runtime/state/dashboard-installed.json"

echo "Dashboard module foundation installed."
echo "Shared dashboard UI and panel-specific data will be added next."
echo "=============================================="
