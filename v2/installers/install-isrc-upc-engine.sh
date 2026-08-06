#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/isrc-upc-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2/Admin \
    v2/runtime/state

echo "=================================================="
echo "V2 ISRC + UPC ASSIGNMENT ENGINE"
echo "=================================================="

echo "[1/10] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Permissions/permissions.js \
    app/Models/Distribution/Release.php \
    app/Models/Distribution/Track.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/10] Creating database migration..."

MIGRATION="database/migrations/2026_08_01_000003_create_v2_identifier_codes_tables.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('isrc_codes')) {
            Schema::create(
                'isrc_codes',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'code',
                        20
                    )->unique();

                    $table->string(
                        'country_code',
                        2
                    );

                    $table->string(
                        'registrant_code',
                        3
                    );

                    $table->unsignedSmallInteger(
                        'reference_year'
                    );

                    $table->unsignedInteger(
                        'designation_code'
                    );

                    $table->string(
                        'status',
                        30
                    )->default('available');

                    $table->unsignedBigInteger(
                        'track_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'assigned_by'
                    )->nullable();

                    $table->timestamp(
                        'assigned_at'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'status',
                        'reference_year',
                    ]);

                    $table->index('track_id');
                }
            );
        }

        if (!Schema::hasTable('upc_codes')) {
            Schema::create(
                'upc_codes',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'code',
                        20
                    )->unique();

                    $table->string(
                        'prefix',
                        12
                    )->nullable();

                    $table->string(
                        'status',
                        30
                    )->default('available');

                    $table->unsignedBigInteger(
                        'release_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'assigned_by'
                    )->nullable();

                    $table->timestamp(
                        'assigned_at'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamps();

                    $table->index('status');
                    $table->index('release_id');
                }
            );
        }

        if (
            !Schema::hasTable(
                'identifier_assignment_logs'
            )
        ) {
            Schema::create(
                'identifier_assignment_logs',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'identifier_type',
                        20
                    );

                    $table->string(
                        'identifier_code',
                        30
                    );

                    $table->string(
                        'action',
                        50
                    );

                    $table->unsignedBigInteger(
                        'release_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'track_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'performed_by'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->string(
                        'ip_address',
                        64
                    )->nullable();

                    $table->text(
                        'user_agent'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'identifier_type',
                        'identifier_code',
                    ]);

                    $table->index('release_id');
                    $table->index('track_id');
                }
            );
        }

        Schema::table(
            'tracks',
            function (Blueprint $table) {
                if (
                    !Schema::hasColumn(
                        'tracks',
                        'isrc_assigned_at'
                    )
                ) {
                    $table->timestamp(
                        'isrc_assigned_at'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'tracks',
                        'isrc_assigned_by'
                    )
                ) {
                    $table->unsignedBigInteger(
                        'isrc_assigned_by'
                    )->nullable();
                }
            }
        );

        Schema::table(
            'releases',
            function (Blueprint $table) {
                if (
                    !Schema::hasColumn(
                        'releases',
                        'upc_assigned_at'
                    )
                ) {
                    $table->timestamp(
                        'upc_assigned_at'
                    )->nullable();
                }

                if (
                    !Schema::hasColumn(
                        'releases',
                        'upc_assigned_by'
                    )
                ) {
                    $table->unsignedBigInteger(
                        'upc_assigned_by'
                    )->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        /*
         * Identifier records may already be assigned.
         * Do not automatically drop production data.
         */
    }
};
PHP
fi


echo "[3/10] Creating identifier models..."

cat > app/Models/IsrcCode.php <<'PHP'
<?php

namespace App\Models;

use App\Models\Distribution\Track;
use Illuminate\Database\Eloquent\Model;

class IsrcCode extends Model
{
    protected $fillable = [
        'public_id',
        'code',
        'country_code',
        'registrant_code',
        'reference_year',
        'designation_code',
        'status',
        'track_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'designation_code' => 'integer',
        'assigned_at' => 'datetime',
    ];

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }
}
PHP

cat > app/Models/UpcCode.php <<'PHP'
<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;

class UpcCode extends Model
{
    protected $fillable = [
        'public_id',
        'code',
        'prefix',
        'status',
        'release_id',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }
}
PHP


echo "[4/10] Updating permissions..."

python3 - <<'PY'
from pathlib import Path

files = [
    Path(
        "app/Services/V2/PermissionService.php"
    ),
    Path(
        "resources/js/V2/Shared/Permissions/permissions.js"
    ),
]

permissions = [
    "identifiers.view",
    "identifiers.assign",
    "identifiers.generate",
    "identifiers.bulk_assign",
    "identifiers.unassign",
]

for path in files:
    if not path.exists():
        continue

    text = path.read_text()

    marker = "'delivery.manage',"

    if marker not in text:
        marker = "'releases.reject',"

    additions = []

    for permission in permissions:
        token = f"'{permission}',"

        if token not in text:
            additions.append(
                "            " + token
            )

    if additions and marker in text:
        text = text.replace(
            marker,
            marker + "\n" + "\n".join(additions),
            1
        )

        path.write_text(text)

        print(f"Updated permissions: {path}")
PY


echo "[5/10] Creating ISRC service..."

cat > app/Services/V2/IsrcService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Track;
use App\Models\IsrcCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IsrcService
{
    public function normalise(
        string $code
    ): string {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim($code)
            )
        );
    }

    public function format(
        string $code
    ): string {
        $code = $this->normalise($code);

        if (strlen($code) !== 12) {
            return $code;
        }

        return sprintf(
            '%s-%s-%s-%s',
            substr($code, 0, 2),
            substr($code, 2, 3),
            substr($code, 5, 2),
            substr($code, 7, 5)
        );
    }

    public function validate(
        string $code
    ): string {
        $normalised = $this->normalise(
            $code
        );

        if (
            !preg_match(
                '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/',
                $normalised
            )
        ) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'ISRC must contain 12 valid characters.',
            ]);
        }

        return $this->format($normalised);
    }

    public function assignManual(
        Track $track,
        string $code,
        User $user,
        ?string $notes = null
    ): Track {
        $formatted = $this->validate($code);

        $duplicate = Track::query()
            ->where('isrc', $formatted)
            ->where('id', '!=', $track->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'This ISRC is already assigned to another track.',
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $formatted,
            $user,
            $notes
        ) {
            $track->update([
                'isrc' => $formatted,
                'isrc_is_auto_generated' =>
                    false,
                'isrc_assigned_at' => now(),
                'isrc_assigned_by' =>
                    $user->id,
                'updated_by' => $user->id,
            ]);

            IsrcCode::query()->updateOrCreate(
                [
                    'code' => $formatted,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'country_code' =>
                        substr(
                            $this->normalise(
                                $formatted
                            ),
                            0,
                            2
                        ),

                    'registrant_code' =>
                        substr(
                            $this->normalise(
                                $formatted
                            ),
                            2,
                            3
                        ),

                    'reference_year' =>
                        (int) (
                            '20'
                            . substr(
                                $this->normalise(
                                    $formatted
                                ),
                                5,
                                2
                            )
                        ),

                    'designation_code' =>
                        (int) substr(
                            $this->normalise(
                                $formatted
                            ),
                            7,
                            5
                        ),

                    'status' => 'assigned',
                    'track_id' => $track->id,
                    'assigned_by' =>
                        $user->id,
                    'assigned_at' => now(),
                    'notes' => $notes,
                ]
            );

            $this->log(
                $formatted,
                'manual_assigned',
                $user,
                $track,
                $notes
            );

            return $track->fresh();
        });
    }

    public function generate(
        Track $track,
        User $user,
        string $countryCode = 'IN',
        string $registrantCode = 'MXT',
        ?int $year = null,
        ?string $notes = null
    ): Track {
        if ($track->isrc) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'This track already has an ISRC.',
            ]);
        }

        $countryCode = strtoupper(
            trim($countryCode)
        );

        $registrantCode = strtoupper(
            trim($registrantCode)
        );

        $year = $year ?: (int) date('Y');

        if (
            !preg_match(
                '/^[A-Z]{2}$/',
                $countryCode
            )
        ) {
            throw ValidationException::withMessages([
                'country_code' =>
                    'Country code must contain 2 letters.',
            ]);
        }

        if (
            !preg_match(
                '/^[A-Z0-9]{3}$/',
                $registrantCode
            )
        ) {
            throw ValidationException::withMessages([
                'registrant_code' =>
                    'Registrant code must contain 3 characters.',
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $user,
            $countryCode,
            $registrantCode,
            $year,
            $notes
        ) {
            $lastDesignation =
                IsrcCode::query()
                    ->where(
                        'country_code',
                        $countryCode
                    )
                    ->where(
                        'registrant_code',
                        $registrantCode
                    )
                    ->where(
                        'reference_year',
                        $year
                    )
                    ->lockForUpdate()
                    ->max(
                        'designation_code'
                    );

            $designation =
                ((int) $lastDesignation) + 1;

            if ($designation > 99999) {
                throw ValidationException::withMessages([
                    'isrc' =>
                        'ISRC yearly sequence limit reached.',
                ]);
            }

            $code = sprintf(
                '%s-%s-%s-%05d',
                $countryCode,
                $registrantCode,
                substr(
                    (string) $year,
                    -2
                ),
                $designation
            );

            $record = IsrcCode::query()->create([
                'public_id' =>
                    (string) Str::ulid(),

                'code' => $code,

                'country_code' =>
                    $countryCode,

                'registrant_code' =>
                    $registrantCode,

                'reference_year' =>
                    $year,

                'designation_code' =>
                    $designation,

                'status' =>
                    'assigned',

                'track_id' =>
                    $track->id,

                'assigned_by' =>
                    $user->id,

                'assigned_at' =>
                    now(),

                'notes' =>
                    $notes,
            ]);

            $track->update([
                'isrc' => $record->code,
                'isrc_is_auto_generated' =>
                    true,
                'isrc_assigned_at' =>
                    now(),
                'isrc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            $this->log(
                $record->code,
                'auto_generated',
                $user,
                $track,
                $notes
            );

            return $track->fresh();
        });
    }

    private function log(
        string $code,
        string $action,
        User $user,
        Track $track,
        ?string $notes
    ): void {
        if (
            !Schema::hasTable(
                'identifier_assignment_logs'
            )
        ) {
            return;
        }

        DB::table(
            'identifier_assignment_logs'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'identifier_type' =>
                'isrc',

            'identifier_code' =>
                $code,

            'action' =>
                $action,

            'release_id' =>
                $track->release_id,

            'track_id' =>
                $track->id,

            'performed_by' =>
                $user->id,

            'notes' =>
                $notes,

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }
}
PHP


echo "[6/10] Creating UPC service..."

cat > app/Services/V2/UpcService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\UpcCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpcService
{
    public function normalise(
        string $code
    ): string {
        return preg_replace(
            '/[^0-9]/',
            '',
            trim($code)
        );
    }

    public function validate(
        string $code
    ): string {
        $code = $this->normalise($code);

        if (
            !in_array(
                strlen($code),
                [12, 13],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'upc' =>
                    'UPC/EAN must contain 12 or 13 digits.',
            ]);
        }

        if (!$this->hasValidCheckDigit($code)) {
            throw ValidationException::withMessages([
                'upc' =>
                    'UPC/EAN check digit is invalid.',
            ]);
        }

        return $code;
    }

    public function assignManual(
        Release $release,
        string $code,
        User $user,
        ?string $notes = null
    ): Release {
        $code = $this->validate($code);

        $duplicate = Release::query()
            ->where('upc', $code)
            ->where('id', '!=', $release->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'upc' =>
                    'This UPC is already assigned to another release.',
            ]);
        }

        return DB::transaction(function () use (
            $release,
            $code,
            $user,
            $notes
        ) {
            $release->update([
                'upc' => $code,
                'upc_assigned_at' => now(),
                'upc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            UpcCode::query()->updateOrCreate(
                [
                    'code' => $code,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'prefix' =>
                        substr($code, 0, 6),

                    'status' =>
                        'assigned',

                    'release_id' =>
                        $release->id,

                    'assigned_by' =>
                        $user->id,

                    'assigned_at' =>
                        now(),

                    'notes' =>
                        $notes,
                ]
            );

            $this->log(
                $code,
                'manual_assigned',
                $user,
                $release,
                $notes
            );

            return $release->fresh();
        });
    }

    public function generate(
        Release $release,
        User $user,
        string $prefix = '890000',
        ?string $notes = null
    ): Release {
        if ($release->upc) {
            throw ValidationException::withMessages([
                'upc' =>
                    'This release already has a UPC.',
            ]);
        }

        $prefix = $this->normalise(
            $prefix
        );

        if (
            strlen($prefix) < 6
            || strlen($prefix) > 10
        ) {
            throw ValidationException::withMessages([
                'prefix' =>
                    'UPC prefix must contain 6 to 10 digits.',
            ]);
        }

        return DB::transaction(function () use (
            $release,
            $user,
            $prefix,
            $notes
        ) {
            $last = UpcCode::query()
                ->where(
                    'prefix',
                    $prefix
                )
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('code');

            $bodyLength = 11;

            $lastSequence = $last
                ? (int) substr(
                    $last,
                    strlen($prefix),
                    $bodyLength
                        - strlen($prefix)
                )
                : 0;

            $sequence =
                $lastSequence + 1;

            $sequenceLength =
                $bodyLength
                - strlen($prefix);

            $body =
                $prefix
                . str_pad(
                    (string) $sequence,
                    $sequenceLength,
                    '0',
                    STR_PAD_LEFT
                );

            $code =
                $body
                . $this->calculateCheckDigit(
                    $body
                );

            $record = UpcCode::query()->create([
                'public_id' =>
                    (string) Str::ulid(),

                'code' =>
                    $code,

                'prefix' =>
                    $prefix,

                'status' =>
                    'assigned',

                'release_id' =>
                    $release->id,

                'assigned_by' =>
                    $user->id,

                'assigned_at' =>
                    now(),

                'notes' =>
                    $notes,
            ]);

            $release->update([
                'upc' => $record->code,
                'upc_assigned_at' => now(),
                'upc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            $this->log(
                $record->code,
                'auto_generated',
                $user,
                $release,
                $notes
            );

            return $release->fresh();
        });
    }

    private function calculateCheckDigit(
        string $body
    ): int {
        $sum = 0;

        foreach (
            str_split($body) as $index => $digit
        ) {
            $position = $index + 1;

            $sum +=
                (int) $digit
                * (
                    $position % 2 === 1
                        ? 3
                        : 1
                );
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function hasValidCheckDigit(
        string $code
    ): bool {
        $body = substr($code, 0, -1);
        $checkDigit =
            (int) substr($code, -1);

        return $this->calculateCheckDigit(
            $body
        ) === $checkDigit;
    }

    private function log(
        string $code,
        string $action,
        User $user,
        Release $release,
        ?string $notes
    ): void {
        if (
            !Schema::hasTable(
                'identifier_assignment_logs'
            )
        ) {
            return;
        }

        DB::table(
            'identifier_assignment_logs'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'identifier_type' =>
                'upc',

            'identifier_code' =>
                $code,

            'action' =>
                $action,

            'release_id' =>
                $release->id,

            'track_id' =>
                null,

            'performed_by' =>
                $user->id,

            'notes' =>
                $notes,

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }
}
PHP


echo "[7/10] Creating Admin Identifier Controller..."

cat > app/Http/Controllers/V2/Admin/IdentifierController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Services\V2\IsrcService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\UpcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentifierController extends Controller
{
    public function pending(
        Request $request,
        PermissionService $permissions
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.view'
        );

        $trackQuery = Track::query()
            ->with([
                'release:id,title,status,artist_id,label_id',
            ])
            ->whereNull('isrc')
            ->whereNull('deleted_at');

        $releaseQuery = Release::query()
            ->whereNull('upc')
            ->whereNull('deleted_at');

        return response()->json([
            'pending_isrc' =>
                $trackQuery
                    ->orderBy('id')
                    ->paginate(
                        50,
                        ['*'],
                        'isrc_page'
                    ),

            'pending_upc' =>
                $releaseQuery
                    ->orderBy('id')
                    ->paginate(
                        50,
                        ['*'],
                        'upc_page'
                    ),
        ]);
    }

    public function assignIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->assignManual(
            $track,
            $validated['isrc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'ISRC assigned successfully.',

            'track' => $track,
        ]);
    }

    public function generateIsrc(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $track->loadMissing('release');

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $track = $isrc->generate(
            $track,
            $request->user(),
            $validated['country_code']
                ?? 'IN',
            $validated['registrant_code']
                ?? 'MXT',
            $validated['reference_year']
                ?? null,
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'ISRC generated successfully.',

            'track' => $track,
        ]);
    }

    public function assignUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.assign'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'upc' => [
                'required',
                'string',
                'max:20',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->assignManual(
            $release,
            $validated['upc'],
            $request->user(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' =>
                'UPC assigned successfully.',

            'release' => $release,
        ]);
    }

    public function generateUpc(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.generate'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $upc->generate(
            $release,
            $request->user(),
            $validated['prefix']
                ?? '890000',
            $validated['notes']
                ?? null
        );

        return response()->json([
            'message' =>
                'UPC generated successfully.',

            'release' => $release,
        ]);
    }

    public function bulkGenerateIsrc(
        Request $request,
        PermissionService $permissions,
        IsrcService $isrc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'track_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'track_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:tracks,id',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'registrant_code' => [
                'nullable',
                'string',
                'size:3',
            ],

            'reference_year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ]);

        $tracks = Track::query()
            ->whereIn(
                'id',
                $validated['track_ids']
            )
            ->whereNull('isrc')
            ->get();

        $assigned = [];

        foreach ($tracks as $track) {
            $assigned[] = $isrc->generate(
                $track,
                $request->user(),
                $validated['country_code']
                    ?? 'IN',
                $validated['registrant_code']
                    ?? 'MXT',
                $validated['reference_year']
                    ?? null
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' ISRC codes generated.',

            'tracks' => $assigned,
        ]);
    }

    public function bulkGenerateUpc(
        Request $request,
        PermissionService $permissions,
        UpcService $upc
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'identifiers.bulk_assign'
        );

        $validated = $request->validate([
            'release_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'release_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:releases,id',
            ],

            'prefix' => [
                'nullable',
                'string',
                'min:6',
                'max:10',
            ],
        ]);

        $releases = Release::query()
            ->whereIn(
                'id',
                $validated['release_ids']
            )
            ->whereNull('upc')
            ->get();

        $assigned = [];

        foreach ($releases as $release) {
            $assigned[] = $upc->generate(
                $release,
                $request->user(),
                $validated['prefix']
                    ?? '890000'
            );
        }

        return response()->json([
            'message' =>
                count($assigned)
                . ' UPC codes generated.',

            'releases' => $assigned,
        ]);
    }
}
PHP


echo "[8/10] Adding identifier routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.identifiers.pending": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/identifiers/pending',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'pending']
    )
    ->name('v2.admin.identifiers.pending');
""",

    "v2.admin.identifiers.isrc.assign": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/isrc/assign',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'assignIsrc']
    )
    ->name('v2.admin.identifiers.isrc.assign');
""",

    "v2.admin.identifiers.isrc.generate": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/tracks/{track}/isrc/generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'generateIsrc']
    )
    ->name('v2.admin.identifiers.isrc.generate');
""",

    "v2.admin.identifiers.upc.assign": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/upc/assign',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'assignUpc']
    )
    ->name('v2.admin.identifiers.upc.assign');
""",

    "v2.admin.identifiers.upc.generate": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/upc/generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'generateUpc']
    )
    ->name('v2.admin.identifiers.upc.generate');
""",

    "v2.admin.identifiers.isrc.bulk": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/identifiers/isrc/bulk-generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'bulkGenerateIsrc']
    )
    ->name('v2.admin.identifiers.isrc.bulk');
""",

    "v2.admin.identifiers.upc.bulk": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/identifiers/upc/bulk-generate',
        [\App\Http\Controllers\V2\Admin\IdentifierController::class, 'bulkGenerateUpc']
    )
    ->name('v2.admin.identifiers.upc.bulk');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} identifier routes added.")
PY


echo "[9/10] Running migrations and checks..."

php artisan migrate --force

php -l app/Models/IsrcCode.php
php -l app/Models/UpcCode.php
php -l app/Services/V2/IsrcService.php
php -l app/Services/V2/UpcService.php
php -l app/Http/Controllers/V2/Admin/IdentifierController.php
php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
echo get_class(
    app(\App\Services\V2\IsrcService::class)
).PHP_EOL;

echo get_class(
    app(\App\Services\V2\UpcService::class)
).PHP_EOL;
"


echo "[10/10] Saving state..."

printf '{\n  "module": "IsrcUpcEngine",\n  "installed": true,\n  "version": "2.6.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/isrc-upc-engine-installed.json

echo ""
echo "===== IDENTIFIER ROUTES ====="

php artisan route:list | grep -E \
"v2/admin/(identifiers|tracks/.*/isrc|releases/.*/upc)"

echo ""
echo "=================================================="
echo "ISRC + UPC ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/isrc-upc-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
