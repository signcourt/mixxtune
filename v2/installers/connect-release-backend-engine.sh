#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/release-engine-connection/$STAMP"

mkdir -p "$BACKUP"

echo "=================================================="
echo "CONNECTING V2 RELEASE CONTROLLERS TO BACKEND ENGINE"
echo "=================================================="

echo "[1/8] Creating backups..."

for FILE in \
    app/Http/Controllers/V2/ReleaseController.php \
    app/Http/Controllers/V2/ReleaseTrackController.php \
    routes/web.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/8] Updating V2 ReleaseController imports..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseController.php"
)

if not path.exists():
    raise SystemExit(
        "V2 ReleaseController.php nahi mila."
    )

text = path.read_text()

imports = [
    "use App\\Services\\V2\\ReleaseAccessService;\n",
    "use App\\Services\\V2\\ReleaseValidationService;\n",
    "use App\\Services\\V2\\ReleaseWorkflowService;\n",
]

marker = "use App\\Services\\V2\\PermissionService;\n"

if marker not in text:
    raise SystemExit(
        "PermissionService import marker nahi mila."
    )

for import_line in imports:
    if import_line not in text:
        text = text.replace(
            marker,
            marker + import_line,
            1
        )

path.write_text(text)

print("Release backend service imports added.")
PY


echo "[3/8] Replacing Submit for Review method..."

python3 - <<'PY'
from pathlib import Path
import re

path = Path(
    "app/Http/Controllers/V2/ReleaseController.php"
)

text = path.read_text()

pattern = re.compile(
    r"""    public function submitForReview\(
        Request \$request,
        Release \$release,
        PermissionService \$permissions
    \): RedirectResponse \{.*?
    \}

(?=    (?:public|private) function )""",
    re.DOTALL,
)

replacement = r'''    public function submitForReview(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseWorkflowService $workflow
    ): RedirectResponse {
        $access->authorizeSubmit(
            $request->user(),
            $release
        );

        $release = $workflow->submit(
            $release,
            $request->user(),
            [
                'action' =>
                    'submitted_for_review',

                'remarks' =>
                    'Release submitted for admin review through V2.',

                'ip_address' =>
                    $request->ip(),

                'user_agent' =>
                    $request->userAgent(),
            ]
        );

        return redirect()
            ->route('v2.releases.index')
            ->with(
                'success',
                "Release '{$release->title}' submitted for review."
            );
    }

'''

updated, count = pattern.subn(
    replacement,
    text,
    count=1
)

if count == 0:
    raise SystemExit(
        "submitForReview method automatic replace nahi hua."
    )

path.write_text(updated)

print("submitForReview connected to WorkflowService.")
PY


echo "[4/8] Adding backend validation/checklist endpoint..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseController.php"
)

text = path.read_text()

if "public function submissionChecklist(" in text:
    print(
        "submissionChecklist method already exists."
    )
else:
    marker = "    public function submitForReview("

    if marker not in text:
        raise SystemExit(
            "submitForReview marker nahi mila."
        )

    method = r'''    public function submissionChecklist(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseValidationService $validator
    ) {
        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json(
            $validator->checklist($release)
        );
    }

'''

    text = text.replace(
        marker,
        method + marker,
        1
    )

    path.write_text(text)

    print(
        "Submission checklist endpoint method added."
    )
PY


echo "[5/8] Connecting edit and update access checks..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseController.php"
)

text = path.read_text()

# Edit method signature.
old = """    public function edit(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): Response {
        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );
"""

new = """    public function edit(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): Response {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );
"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )
else:
    print(
        "Edit access block exact format me nahi mila; skipped."
    )

# Update method signature.
old = """    public function update(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): RedirectResponse {
        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );
"""

new = """    public function update(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );
"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )
else:
    print(
        "Update access block exact format me nahi mila; skipped."
    )

# Distribution method.
old = """    public function saveDistribution(
        Request $request,
        Release $release,
        PermissionService $permissions
    ): RedirectResponse {
        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        abort_unless(
            in_array(
                $release->status,
                [
                    'draft',
                    'changes_requested',
                    'rejected',
                ],
                true
            ),
            403,
            'This release is locked for editing.'
        );
"""

new = """    public function saveDistribution(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {
        $access->authorizeUpdate(
            $request->user(),
            $release
        );
"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )
else:
    print(
        "Distribution access block exact format me nahi mila; skipped."
    )

path.write_text(text)

print("Release edit/update access integration processed.")
PY


echo "[6/8] Connecting V2 Track Controller to access service..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/ReleaseTrackController.php"
)

if not path.exists():
    print(
        "ReleaseTrackController nahi mila; track integration skipped."
    )
    raise SystemExit(0)

text = path.read_text()

import_line = (
    "use App\\Services\\V2\\ReleaseAccessService;\n"
)

marker = (
    "use App\\Services\\V2\\PermissionService;\n"
)

if (
    import_line not in text
    and marker in text
):
    text = text.replace(
        marker,
        marker + import_line,
        1
    )

# Store method.
old = """        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $release,
            $permissions
        );

        $this->ensureEditable($release);
"""

new = """        $access->authorizeUpdate(
            $request->user(),
            $release
        );
"""

store_signature = """        Release $release,
        PermissionService $permissions
    ): RedirectResponse {"""

store_signature_new = """        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {"""

if store_signature in text:
    text = text.replace(
        store_signature,
        store_signature_new,
        1
    )

if old in text:
    text = text.replace(
        old,
        new,
        1
    )

# Update method signature.
update_signature = """        Track $track,
        PermissionService $permissions
    ): RedirectResponse {"""

update_signature_new = """        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {"""

if update_signature in text:
    text = text.replace(
        update_signature,
        update_signature_new,
        1
    )

update_old = """        $permissions->authorize(
            $request->user(),
            'releases.update'
        );

        $this->authorizeRelease(
            $request,
            $track->release,
            $permissions
        );

        $this->ensureEditable(
            $track->release
        );
"""

update_new = """        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );
"""

if update_old in text:
    text = text.replace(
        update_old,
        update_new,
        1
    )

# Destroy method signature.
destroy_signature = """        Track $track,
        PermissionService $permissions
    ): RedirectResponse {"""

destroy_signature_new = """        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): RedirectResponse {"""

if destroy_signature in text:
    text = text.replace(
        destroy_signature,
        destroy_signature_new,
        1
    )

destroy_old = update_old
destroy_new = update_new

if destroy_old in text:
    text = text.replace(
        destroy_old,
        destroy_new,
        1
    )

# Download method signature.
download_signature = """        Track $track,
        PermissionService $permissions
    ) {"""

download_signature_new = """        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access
    ) {"""

if download_signature in text:
    text = text.replace(
        download_signature,
        download_signature_new,
        1
    )

download_old = """        $permissions->authorize(
            $request->user(),
            'releases.view'
        );

        $this->authorizeRelease(
            $request,
            $track->release,
            $permissions
        );
"""

download_new = """        $access->authorizeView(
            $request->user(),
            $track->release
        );
"""

if download_old in text:
    text = text.replace(
        download_old,
        download_new,
        1
    )

path.write_text(text)

print("TrackController access integration processed.")
PY


echo "[7/8] Adding checklist route..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

route_name = "v2.releases.submission-checklist"

if route_name not in text:
    text += r"""

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/submission-checklist',
        [\App\Http\Controllers\V2\ReleaseController::class, 'submissionChecklist']
    )
    ->name('v2.releases.submission-checklist');
"""

    path.write_text(text)

    print("Submission checklist route added.")
else:
    print("Submission checklist route already exists.")
PY


echo "[8/8] Running final checks..."

php -l \
app/Http/Controllers/V2/ReleaseController.php

if [ -f \
app/Http/Controllers/V2/ReleaseTrackController.php
]; then
    php -l \
    app/Http/Controllers/V2/ReleaseTrackController.php
fi

php -l routes/web.php

php artisan optimize:clear

php artisan route:list | grep -E \
"v2/releases/.*/(submit|submission-checklist)"

printf '{\n  "module": "ReleaseBackendConnection",\n  "installed": true,\n  "version": "2.3.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/release-backend-connection-installed.json

echo ""
echo "=================================================="
echo "RELEASE BACKEND ENGINE CONNECTED"
echo "=================================================="

echo ""
echo "Controller integration:"

grep -nE \
"ReleaseAccessService|ReleaseWorkflowService|submissionChecklist|submitForReview" \
app/Http/Controllers/V2/ReleaseController.php

echo ""
echo "State:"

cat \
v2/runtime/state/release-backend-connection-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
