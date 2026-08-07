#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/compact-panel-density/$STAMP"

LAYOUT="resources/js/V2/Shared/Layouts/PanelLayout.jsx"
CSS_FILE="resources/css/app.css"

mkdir -p \
    "$BACKUP/resources/js/V2/Shared/Layouts" \
    "$BACKUP/resources/css" \
    "v2/runtime/state"

echo "=============================================="
echo "INSTALLING COMPACT V2 PANEL DENSITY"
echo "=============================================="

echo "[1/5] Creating backup..."

if [ -f "$LAYOUT" ]; then
    cp -a "$LAYOUT" \
        "$BACKUP/resources/js/V2/Shared/Layouts/PanelLayout.jsx"
else
    echo "ERROR: $LAYOUT not found."
    exit 1
fi

if [ -f "$CSS_FILE" ]; then
    cp -a "$CSS_FILE" \
        "$BACKUP/resources/css/app.css"
else
    echo "ERROR: $CSS_FILE not found."
    exit 1
fi


echo "[2/5] Adding compact density class to PanelLayout..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/V2/Shared/Layouts/PanelLayout.jsx"
)

text = path.read_text()

old = '''
        <div className="min-h-screen bg-slate-100 text-slate-900">
'''

new = '''
        <div className="v2-panel-density min-h-screen bg-slate-100 text-slate-900">
'''

if old in text:
    text = text.replace(
        old,
        new,
        1
    )

    path.write_text(text)

    print("v2-panel-density class added.")
elif "v2-panel-density" in text:
    print("Density class already present.")
else:
    raise SystemExit(
        "PanelLayout root wrapper automatic locate नहीं हुआ।"
    )
PY


echo "[3/5] Adding responsive panel zoom CSS..."

python3 - <<'PY'
from pathlib import Path

path = Path("resources/css/app.css")
text = path.read_text()

start = "/* V2 COMPACT PANEL DENSITY START */"
end = "/* V2 COMPACT PANEL DENSITY END */"

block = r'''
/* V2 COMPACT PANEL DENSITY START */

/*
|--------------------------------------------------------------------------
| V2 Panel Display Density
|--------------------------------------------------------------------------
|
| Browser zoom remains at 100%.
| Change --v2-panel-scale to adjust the complete panel size.
|
| 0.88 = compact
| 0.90 = medium compact
| 1.00 = normal size
|
*/

:root {
    --v2-panel-scale: 0.88;
}

/* Mobile remains full size for readability. */
.v2-panel-density {
    width: 100%;
    min-height: 100vh;
}

/* Laptop and desktop compact mode. */
@media (min-width: 1024px) {
    .v2-panel-density {
        zoom: var(--v2-panel-scale);
        min-height: calc(
            100vh / var(--v2-panel-scale)
        );
    }
}

/*
 * Safari fallback and browsers where CSS zoom
 * is unavailable.
 */
@supports not (zoom: 1) {
    @media (min-width: 1024px) {
        .v2-panel-density {
            width: calc(
                100% / var(--v2-panel-scale)
            );

            min-height: calc(
                100vh / var(--v2-panel-scale)
            );

            transform: scale(
                var(--v2-panel-scale)
            );

            transform-origin: top left;
        }
    }
}

/* Compact cards and tables. */
@media (min-width: 1024px) {
    .v2-panel-density table th {
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .v2-panel-density table td {
        padding-top: 0.72rem;
        padding-bottom: 0.72rem;
    }

    .v2-panel-density main {
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
    }
}

/* V2 COMPACT PANEL DENSITY END */
'''

if start in text and end in text:
    before = text.split(start, 1)[0]
    after = text.split(end, 1)[1]

    text = (
        before.rstrip()
        + "\n\n"
        + block.strip()
        + "\n"
        + after.lstrip()
    )
else:
    text = (
        text.rstrip()
        + "\n\n"
        + block.strip()
        + "\n"
    )

path.write_text(text)

print("Compact density CSS installed.")
PY


echo "[4/5] Building frontend..."

npm run build

php artisan optimize:clear


echo "[5/5] Saving state..."

printf '{\n  "module": "CompactPanelDensity",\n  "installed": true,\n  "desktop_scale": 0.88,\n  "version": "3.4.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/compact-panel-density-installed.json

echo ""
echo "===== DENSITY CLASS CHECK ====="

grep -n \
"v2-panel-density" \
resources/js/V2/Shared/Layouts/PanelLayout.jsx

echo ""
echo "===== SCALE CHECK ====="

grep -nE \
"v2-panel-scale|V2 COMPACT PANEL" \
resources/css/app.css

echo ""
echo "=============================================="
echo "COMPACT PANEL DENSITY INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/compact-panel-density-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
