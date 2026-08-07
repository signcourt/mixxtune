#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/contributors-splits-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Models/Distribution \
    v2/runtime/state

echo "=================================================="
echo "V2 CONTRIBUTORS, CREDITS & SPLITS ENGINE"
echo "=================================================="

echo "[1/9] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Permissions/permissions.js \
    app/Models/Distribution/Track.php \
    app/Models/Distribution/TrackSplit.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/9] Creating contributors and splits migration..."

MIGRATION="database/migrations/2026_08_01_000007_create_v2_track_contributors_and_splits_tables.php"

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
        if (!Schema::hasTable('contributors')) {
            Schema::create(
                'contributors',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    )->nullable();

                    $table->string(
                        'name'
                    );

                    $table->string(
                        'legal_name'
                    )->nullable();

                    $table->string(
                        'email'
                    )->nullable();

                    $table->string(
                        'phone',
                        50
                    )->nullable();

                    $table->string(
                        'ipi_number',
                        50
                    )->nullable();

                    $table->string(
                        'isni',
                        50
                    )->nullable();

                    $table->string(
                        'society',
                        100
                    )->nullable();

                    $table->string(
                        'country_code',
                        2
                    )->nullable();

                    $table->json(
                        'platform_ids'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'updated_by'
                    )->nullable();

                    $table->timestamps();
                    $table->softDeletes();

                    $table->index('name');
                    $table->index('ipi_number');
                    $table->index('email');
                }
            );
        }

        if (
            !Schema::hasTable(
                'track_contributors'
            )
        ) {
            Schema::create(
                'track_contributors',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'track_id'
                    );

                    $table->unsignedBigInteger(
                        'contributor_id'
                    );

                    $table->string(
                        'role',
                        100
                    );

                    $table->boolean(
                        'is_primary'
                    )->default(false);

                    $table->unsignedInteger(
                        'sort_order'
                    )->default(0);

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'updated_by'
                    )->nullable();

                    $table->timestamps();
                    $table->softDeletes();

                    $table->unique(
                        [
                            'track_id',
                            'contributor_id',
                            'role',
                        ],
                        'track_contributor_role_unique'
                    );

                    $table->index([
                        'track_id',
                        'role',
                    ]);

                    $table->foreign('track_id')
                        ->references('id')
                        ->on('tracks')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'contributor_id'
                    )
                        ->references('id')
                        ->on('contributors')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('track_splits')) {
            Schema::create(
                'track_splits',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'track_id'
                    );

                    $table->unsignedBigInteger(
                        'contributor_id'
                    )->nullable();

                    $table->string(
                        'recipient_name'
                    );

                    $table->string(
                        'recipient_email'
                    )->nullable();

                    $table->decimal(
                        'percentage',
                        8,
                        4
                    );

                    $table->string(
                        'split_type',
                        50
                    )->default('master');

                    $table->string(
                        'status',
                        30
                    )->default('active');

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'updated_by'
                    )->nullable();

                    $table->timestamps();
                    $table->softDeletes();

                    $table->index([
                        'track_id',
                        'split_type',
                    ]);

                    $table->foreign('track_id')
                        ->references('id')
                        ->on('tracks')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'contributor_id'
                    )
                        ->references('id')
                        ->on('contributors')
                        ->nullOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Production credits and split data should
         * not be removed automatically.
         */
    }
};
PHP
fi


echo "[3/9] Creating contributor models..."

cat > app/Models/Distribution/Contributor.php <<'PHP'
<?php

namespace App\Models\Distribution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contributor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'legal_name',
        'email',
        'phone',
        'ipi_number',
        'isni',
        'society',
        'country_code',
        'platform_ids',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'platform_ids' => 'array',
    ];

    public function tracks()
    {
        return $this->belongsToMany(
            Track::class,
            'track_contributors'
        )
            ->withPivot([
                'public_id',
                'role',
                'is_primary',
                'sort_order',
                'notes',
            ])
            ->withTimestamps();
    }

    public function splits()
    {
        return $this->hasMany(
            TrackSplit::class
        );
    }
}
PHP

cat > app/Models/Distribution/TrackContributor.php <<'PHP'
<?php

namespace App\Models\Distribution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackContributor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'track_id',
        'contributor_id',
        'role',
        'is_primary',
        'sort_order',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function contributor()
    {
        return $this->belongsTo(
            Contributor::class
        );
    }
}
PHP

cat > app/Models/Distribution/TrackSplit.php <<'PHP'
<?php

namespace App\Models\Distribution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackSplit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'track_id',
        'contributor_id',
        'recipient_name',
        'recipient_email',
        'percentage',
        'split_type',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
    ];

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function contributor()
    {
        return $this->belongsTo(
            Contributor::class
        );
    }
}
PHP


echo "[4/9] Updating Track model relationships..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Models/Distribution/Track.php"
)

if not path.exists():
    raise SystemExit(
        "Track model nahi mila."
    )

text = path.read_text()

methods = r'''
    public function contributors()
    {
        return $this->belongsToMany(
            Contributor::class,
            'track_contributors'
        )
            ->withPivot([
                'public_id',
                'role',
                'is_primary',
                'sort_order',
                'notes',
            ])
            ->withTimestamps();
    }

    public function contributorCredits()
    {
        return $this->hasMany(
            TrackContributor::class
        );
    }

    public function splits()
    {
        return $this->hasMany(
            TrackSplit::class
        );
    }

'''

if "public function contributorCredits()" not in text:
    position = text.rfind("}")

    text = (
        text[:position]
        + methods
        + text[position:]
    )

    path.write_text(text)

    print("Track relationships added.")
else:
    print("Track relationships already exist.")
PY


echo "[5/9] Updating permissions..."

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
    "contributors.view",
    "contributors.manage",
    "splits.view",
    "splits.manage",
]

for path in files:
    if not path.exists():
        continue

    text = path.read_text()

    marker = "'releases.update',"

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

        print(f"Updated: {path}")
PY


echo "[6/9] Creating Contributors & Splits Service..."

cat > app/Services/V2/TrackCreditsService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Contributor;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackContributor;
use App\Models\Distribution\TrackSplit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackCreditsService
{
    private const ROLES = [
        'primary_artist',
        'featured_artist',
        'composer',
        'lyricist',
        'producer',
        'music_producer',
        'arranger',
        'performer',
        'remixer',
        'publisher',
        'music_director',
        'vocalist',
        'instrumentalist',
        'engineer',
        'mastering_engineer',
        'mixing_engineer',
        'other',
    ];

    private const SPLIT_TYPES = [
        'master',
        'publishing',
        'performance',
        'mechanical',
    ];

    public function createContributor(
        array $data,
        User $user
    ): Contributor {
        $name = trim(
            (string) (
                $data['name'] ?? ''
            )
        );

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' =>
                    'Contributor name is required.',
            ]);
        }

        return Contributor::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'user_id' =>
                $data['user_id'] ?? null,

            'name' =>
                $name,

            'legal_name' =>
                $data['legal_name'] ?? null,

            'email' =>
                $data['email'] ?? null,

            'phone' =>
                $data['phone'] ?? null,

            'ipi_number' =>
                $data['ipi_number'] ?? null,

            'isni' =>
                $data['isni'] ?? null,

            'society' =>
                $data['society'] ?? null,

            'country_code' =>
                isset($data['country_code'])
                    ? strtoupper(
                        trim(
                            $data['country_code']
                        )
                    )
                    : null,

            'platform_ids' =>
                $data['platform_ids'] ?? [],

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    public function attachContributor(
        Track $track,
        Contributor $contributor,
        string $role,
        User $user,
        array $data = []
    ): TrackContributor {
        $role = strtolower(
            trim($role)
        );

        if (
            !in_array(
                $role,
                self::ROLES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'role' =>
                    'Invalid contributor role.',
            ]);
        }

        $existing =
            TrackContributor::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'contributor_id',
                    $contributor->id
                )
                ->where(
                    'role',
                    $role
                )
                ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'contributor_id' =>
                    'This contributor already has this role on the track.',
            ]);
        }

        return TrackContributor::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'track_id' =>
                $track->id,

            'contributor_id' =>
                $contributor->id,

            'role' =>
                $role,

            'is_primary' =>
                (bool) (
                    $data['is_primary']
                    ?? false
                ),

            'sort_order' =>
                (int) (
                    $data['sort_order']
                    ?? 0
                ),

            'notes' =>
                $data['notes'] ?? null,

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    public function updateContributorCredit(
        TrackContributor $credit,
        array $data,
        User $user
    ): TrackContributor {
        if (isset($data['role'])) {
            $role = strtolower(
                trim($data['role'])
            );

            if (
                !in_array(
                    $role,
                    self::ROLES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'role' =>
                        'Invalid contributor role.',
                ]);
            }

            $duplicate =
                TrackContributor::query()
                    ->where(
                        'track_id',
                        $credit->track_id
                    )
                    ->where(
                        'contributor_id',
                        $credit->contributor_id
                    )
                    ->where(
                        'role',
                        $role
                    )
                    ->where(
                        'id',
                        '!=',
                        $credit->id
                    )
                    ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'role' =>
                        'This contributor already has this role.',
                ]);
            }
        }

        $credit->update([
            'role' =>
                $data['role']
                ?? $credit->role,

            'is_primary' =>
                array_key_exists(
                    'is_primary',
                    $data
                )
                    ? (bool) $data[
                        'is_primary'
                    ]
                    : $credit->is_primary,

            'sort_order' =>
                $data['sort_order']
                ?? $credit->sort_order,

            'notes' =>
                $data['notes']
                ?? $credit->notes,

            'updated_by' =>
                $user->id,
        ]);

        return $credit->fresh([
            'contributor',
        ]);
    }

    public function replaceSplits(
        Track $track,
        string $splitType,
        array $splits,
        User $user
    ): array {
        $splitType = strtolower(
            trim($splitType)
        );

        if (
            !in_array(
                $splitType,
                self::SPLIT_TYPES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'split_type' =>
                    'Invalid split type.',
            ]);
        }

        if (empty($splits)) {
            throw ValidationException::withMessages([
                'splits' =>
                    'At least one split recipient is required.',
            ]);
        }

        $total = 0;

        foreach ($splits as $index => $split) {
            $name = trim(
                (string) (
                    $split[
                        'recipient_name'
                    ] ?? ''
                )
            );

            $percentage = round(
                (float) (
                    $split['percentage']
                    ?? 0
                ),
                4
            );

            if ($name === '') {
                throw ValidationException::withMessages([
                    "splits.{$index}.recipient_name" =>
                        'Recipient name is required.',
                ]);
            }

            if (
                $percentage <= 0
                || $percentage > 100
            ) {
                throw ValidationException::withMessages([
                    "splits.{$index}.percentage" =>
                        'Percentage must be greater than 0 and not exceed 100.',
                ]);
            }

            $total += $percentage;
        }

        if (abs($total - 100) > 0.0001) {
            throw ValidationException::withMessages([
                'splits' =>
                    "Total {$splitType} split must equal 100%. Current total: {$total}%.",
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $splitType,
            $splits,
            $user
        ) {
            TrackSplit::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'split_type',
                    $splitType
                )
                ->delete();

            $created = [];

            foreach ($splits as $split) {
                $created[] =
                    TrackSplit::query()->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'track_id' =>
                            $track->id,

                        'contributor_id' =>
                            $split[
                                'contributor_id'
                            ] ?? null,

                        'recipient_name' =>
                            trim(
                                $split[
                                    'recipient_name'
                                ]
                            ),

                        'recipient_email' =>
                            $split[
                                'recipient_email'
                            ] ?? null,

                        'percentage' =>
                            round(
                                (float) $split[
                                    'percentage'
                                ],
                                4
                            ),

                        'split_type' =>
                            $splitType,

                        'status' =>
                            'active',

                        'notes' =>
                            $split['notes']
                            ?? null,

                        'created_by' =>
                            $user->id,

                        'updated_by' =>
                            $user->id,
                    ]);
            }

            return $created;
        });
    }

    public function validateTrackSplits(
        Track $track
    ): array {
        $errors = [];

        foreach (
            self::SPLIT_TYPES as $splitType
        ) {
            $splits = TrackSplit::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'split_type',
                    $splitType
                )
                ->where(
                    'status',
                    'active'
                )
                ->get();

            if ($splits->isEmpty()) {
                continue;
            }

            $total = round(
                (float) $splits->sum(
                    'percentage'
                ),
                4
            );

            if (abs($total - 100) > 0.0001) {
                $errors[$splitType] =
                    ucfirst($splitType)
                    . " split total must equal 100%. Current total: {$total}%.";
            }
        }

        return $errors;
    }

    public function trackCredits(
        Track $track
    ): array {
        return [
            'contributors' =>
                TrackContributor::query()
                    ->with('contributor')
                    ->where(
                        'track_id',
                        $track->id
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(),

            'splits' =>
                TrackSplit::query()
                    ->with('contributor')
                    ->where(
                        'track_id',
                        $track->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy('split_type')
                    ->orderBy('id')
                    ->get(),

            'validation' =>
                $this->validateTrackSplits(
                    $track
                ),
        ];
    }
}
PHP


echo "[7/9] Creating Credits Controller..."

cat > app/Http/Controllers/V2/TrackCreditsController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Contributor;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackContributor;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseAuditService;
use App\Services\V2\TrackCreditsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackCreditsController extends Controller
{
    public function show(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.view'
        );

        $track->loadMissing('release');

        abort_unless(
            $track->release,
            404,
            'Release not found.'
        );

        $access->authorizeView(
            $request->user(),
            $track->release
        );

        return response()->json([
            'track' => $track,
            ...$credits->trackCredits(
                $track
            ),
        ]);
    }

    public function createContributor(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'ipi_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'isni' => [
                'nullable',
                'string',
                'max:50',
            ],

            'society' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],

            'platform_ids' => [
                'nullable',
                'array',
            ],
        ]);

        $contributor =
            $credits->createContributor(
                $validated,
                $request->user()
            );

        return response()->json([
            'message' =>
                'Contributor created.',

            'contributor' =>
                $contributor,
        ]);
    }

    public function attachContributor(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits,
        ReleaseAuditService $audit
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'contributor_id' => [
                'required',
                'integer',
                'exists:contributors,id',
            ],

            'role' => [
                'required',
                'string',
                'max:100',
            ],

            'is_primary' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $contributor =
            Contributor::query()->findOrFail(
                $validated[
                    'contributor_id'
                ]
            );

        $credit =
            $credits->attachContributor(
                $track,
                $contributor,
                $validated['role'],
                $request->user(),
                $validated
            );

        $audit->log(
            $track->release,
            'track.contributor_added',
            'Contributor added',
            "{$contributor->name} added as {$credit->role}.",
            [
                'category' =>
                    'credits',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $request->user()->id,

                'new_values' => [
                    'contributor_id' =>
                        $contributor->id,

                    'role' =>
                        $credit->role,
                ],
            ]
        );

        return response()->json([
            'message' =>
                'Contributor attached to track.',

            'credit' =>
                $credit->load(
                    'contributor'
                ),
        ]);
    }

    public function updateCredit(
        Request $request,
        TrackContributor $credit,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits
    ): JsonResponse {
        $credit->loadMissing(
            'track.release'
        );

        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $access->authorizeUpdate(
            $request->user(),
            $credit->track->release
        );

        $validated = $request->validate([
            'role' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_primary' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $credit =
            $credits
                ->updateContributorCredit(
                    $credit,
                    $validated,
                    $request->user()
                );

        return response()->json([
            'message' =>
                'Contributor credit updated.',

            'credit' =>
                $credit,
        ]);
    }

    public function deleteCredit(
        Request $request,
        TrackContributor $credit,
        PermissionService $permissions,
        ReleaseAccessService $access
    ): JsonResponse {
        $credit->loadMissing(
            'track.release'
        );

        $permissions->authorize(
            $request->user(),
            'contributors.manage'
        );

        $access->authorizeUpdate(
            $request->user(),
            $credit->track->release
        );

        $credit->update([
            'updated_by' =>
                $request->user()->id,
        ]);

        $credit->delete();

        return response()->json([
            'message' =>
                'Contributor credit deleted.',
        ]);
    }

    public function replaceSplits(
        Request $request,
        Track $track,
        PermissionService $permissions,
        ReleaseAccessService $access,
        TrackCreditsService $credits,
        ReleaseAuditService $audit
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'splits.manage'
        );

        $track->loadMissing('release');

        $access->authorizeUpdate(
            $request->user(),
            $track->release
        );

        $validated = $request->validate([
            'split_type' => [
                'required',
                'string',
                'max:50',
            ],

            'splits' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'splits.*.contributor_id' => [
                'nullable',
                'integer',
                'exists:contributors,id',
            ],

            'splits.*.recipient_name' => [
                'required',
                'string',
                'max:255',
            ],

            'splits.*.recipient_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'splits.*.percentage' => [
                'required',
                'numeric',
                'gt:0',
                'lte:100',
            ],

            'splits.*.notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $splits =
            $credits->replaceSplits(
                $track,
                $validated['split_type'],
                $validated['splits'],
                $request->user()
            );

        $audit->log(
            $track->release,
            'track.splits_updated',
            'Royalty splits updated',
            ucfirst(
                $validated['split_type']
            )
                . ' splits updated for '
                . $track->title
                . '.',
            [
                'category' =>
                    'splits',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $request->user()->id,

                'new_values' => [
                    'split_type' =>
                        $validated[
                            'split_type'
                        ],

                    'splits' =>
                        $validated['splits'],
                ],
            ]
        );

        return response()->json([
            'message' =>
                'Track splits updated.',

            'splits' =>
                $splits,

            'validation' =>
                $credits
                    ->validateTrackSplits(
                        $track
                    ),
        ]);
    }
}
PHP


echo "[8/9] Adding credits and splits routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.track-credits.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/tracks/{track}/credits',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'show']
    )
    ->name('v2.track-credits.show');
""",

    "v2.track-credits.contributors.create": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/tracks/{track}/contributors',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'createContributor']
    )
    ->name('v2.track-credits.contributors.create');
""",

    "v2.track-credits.contributors.attach": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/tracks/{track}/contributors/attach',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'attachContributor']
    )
    ->name('v2.track-credits.contributors.attach');
""",

    "v2.track-credits.credit.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/track-contributors/{credit}',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'updateCredit']
    )
    ->name('v2.track-credits.credit.update');
""",

    "v2.track-credits.credit.delete": r"""
Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/track-contributors/{credit}',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'deleteCredit']
    )
    ->name('v2.track-credits.credit.delete');
""",

    "v2.track-credits.splits.replace": r"""
Route::middleware(['auth', 'verified'])
    ->put(
        '/v2/tracks/{track}/splits',
        [\App\Http\Controllers\V2\TrackCreditsController::class, 'replaceSplits']
    )
    ->name('v2.track-credits.splits.replace');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} credits routes added.")
PY


echo "[9/9] Running migrations and final checks..."

php artisan migrate --force

php -l \
app/Models/Distribution/Contributor.php

php -l \
app/Models/Distribution/TrackContributor.php

php -l \
app/Models/Distribution/TrackSplit.php

php -l \
app/Models/Distribution/Track.php

php -l \
app/Services/V2/TrackCreditsService.php

php -l \
app/Http/Controllers/V2/TrackCreditsController.php

php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
echo get_class(
    app(
        \App\Services\V2\TrackCreditsService::class
    )
).PHP_EOL;
"

printf '{\n  "module": "ContributorsSplitsEngine",\n  "installed": true,\n  "version": "3.0.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/contributors-splits-engine-installed.json

echo ""
echo "===== CREDITS & SPLITS ROUTES ====="

php artisan route:list | grep -E \
"v2/(tracks/.*/credits|tracks/.*/contributors|track-contributors|tracks/.*/splits)"

echo ""
echo "=================================================="
echo "CONTRIBUTORS & SPLITS ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/contributors-splits-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
