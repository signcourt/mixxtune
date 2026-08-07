#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="/var/www/backstage-distribution"
V2_ROOT="$PROJECT_ROOT/v2"

echo "=============================================="
echo "INSTALLING V2 RELEASE MODULE"
echo "=============================================="

mkdir -p "$V2_ROOT/modules/Releases"
mkdir -p "$V2_ROOT/runtime/state"

printf "{\n  \"module\": \"Releases\",\n  \"installed\": true,\n  \"version\": \"2.0.0\",\n  \"installed_at\": \"%s\"\n}\n" "$(date --iso-8601=seconds)" > "$V2_ROOT/runtime/state/releases-installed.json"

echo "Release module foundation installed."
echo "Shared release wizard and role-based behaviour will be added next."
echo "=============================================="
