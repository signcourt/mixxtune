#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/release-backend-engine/$STAMP"

mkdir -p \
    "$BACKUP/app/Services/V2" \
    "$PROJECT/app/Services/V2" \
    "$PROJECT/v2/runtime/state"

echo "=================================================="
echo "V2 RELEASE BACKEND ENGINE"
echo "=================================================="

echo "[1/7] Creating backup..."

for FILE in \
    app/Services/V2/ReleaseAccessService.php \
    app/Services/V2/ReleaseValidationService.php \
    app/Services/V2/ReleaseWorkflowService.php
do
    if [ -f "$FILE" ]; then
        cp -a "$FILE" "$BACKUP/app/Services/V2/"
    fi
done


echo "[2/7] Creating Release Access Service..."

cat > app/Services/V2/ReleaseAccessService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ReleaseAccessService
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {
    }

    public function role(?User $user): string
    {
        return $this->permissions->role($user);
    }

    public function authorizeView(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.view'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot access this release.'
        );
    }

    public function authorizeUpdate(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.update'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot manage this release.'
        );

        abort_unless(
            $this->isEditable($release),
            403,
            'This release is locked for editing.'
        );
    }

    public function authorizeSubmit(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.submit'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot submit this release.'
        );

        abort_unless(
            $this->isEditable($release),
            403,
            'This release has already been submitted.'
        );
    }

    public function canAccess(
        ?User $user,
        Release $release
    ): bool {
        if (!$user) {
            return false;
        }

        $role = $this->role($user);

        if ($role === 'super_admin') {
            return true;
        }

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            return $artist
                && (int) $release->artist_id
                    === (int) $artist->id;
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            return $label
                && (int) $release->label_id
                    === (int) $label->id;
        }

        if ($role === 'admin') {
            return $this->adminCanAccess(
                $user,
                $release
            );
        }

        return false;
    }

    public function isEditable(
        Release $release
    ): bool {
        return in_array(
            $release->status,
            [
                'draft',
                'changes_requested',
                'rejected',
            ],
            true
        );
    }

    private function adminCanAccess(
        User $user,
        Release $release
    ): bool {
        if (
            Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artist = Artist::query()
                ->find($release->artist_id);

            return $artist
                && (int) $artist->assigned_admin_id
                    === (int) $user->id;
        }

        if (
            Schema::hasColumn(
                'labels',
                'assigned_admin_id'
            )
        ) {
            $label = Label::query()
                ->find($release->label_id);

            return $label
                && (int) $label->assigned_admin_id
                    === (int) $user->id;
        }

        return false;
    }
}
PHP


echo "[3/7] Creating Release Validation Service..."

cat > app/Services/V2/ReleaseValidationService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use Illuminate\Support\Carbon;

class ReleaseValidationService
{
    public function validateForSubmission(
        Release $release
    ): array {
        $release->loadMissing('tracks');

        $errors = [];

        $this->validateReleaseMetadata(
            $release,
            $errors
        );

        $this->validateArtists(
            $release,
            $errors
        );

        $this->validateTracks(
            $release,
            $errors
        );

        $this->validateDistribution(
            $release,
            $errors
        );

        return $errors;
    }

    public function isReady(
        Release $release
    ): bool {
        return $this->validateForSubmission(
            $release
        ) === [];
    }

    public function checklist(
        Release $release
    ): array {
        $errors = $this->validateForSubmission(
            $release
        );

        return [
            'ready' => empty($errors),
            'errors' => $errors,
            'checks' => [
                'metadata' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'release.'
                ),

                'artists' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'artists.'
                ),

                'tracks' => !$this->hasErrorsWithPrefix(
                    $errors,
                    'tracks.'
                ),

                'distribution' =>
                    !$this->hasErrorsWithPrefix(
                        $errors,
                        'distribution.'
                    ),
            ],
        ];
    }

    private function validateReleaseMetadata(
        Release $release,
        array &$errors
    ): void {
        if (!trim((string) $release->title)) {
            $errors['release.title'] =
                'Release title is required.';
        }

        if (!trim((string) $release->release_type)) {
            $errors['release.release_type'] =
                'Release type is required.';
        }

        if (!trim((string) $release->catalog_number)) {
            $errors['release.catalog_number'] =
                'Catalogue number is required.';
        }

        if (!$release->digital_release_date) {
            $errors['release.digital_release_date'] =
                'Digital release date is required.';
        } else {
            $releaseDate = Carbon::parse(
                $release->digital_release_date
            )->startOfDay();

            if ($releaseDate->lte(now()->startOfDay())) {
                $errors['release.digital_release_date'] =
                    'Digital release date must be after today.';
            }
        }

        if (!$release->artwork_path) {
            $errors['release.artwork'] =
                'Cover artwork is required.';
        }

        if (!trim((string) $release->language)) {
            $errors['release.language'] =
                'Release language is required.';
        }

        if (!trim((string) $release->primary_genre)) {
            $errors['release.primary_genre'] =
                'Primary genre is required.';
        }
    }

    private function validateArtists(
        Release $release,
        array &$errors
    ): void {
        $primaryArtists = is_array(
            $release->primary_artists
        )
            ? $release->primary_artists
            : [];

        if (empty($primaryArtists)) {
            if (
                !trim(
                    (string)
                    $release->primary_artist_name
                )
            ) {
                $errors['artists.primary'] =
                    'At least one primary artist is required.';
            }

            return;
        }

        foreach (
            $primaryArtists as $index => $artist
        ) {
            if (
                !trim(
                    (string) (
                        $artist['name'] ?? ''
                    )
                )
            ) {
                $number = $index + 1;

                $errors[
                    "artists.primary.{$index}.name"
                ] =
                    "Primary artist {$number} name is required.";
            }
        }

        if (!$release->label_id) {
            $errors['artists.label'] =
                'Label is required.';
        }
    }

    private function validateTracks(
        Release $release,
        array &$errors
    ): void {
        if ($release->tracks->isEmpty()) {
            $errors['tracks.empty'] =
                'Add at least one track.';

            return;
        }

        foreach (
            $release->tracks as $index => $track
        ) {
            $number = $index + 1;

            if (!trim((string) $track->title)) {
                $errors[
                    "tracks.{$track->id}.title"
                ] =
                    "Track {$number} title is required.";
            }

            if (
                !trim(
                    (string)
                    $track->primary_artist_name
                )
            ) {
                $errors[
                    "tracks.{$track->id}.artist"
                ] =
                    "Track {$number} primary artist is required.";
            }

            if (!$track->audio_path) {
                $errors[
                    "tracks.{$track->id}.audio"
                ] =
                    "Track {$number} WAV file is required.";
            }
        }
    }

    private function validateDistribution(
        Release $release,
        array &$errors
    ): void {
        $stores = is_array($release->stores)
            ? array_values(
                array_unique(
                    array_map(
                        'intval',
                        $release->stores
                    )
                )
            )
            : [];

        /*
         * All active stores are the system default.
         * A release created before store selection may use
         * all active stores automatically.
         */
        if (empty($stores)) {
            $stores = DistributionStore::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (empty($stores)) {
            $errors['distribution.stores'] =
                'No active distribution stores are available.';

            return;
        }

        $validStoreCount =
            DistributionStore::query()
                ->where('is_active', true)
                ->whereIn('id', $stores)
                ->count();

        if ($validStoreCount !== count($stores)) {
            $errors['distribution.stores'] =
                'One or more stores are inactive or invalid.';
        }

        $territories = is_array(
            $release->territories
        )
            ? $release->territories
            : [];

        /*
         * Worldwide is the default territory.
         */
        $worldwide = $release->worldwide === null
            ? true
            : (bool) $release->worldwide;

        if (!$worldwide && empty($territories)) {
            $errors['distribution.territories'] =
                'Enable Worldwide or select at least one territory.';
        }
    }

    private function hasErrorsWithPrefix(
        array $errors,
        string $prefix
    ): bool {
        foreach (array_keys($errors) as $key) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
PHP


echo "[4/7] Creating Release Workflow Service..."

cat > app/Services/V2/ReleaseWorkflowService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReleaseWorkflowService
{
    private const TRANSITIONS = [
        'draft' => [
            'submitted',
            'archived',
        ],

        'changes_requested' => [
            'submitted',
            'archived',
        ],

        'rejected' => [
            'draft',
            'submitted',
            'archived',
        ],

        'submitted' => [
            'changes_requested',
            'approved',
            'rejected',
        ],

        'approved' => [
            'processing',
            'rejected',
        ],

        'processing' => [
            'delivered',
            'failed',
        ],

        'delivered' => [
            'live',
            'failed',
        ],

        'live' => [
            'takedown_requested',
        ],

        'takedown_requested' => [
            'taken_down',
            'live',
        ],

        'failed' => [
            'processing',
            'rejected',
        ],

        'taken_down' => [
            'archived',
        ],

        'archived' => [],
    ];

    public function __construct(
        private readonly ReleaseValidationService $validator
    ) {
    }

    public function canTransition(
        Release $release,
        string $newStatus
    ): bool {
        $currentStatus = (string) $release->status;

        return in_array(
            $newStatus,
            self::TRANSITIONS[$currentStatus] ?? [],
            true
        );
    }

    public function submit(
        Release $release,
        User $user,
        array $context = []
    ): Release {
        $errors = $this->validator
            ->validateForSubmission($release);

        if (!empty($errors)) {
            throw ValidationException::withMessages(
                $errors
            );
        }

        return $this->transition(
            $release,
            'submitted',
            $user,
            [
                'action' =>
                    'submitted_for_review',

                'remarks' =>
                    'Release submitted for review.',

                ...$context,
            ]
        );
    }

    public function transition(
        Release $release,
        string $newStatus,
        User $user,
        array $context = []
    ): Release {
        $oldStatus = (string) $release->status;

        abort_unless(
            $this->canTransition(
                $release,
                $newStatus
            ),
            422,
            "Release cannot move from {$oldStatus} to {$newStatus}."
        );

        return DB::transaction(function () use (
            $release,
            $oldStatus,
            $newStatus,
            $user,
            $context
        ) {
            $updates = [
                'status' => $newStatus,
                'updated_by' => $user->id,
            ];

            $this->addStatusTimestamps(
                $updates,
                $newStatus,
                $user
            );

            if ($newStatus === 'submitted') {
                $updates['wizard_step'] = 4;
                $updates[
                    'completion_percentage'
                ] = 100;
                $updates['review_notes'] = null;
                $updates['rejection_reason'] = null;
                $updates['rejected_at'] = null;
                $updates['rejected_by'] = null;
            }

            if ($newStatus === 'changes_requested') {
                $updates['review_notes'] =
                    $context['remarks']
                    ?? $context['notes']
                    ?? null;
            }

            if ($newStatus === 'rejected') {
                $updates['rejection_reason'] =
                    $context['remarks']
                    ?? $context['reason']
                    ?? null;
            }

            $release->update($updates);

            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );

            return $release->fresh();
        });
    }

    public function availableTransitions(
        Release $release
    ): array {
        return self::TRANSITIONS[
            (string) $release->status
        ] ?? [];
    }

    public function isEditable(
        Release $release
    ): bool {
        return in_array(
            $release->status,
            [
                'draft',
                'changes_requested',
                'rejected',
            ],
            true
        );
    }

    private function addStatusTimestamps(
        array &$updates,
        string $status,
        User $user
    ): void {
        $timestampColumns = [
            'submitted' => 'submitted_at',
            'approved' => 'approved_at',
            'rejected' => 'rejected_at',
            'processing' => 'processing_at',
            'delivered' => 'delivered_at',
            'live' => 'live_at',
            'taken_down' => 'taken_down_at',
        ];

        $userColumns = [
            'approved' => 'approved_by',
            'rejected' => 'rejected_by',
        ];

        if (
            isset($timestampColumns[$status])
            && Schema::hasColumn(
                'releases',
                $timestampColumns[$status]
            )
        ) {
            $updates[
                $timestampColumns[$status]
            ] = now();
        }

        if (
            isset($userColumns[$status])
            && Schema::hasColumn(
                'releases',
                $userColumns[$status]
            )
        ) {
            $updates[
                $userColumns[$status]
            ] = $user->id;
        }
    }

    private function writeStatusLog(
        Release $release,
        string $oldStatus,
        string $newStatus,
        User $user,
        array $context
    ): void {
        if (
            !Schema::hasTable(
                'release_status_logs'
            )
        ) {
            return;
        }

        $columns = Schema::getColumnListing(
            'release_status_logs'
        );

        $possibleValues = [
            'public_id' =>
                (string) Str::ulid(),

            'release_id' =>
                $release->id,

            'old_status' =>
                $oldStatus,

            'previous_status' =>
                $oldStatus,

            'from_status' =>
                $oldStatus,

            'new_status' =>
                $newStatus,

            'to_status' =>
                $newStatus,

            'status' =>
                $newStatus,

            'action' =>
                $context['action']
                ?? $newStatus,

            'remarks' =>
                $context['remarks']
                ?? null,

            'notes' =>
                $context['notes']
                ?? $context['remarks']
                ?? null,

            'reason' =>
                $context['reason']
                ?? null,

            'ip_address' =>
                $context['ip_address']
                ?? request()?->ip(),

            'user_agent' =>
                $context['user_agent']
                ?? request()?->userAgent(),

            'changed_by' =>
                $user->id,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,

            'user_id' =>
                $user->id,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ];

        $log = [];

        foreach (
            $possibleValues as $column => $value
        ) {
            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
            ) {
                $log[$column] = $value;
            }
        }

        DB::table(
            'release_status_logs'
        )->insert($log);
    }
}
PHP


echo "[5/7] Running PHP syntax checks..."

php -l app/Services/V2/ReleaseAccessService.php
php -l app/Services/V2/ReleaseValidationService.php
php -l app/Services/V2/ReleaseWorkflowService.php


echo "[6/7] Running Laravel container checks..."

php artisan tinker --execute="
\$services = [
    app(\App\Services\V2\ReleaseAccessService::class),
    app(\App\Services\V2\ReleaseValidationService::class),
    app(\App\Services\V2\ReleaseWorkflowService::class),
];

foreach (\$services as \$service) {
    echo get_class(\$service).PHP_EOL;
}
"


echo "[7/7] Saving installation state..."

printf '{\n  "module": "ReleaseBackendEngine",\n  "installed": true,\n  "version": "2.2.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/release-backend-engine-installed.json

php artisan optimize:clear

echo ""
echo "=================================================="
echo "RELEASE BACKEND ENGINE INSTALLED"
echo "=================================================="

echo ""
echo "Services:"

ls -lah \
app/Services/V2/ReleaseAccessService.php \
app/Services/V2/ReleaseValidationService.php \
app/Services/V2/ReleaseWorkflowService.php

echo ""
echo "State:"

cat \
v2/runtime/state/release-backend-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
