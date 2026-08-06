#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/impersonation-banner-integration/$STAMP"

MIDDLEWARE="app/Http/Middleware/HandleInertiaRequests.php"
LAYOUT="resources/js/V2/Shared/Layouts/PanelLayout.jsx"
BANNER="resources/js/V2/Shared/Components/ImpersonationBanner.jsx"

mkdir -p \
    "$BACKUP" \
    resources/js/V2/Shared/Components \
    v2/runtime/state

echo "=================================================="
echo "CONNECTING IMPERSONATION BANNER"
echo "=================================================="

if [ ! -f "$MIDDLEWARE" ]; then
    echo "ERROR: $MIDDLEWARE नहीं मिला।"
    exit 1
fi

if [ ! -f "$LAYOUT" ]; then
    echo "ERROR: $LAYOUT नहीं मिला।"
    exit 1
fi

for FILE in \
    "$MIDDLEWARE" \
    "$LAYOUT" \
    "$BANNER"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/5] Creating impersonation banner component..."

cat > "$BANNER" <<'JSX'
import {
    router,
    usePage,
} from '@inertiajs/react';

export default function ImpersonationBanner() {
    const {
        auth = {},
        impersonation = {},
    } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    const stopImpersonation = () => {
        const confirmed = window.confirm(
            'Return to the Super Admin account?'
        );

        if (!confirmed) {
            return;
        }

        router.post(
            '/v2/impersonation/stop',
            {},
            {
                preserveScroll: false,
            }
        );
    };

    return (
        <div className="sticky top-0 z-[100] border-b border-amber-500 bg-amber-400 px-4 py-3 text-amber-950 shadow-md">
            <div className="mx-auto flex w-full flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-950 text-lg text-white">
                        ⚠
                    </span>

                    <div>
                        <div className="text-sm font-bold">
                            Impersonation Mode Active
                        </div>

                        <div className="text-xs font-medium">
                            You are viewing the panel as{' '}
                            <strong>
                                {auth?.user?.name ??
                                    'another user'}
                            </strong>

                            {auth?.user?.role
                                ? ` (${String(
                                      auth.user.role
                                  ).replaceAll(
                                      '_',
                                      ' '
                                  )})`
                                : ''}
                            .
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={stopImpersonation}
                    className="rounded-xl bg-slate-950 px-5 py-2.5 text-xs font-bold text-white transition hover:bg-slate-800"
                >
                    Return to Super Admin
                </button>
            </div>
        </div>
    );
}
JSX

echo "[2/5] Sharing impersonation session through Inertia..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Middleware/HandleInertiaRequests.php"
)

text = path.read_text()

if "'impersonation' =>" in text or '"impersonation" =>' in text:
    print("Impersonation shared prop already exists.")
    raise SystemExit(0)

needle = """            'auth' => [
"""

if needle not in text:
    print("Standard auth shared block नहीं मिला। Alternative insertion कर रहे हैं.")

    share_marker = """        return [
"""

    if share_marker not in text:
        raise SystemExit(
            "HandleInertiaRequests share() return array नहीं मिला।"
        )

    insertion = """        return [
            'impersonation' => fn () => [
                'active' => $request->session()->has(
                    'impersonator_user_id'
                ),

                'impersonator_user_id' =>
                    $request->session()->get(
                        'impersonator_user_id'
                    ),

                'impersonator_name' =>
                    $request->session()->get(
                        'impersonator_name'
                    ),

                'impersonator_email' =>
                    $request->session()->get(
                        'impersonator_email'
                    ),

                'impersonated_user_id' =>
                    $request->session()->get(
                        'impersonated_user_id'
                    ),

                'started_at' =>
                    $request->session()->get(
                        'impersonation_started_at'
                    ),
            ],

"""

    text = text.replace(
        share_marker,
        insertion,
        1
    )
else:
    insertion = """            'impersonation' => fn () => [
                'active' => $request->session()->has(
                    'impersonator_user_id'
                ),

                'impersonator_user_id' =>
                    $request->session()->get(
                        'impersonator_user_id'
                    ),

                'impersonator_name' =>
                    $request->session()->get(
                        'impersonator_name'
                    ),

                'impersonator_email' =>
                    $request->session()->get(
                        'impersonator_email'
                    ),

                'impersonated_user_id' =>
                    $request->session()->get(
                        'impersonated_user_id'
                    ),

                'started_at' =>
                    $request->session()->get(
                        'impersonation_started_at'
                    ),
            ],

"""

    text = text.replace(
        needle,
        insertion + needle,
        1
    )

path.write_text(text)

print("Impersonation shared prop added.")
PY

echo "[3/5] Adding banner to PanelLayout..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/V2/Shared/Layouts/PanelLayout.jsx"
)

text = path.read_text()

import_line = """import ImpersonationBanner from '@/V2/Shared/Components/ImpersonationBanner';
"""

if "ImpersonationBanner" not in text:
    lines = text.splitlines()

    last_import_index = -1

    for index, line in enumerate(lines):
        if line.startswith("import "):
            last_import_index = index

    if last_import_index == -1:
        lines.insert(
            0,
            import_line.rstrip()
        )
    else:
        lines.insert(
            last_import_index + 1,
            import_line.rstrip()
        )

    text = "\n".join(lines) + "\n"

if "<ImpersonationBanner" not in text:
    return_patterns = [
        """    return (
        <div""",

        """    return (
        <>""",

        """return (
        <div""",

        """return (
        <>""",
    ]

    replaced = False

    for pattern in return_patterns:
        if pattern not in text:
            continue

        if "<div" in pattern:
            replacement = pattern.replace(
                "<div",
                """<>
            <ImpersonationBanner />

            <div"""
            )

            text = text.replace(
                pattern,
                replacement,
                1
            )

            # Outer fragment needs closing tag.
            closing_candidates = [
                """        </div>
    );
}""",
                """    </div>
    );
}""",
            ]

            closed = False

            for candidate in closing_candidates:
                if candidate in text:
                    replacement_close = candidate.replace(
                        """
    );
}""",
                        """
        </>
    );
}"""
                    )

                    text = text.replace(
                        candidate,
                        replacement_close,
                        1
                    )

                    closed = True
                    break

            if not closed:
                raise SystemExit(
                    "PanelLayout outer closing tag safely नहीं मिला। Backup restore करें."
                )
        else:
            replacement = pattern.replace(
                "<>",
                """<>
            <ImpersonationBanner />"""
            )

            text = text.replace(
                pattern,
                replacement,
                1
            )

        replaced = True
        break

    if not replaced:
        raise SystemExit(
            "PanelLayout return wrapper नहीं मिला।"
        )

path.write_text(text)

print("ImpersonationBanner added to PanelLayout.")
PY

echo "[4/5] PHP and frontend checks..."

php -l "$MIDDLEWARE"
php -l \
app/Http/Controllers/V2/Admin/UserImpersonationController.php

grep -n \
"ImpersonationBanner" \
"$LAYOUT"

grep -n \
"'impersonation'" \
"$MIDDLEWARE"

npm run build

echo "[5/5] Clearing caches and verifying routes..."

php artisan optimize:clear

php artisan route:list | grep -E \
"v2/(admin/users/.*/impersonate|impersonation/stop)"

printf '{\n  "module": "ImpersonationBannerIntegration",\n  "installed": true,\n  "version": "4.7.5",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/impersonation-banner-integration-installed.json

echo ""
echo "=================================================="
echo "IMPERSONATION BANNER CONNECTED"
echo "=================================================="

cat \
v2/runtime/state/impersonation-banner-integration-installed.json

echo ""
echo "Test page:"
echo "https://admin.mixxtune.com/v2/admin/users"

echo ""
echo "Backup:"
echo "$BACKUP"
