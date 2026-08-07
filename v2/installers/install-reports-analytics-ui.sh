#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/reports-analytics-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/Reports \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Reports \
    resources/js/Pages/V2/Admin/Reports \
    storage/app/reports/imports \
    storage/app/reports/errors \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING V2 REPORTS & ANALYTICS"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Config/panelRoutes.js
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/9] Creating reports migration..."

MIGRATION="database/migrations/2026_08_01_000009_create_v2_report_tables.php"

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
        if (!Schema::hasTable('report_imports')) {
            Schema::create(
                'report_imports',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'original_filename'
                    );

                    $table->string(
                        'stored_path'
                    )->nullable();

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->unsignedBigInteger(
                        'total_rows'
                    )->default(0);

                    $table->unsignedBigInteger(
                        'imported_rows'
                    )->default(0);

                    $table->unsignedBigInteger(
                        'duplicate_rows'
                    )->default(0);

                    $table->unsignedBigInteger(
                        'failed_rows'
                    )->default(0);

                    $table->string(
                        'error_file_path'
                    )->nullable();

                    $table->json(
                        'column_map'
                    )->nullable();

                    $table->text(
                        'error_message'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'uploaded_by'
                    )->nullable();

                    $table->timestamp(
                        'started_at'
                    )->nullable();

                    $table->timestamp(
                        'completed_at'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'status',
                        'created_at',
                    ]);
                }
            );
        }

        if (!Schema::hasTable('report_rows')) {
            Schema::create(
                'report_rows',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'row_hash',
                        64
                    )->unique();

                    $table->unsignedBigInteger(
                        'report_import_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'release_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'track_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'artist_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'label_id'
                    )->nullable();

                    $table->string(
                        'track_artist'
                    )->nullable();

                    $table->string(
                        'album_title'
                    )->nullable();

                    $table->string(
                        'album_artist'
                    )->nullable();

                    $table->string(
                        'label_name'
                    )->nullable();

                    $table->string(
                        'track_title'
                    )->nullable();

                    $table->string(
                        'isrc',
                        30
                    )->nullable();

                    $table->string(
                        'upc',
                        30
                    )->nullable();

                    $table->string(
                        'platform',
                        150
                    )->nullable();

                    $table->string(
                        'currency',
                        10
                    )->nullable();

                    $table->string(
                        'country_code',
                        10
                    )->nullable();

                    $table->string(
                        'cms',
                        150
                    )->nullable();

                    $table->string(
                        'sale_type',
                        150
                    )->nullable();

                    $table->date(
                        'sale_date'
                    )->nullable();

                    $table->string(
                        'sale_month',
                        10
                    )->nullable();

                    $table->decimal(
                        'streams',
                        20,
                        4
                    )->default(0);

                    $table->decimal(
                        'sale_units',
                        20,
                        4
                    )->default(0);

                    $table->decimal(
                        'label_rate',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'earnings',
                        20,
                        8
                    )->default(0);

                    $table->json(
                        'raw_data'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'artist_id',
                        'sale_month',
                    ]);

                    $table->index([
                        'label_id',
                        'sale_month',
                    ]);

                    $table->index([
                        'platform',
                        'sale_month',
                    ]);

                    $table->index('isrc');
                    $table->index('upc');
                    $table->index('country_code');
                    $table->index('sale_date');
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial and analytics data should not
         * be deleted automatically.
         */
    }
};
PHP
fi


echo "[2/9] Creating models..."

cat > app/Models/Reports/ReportImport.php <<'PHP'
<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportImport extends Model
{
    protected $fillable = [
        'public_id',
        'original_filename',
        'stored_path',
        'status',
        'total_rows',
        'imported_rows',
        'duplicate_rows',
        'failed_rows',
        'error_file_path',
        'column_map',
        'error_message',
        'uploaded_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'column_map' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(
            ReportRow::class
        );
    }
}
PHP

cat > app/Models/Reports/ReportRow.php <<'PHP'
<?php

namespace App\Models\Reports;

use Illuminate\Database\Eloquent\Model;

class ReportRow extends Model
{
    protected $fillable = [
        'row_hash',
        'report_import_id',
        'release_id',
        'track_id',
        'artist_id',
        'label_id',
        'track_artist',
        'album_title',
        'album_artist',
        'label_name',
        'track_title',
        'isrc',
        'upc',
        'platform',
        'currency',
        'country_code',
        'cms',
        'sale_type',
        'sale_date',
        'sale_month',
        'streams',
        'sale_units',
        'label_rate',
        'earnings',
        'raw_data',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'streams' => 'decimal:4',
        'sale_units' => 'decimal:4',
        'label_rate' => 'decimal:8',
        'earnings' => 'decimal:8',
        'raw_data' => 'array',
    ];

    public function import()
    {
        return $this->belongsTo(
            ReportImport::class,
            'report_import_id'
        );
    }
}
PHP


echo "[3/9] Creating CSV import service..."

cat > app/Services/V2/ReportImportService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Reports\ReportImport;
use App\Models\Reports\ReportRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ReportImportService
{
    private const COLUMN_ALIASES = [
        'track_artist' => [
            'track artist',
            'artist',
            'track_artist',
        ],

        'album_title' => [
            'album title',
            'release title',
            'album_title',
        ],

        'album_artist' => [
            'album artist',
            'album_artist',
        ],

        'label_name' => [
            'label',
            'label name',
            'label_name',
        ],

        'track_title' => [
            'track title',
            'title',
            'track_title',
        ],

        'isrc' => [
            'isrc',
        ],

        'upc' => [
            'upc',
            'ean',
            'barcode',
        ],

        'platform' => [
            'platform',
            'store',
            'global customer name',
            'customer account name',
        ],

        'currency' => [
            'client payment currency',
            'currency',
        ],

        'country_code' => [
            'country/region',
            'country',
            'territory',
        ],

        'cms' => [
            'cms',
        ],

        'sale_type' => [
            'sale type',
            'usage type',
            'content type',
        ],

        'sale_date' => [
            'sale date',
            'date',
            'reporting date',
        ],

        'sale_month' => [
            'sale month',
            'month',
            'reporting month',
        ],

        'streams' => [
            'stream',
            'streams',
        ],

        'sale_units' => [
            'sale units',
            'units',
            'quantity',
        ],

        'label_rate' => [
            'label rate',
            'rate',
        ],

        'earnings' => [
            'earnings',
            'net revenue',
            'revenue',
            'amount',
        ],
    ];

    public function import(
        UploadedFile $file,
        User $user
    ): ReportImport {
        $storedPath = $file->store(
            'reports/imports'
        );

        $import = ReportImport::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'original_filename' =>
                $file->getClientOriginalName(),

            'stored_path' =>
                $storedPath,

            'status' =>
                'processing',

            'uploaded_by' =>
                $user->id,

            'started_at' =>
                now(),
        ]);

        try {
            $this->processFile(
                storage_path(
                    'app/' . $storedPath
                ),
                $import
            );

            $import->update([
                'status' =>
                    $import->failed_rows > 0
                        ? 'completed_with_errors'
                        : 'completed',

                'completed_at' =>
                    now(),
            ]);
        } catch (Throwable $exception) {
            $import->update([
                'status' =>
                    'failed',

                'error_message' =>
                    $exception->getMessage(),

                'completed_at' =>
                    now(),
            ]);

            throw $exception;
        }

        return $import->fresh();
    }

    private function processFile(
        string $path,
        ReportImport $import
    ): void {
        $handle = fopen($path, 'r');

        if (!$handle) {
            throw new \RuntimeException(
                'CSV file could not be opened.'
            );
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);

            throw new \RuntimeException(
                'CSV header row is missing.'
            );
        }

        $headers = array_map(
            fn ($header) =>
                $this->normaliseHeader(
                    (string) $header
                ),
            $headers
        );

        $columnMap =
            $this->resolveColumnMap(
                $headers
            );

        $import->update([
            'column_map' =>
                $columnMap,
        ]);

        $total = 0;
        $imported = 0;
        $duplicates = 0;
        $failed = 0;
        $errorRows = [];

        while (
            ($values = fgetcsv($handle))
            !== false
        ) {
            $total++;

            try {
                $row = [];

                foreach (
                    $headers as $index => $header
                ) {
                    $row[$header] =
                        $values[$index]
                        ?? null;
                }

                $mapped =
                    $this->mapRow(
                        $row,
                        $columnMap
                    );

                $hash =
                    $this->rowHash(
                        $mapped
                    );

                if (
                    ReportRow::query()
                        ->where(
                            'row_hash',
                            $hash
                        )
                        ->exists()
                ) {
                    $duplicates++;

                    continue;
                }

                $this->storeRow(
                    $mapped,
                    $row,
                    $hash,
                    $import
                );

                $imported++;
            } catch (Throwable $exception) {
                $failed++;

                $errorRows[] = [
                    'row_number' =>
                        $total + 1,

                    'message' =>
                        $exception->getMessage(),

                    'raw' =>
                        $values,
                ];
            }

            if ($total % 500 === 0) {
                $import->update([
                    'total_rows' =>
                        $total,

                    'imported_rows' =>
                        $imported,

                    'duplicate_rows' =>
                        $duplicates,

                    'failed_rows' =>
                        $failed,
                ]);
            }
        }

        fclose($handle);

        $errorFilePath = null;

        if (!empty($errorRows)) {
            $errorFilePath =
                'reports/errors/'
                . $import->public_id
                . '-errors.json';

            file_put_contents(
                storage_path(
                    'app/' . $errorFilePath
                ),
                json_encode(
                    $errorRows,
                    JSON_PRETTY_PRINT
                )
            );
        }

        $import->update([
            'total_rows' =>
                $total,

            'imported_rows' =>
                $imported,

            'duplicate_rows' =>
                $duplicates,

            'failed_rows' =>
                $failed,

            'error_file_path' =>
                $errorFilePath,
        ]);
    }

    private function storeRow(
        array $mapped,
        array $raw,
        string $hash,
        ReportImport $import
    ): void {
        $isrc = $mapped['isrc']
            ? strtoupper(
                trim($mapped['isrc'])
            )
            : null;

        $upc = $mapped['upc']
            ? preg_replace(
                '/[^0-9]/',
                '',
                $mapped['upc']
            )
            : null;

        $track = $isrc
            ? Track::query()
                ->where('isrc', $isrc)
                ->first()
            : null;

        $release = $track?->release;

        if (
            !$release
            && $upc
        ) {
            $release = Release::query()
                ->where('upc', $upc)
                ->first();
        }

        ReportRow::query()->create([
            'row_hash' =>
                $hash,

            'report_import_id' =>
                $import->id,

            'release_id' =>
                $release?->id,

            'track_id' =>
                $track?->id,

            'artist_id' =>
                $release?->artist_id,

            'label_id' =>
                $release?->label_id,

            'track_artist' =>
                $mapped['track_artist'],

            'album_title' =>
                $mapped['album_title'],

            'album_artist' =>
                $mapped['album_artist'],

            'label_name' =>
                $mapped['label_name'],

            'track_title' =>
                $mapped['track_title'],

            'isrc' =>
                $isrc,

            'upc' =>
                $upc,

            'platform' =>
                $mapped['platform'],

            'currency' =>
                $mapped['currency'],

            'country_code' =>
                $mapped['country_code'],

            'cms' =>
                $mapped['cms'],

            'sale_type' =>
                $mapped['sale_type'],

            'sale_date' =>
                $this->parseDate(
                    $mapped['sale_date']
                ),

            'sale_month' =>
                $this->parseMonth(
                    $mapped['sale_month'],
                    $mapped['sale_date']
                ),

            'streams' =>
                $this->number(
                    $mapped['streams']
                ),

            'sale_units' =>
                $this->number(
                    $mapped['sale_units']
                ),

            'label_rate' =>
                $this->number(
                    $mapped['label_rate']
                ),

            'earnings' =>
                $this->number(
                    $mapped['earnings']
                ),

            'raw_data' =>
                $raw,
        ]);
    }

    private function mapRow(
        array $row,
        array $columnMap
    ): array {
        $mapped = [];

        foreach (
            array_keys(
                self::COLUMN_ALIASES
            ) as $field
        ) {
            $header =
                $columnMap[$field]
                ?? null;

            $mapped[$field] =
                $header
                    ? trim(
                        (string) (
                            $row[$header]
                            ?? ''
                        )
                    )
                    : null;
        }

        return $mapped;
    }

    private function resolveColumnMap(
        array $headers
    ): array {
        $map = [];

        foreach (
            self::COLUMN_ALIASES
            as $field => $aliases
        ) {
            foreach ($aliases as $alias) {
                $normalised =
                    $this->normaliseHeader(
                        $alias
                    );

                if (
                    in_array(
                        $normalised,
                        $headers,
                        true
                    )
                ) {
                    $map[$field] =
                        $normalised;

                    break;
                }
            }
        }

        return $map;
    }

    private function rowHash(
        array $row
    ): string {
        return hash(
            'sha256',
            implode('|', [
                $row['isrc'] ?? '',
                $row['upc'] ?? '',
                $row['platform'] ?? '',
                $row['country_code'] ?? '',
                $row['sale_type'] ?? '',
                $row['sale_date'] ?? '',
                $row['sale_month'] ?? '',
                $row['streams'] ?? '',
                $row['sale_units'] ?? '',
                $row['earnings'] ?? '',
            ])
        );
    }

    private function normaliseHeader(
        string $header
    ): string {
        $header = trim(
            strtolower(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $header
                )
            )
        );

        return str_replace(
            [
                '_',
                '-',
            ],
            ' ',
            $header
        );
    }

    private function parseDate(
        ?string $value
    ): ?string {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse(
                $value
            )->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseMonth(
        ?string $month,
        ?string $date
    ): ?string {
        $value = $month ?: $date;

        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse(
                $value
            )->format('Y-m');
        } catch (Throwable) {
            return trim($month ?: '');
        }
    }

    private function number(
        mixed $value
    ): float {
        if ($value === null) {
            return 0;
        }

        return (float) preg_replace(
            '/[^0-9.\-]/',
            '',
            (string) $value
        );
    }
}
PHP


echo "[4/9] Creating report analytics service..."

cat > app/Services/V2/ReportAnalyticsService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Reports\ReportRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    public function scopedQuery(
        User $user,
        PermissionService $permissions
    ): Builder {
        $role = $permissions->role(
            $user
        );

        $query =
            ReportRow::query();

        if ($role === 'super_admin') {
            return $query;
        }

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $artistId
                ? $query->where(
                    'artist_id',
                    $artistId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $labelId
                ? $query->where(
                    'label_id',
                    $labelId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'admin') {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            return $query->whereIn(
                'artist_id',
                $artistIds
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function applyFilters(
        Builder $query,
        array $filters
    ): Builder {
        if (!empty($filters['month'])) {
            $query->where(
                'sale_month',
                $filters['month']
            );
        }

        if (!empty($filters['platform'])) {
            $query->where(
                'platform',
                $filters['platform']
            );
        }

        if (!empty($filters['country'])) {
            $query->where(
                'country_code',
                $filters['country']
            );
        }

        if (!empty($filters['isrc'])) {
            $query->where(
                'isrc',
                'like',
                '%' . $filters['isrc'] . '%'
            );
        }

        if (!empty($filters['search'])) {
            $search =
                $filters['search'];

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'track_title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'track_artist',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'album_title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'isrc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'upc',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        return $query;
    }

    public function summary(
        Builder $query
    ): array {
        $result = (clone $query)
            ->selectRaw(
                'COALESCE(SUM(streams), 0) as streams'
            )
            ->selectRaw(
                'COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->selectRaw(
                'COALESCE(SUM(earnings), 0) as earnings'
            )
            ->selectRaw(
                'COUNT(*) as rows_count'
            )
            ->first();

        return [
            'streams' =>
                (float) $result->streams,

            'sale_units' =>
                (float) $result->sale_units,

            'earnings' =>
                (float) $result->earnings,

            'rows' =>
                (int) $result->rows_count,
        ];
    }
}
PHP


echo "[5/9] Creating Reports Controller..."

cat > app/Http/Controllers/V2/ReportController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        ReportAnalyticsService $analytics
    ): Response {
        $permissions->authorize(
            $request->user(),
            'reports.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'month' => trim(
                (string) $request->input(
                    'month',
                    ''
                )
            ),

            'platform' => trim(
                (string) $request->input(
                    'platform',
                    ''
                )
            ),

            'country' => trim(
                (string) $request->input(
                    'country',
                    ''
                )
            ),

            'isrc' => trim(
                (string) $request->input(
                    'isrc',
                    ''
                )
            ),
        ];

        $base = $analytics->scopedQuery(
            $request->user(),
            $permissions
        );

        $query = $analytics->applyFilters(
            clone $base,
            $filters
        );

        $platforms = (clone $base)
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        $months = (clone $base)
            ->whereNotNull('sale_month')
            ->distinct()
            ->orderByDesc('sale_month')
            ->pluck('sale_month');

        $countries = (clone $base)
            ->whereNotNull('country_code')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code');

        return Inertia::render(
            'V2/Reports/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'filters' =>
                    $filters,

                'summary' =>
                    $analytics->summary(
                        $query
                    ),

                'platforms' =>
                    $platforms,

                'months' =>
                    $months,

                'countries' =>
                    $countries,

                'rows' =>
                    $query
                        ->orderByDesc('sale_date')
                        ->orderByDesc('id')
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }
}
PHP


echo "[6/9] Creating Admin Import Controller..."

cat > app/Http/Controllers/V2/Admin/ReportImportController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\ReportImport;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportImportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        return Inertia::render(
            'V2/Admin/Reports/Imports',
            [
                'role' => $role,

                'imports' =>
                    ReportImport::query()
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions,
        ReportImportService $importer
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = $request->validate([
            'report_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:512000',
            ],
        ]);

        $import = $importer->import(
            $validated['report_file'],
            $request->user()
        );

        return back()->with(
            'success',
            "Report import completed. Imported: {$import->imported_rows}, duplicates: {$import->duplicate_rows}, failed: {$import->failed_rows}."
        );
    }
}
PHP


echo "[7/9] Creating frontend pages..."

cat > resources/js/Pages/V2/Reports/Index.jsx <<'JSX'
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    filters = {},
    summary = {},
    rows = {},
    platforms = [],
    months = [],
    countries = [],
}) {
    const data = rows.data ?? [];

    const update = (changes) => {
        router.get(
            '/v2/reports',
            {
                ...filters,
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Reports"
            subtitle="Streams, sales and earnings analytics"
        >
            <Head title="Reports" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card
                        label="Streams"
                        value={summary.streams ?? 0}
                    />

                    <Card
                        label="Sale Units"
                        value={summary.sale_units ?? 0}
                    />

                    <Card
                        label="Earnings"
                        value={Number(
                            summary.earnings ?? 0
                        ).toFixed(2)}
                    />

                    <Card
                        label="Report Rows"
                        value={summary.rows ?? 0}
                    />
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <input
                            type="search"
                            defaultValue={
                                filters.search ?? ''
                            }
                            placeholder="Track, artist, UPC..."
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Enter'
                                ) {
                                    update({
                                        search:
                                            event.currentTarget
                                                .value,
                                    });
                                }
                            }}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm xl:col-span-2"
                        />

                        <select
                            value={filters.month ?? ''}
                            onChange={(event) =>
                                update({
                                    month:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Months
                            </option>

                            {months.map((month) => (
                                <option
                                    key={month}
                                    value={month}
                                >
                                    {month}
                                </option>
                            ))}
                        </select>

                        <select
                            value={
                                filters.platform ?? ''
                            }
                            onChange={(event) =>
                                update({
                                    platform:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Platforms
                            </option>

                            {platforms.map(
                                (platform) => (
                                    <option
                                        key={platform}
                                        value={platform}
                                    >
                                        {platform}
                                    </option>
                                )
                            )}
                        </select>

                        <select
                            value={
                                filters.country ?? ''
                            }
                            onChange={(event) =>
                                update({
                                    country:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Countries
                            </option>

                            {countries.map(
                                (country) => (
                                    <option
                                        key={country}
                                        value={country}
                                    >
                                        {country}
                                    </option>
                                )
                            )}
                        </select>

                        <button
                            type="button"
                            onClick={() =>
                                router.get('/v2/reports')
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700"
                        >
                            Clear
                        </button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Month',
                                        'Track',
                                        'Artist',
                                        'ISRC',
                                        'Platform',
                                        'Country',
                                        'Streams',
                                        'Units',
                                        'Earnings',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {data.length > 0 ? (
                                    data.map((row) => (
                                        <tr key={row.id}>
                                            <Cell>
                                                {row.sale_month ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.track_title ||
                                                    row.album_title ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.track_artist ||
                                                    row.album_artist ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.isrc || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.platform ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.country_code ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.streams}
                                            </Cell>

                                            <Cell>
                                                {row.sale_units}
                                            </Cell>

                                            <Cell>
                                                {row.earnings}
                                            </Cell>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="9"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No report data found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {rows.links && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {rows.links.map(
                            (link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={[
                                        'rounded-lg border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-violet-600 bg-violet-600 text-white'
                                            : 'border-slate-300 bg-white text-slate-700',
                                        !link.url
                                            ? 'pointer-events-none opacity-40'
                                            : '',
                                    ].join(' ')}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function Card({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Cell({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/Reports/Imports.jsx <<'JSX'
import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Imports({
    role = 'admin',
    imports = {},
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        report_file: null,
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            '/v2/admin/reports/imports',
            {
                forceFormData: true,

                onSuccess: () => {
                    reset();
                },
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Report Imports"
            subtitle="Upload DSP CSV reports"
        >
            <Head title="Report Imports" />

            <div className="space-y-6">
                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 className="text-lg font-semibold text-slate-900">
                        Upload Report CSV
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Maximum file size: 500 MB.
                    </p>

                    <input
                        type="file"
                        accept=".csv,.txt"
                        onChange={(event) =>
                            setData(
                                'report_file',
                                event.target.files?.[0] ??
                                    null
                            )
                        }
                        className="mt-5 block w-full rounded-xl border border-slate-300 p-3 text-sm"
                    />

                    {errors.report_file && (
                        <div className="mt-2 text-sm text-red-600">
                            {errors.report_file}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={
                            processing ||
                            !data.report_file
                        }
                        className="mt-5 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Importing...'
                            : 'Upload & Import'}
                    </button>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'File',
                                    'Status',
                                    'Total',
                                    'Imported',
                                    'Duplicate',
                                    'Failed',
                                    'Uploaded',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(imports.data ?? []).map(
                                (item) => (
                                    <tr key={item.id}>
                                        <Cell>
                                            {item.original_filename}
                                        </Cell>

                                        <Cell>
                                            {item.status}
                                        </Cell>

                                        <Cell>
                                            {item.total_rows}
                                        </Cell>

                                        <Cell>
                                            {item.imported_rows}
                                        </Cell>

                                        <Cell>
                                            {item.duplicate_rows}
                                        </Cell>

                                        <Cell>
                                            {item.failed_rows}
                                        </Cell>

                                        <Cell>
                                            {item.created_at}
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX


echo "[8/9] Adding routes and permissions..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.reports.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/reports',
        [\App\Http\Controllers\V2\ReportController::class, 'index']
    )
    ->name('v2.reports.index');
""",

    "v2.admin.reports.imports.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/reports/imports',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'index']
    )
    ->name('v2.admin.reports.imports.index');
""",

    "v2.admin.reports.imports.store": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/reports/imports',
        [\App\Http\Controllers\V2\Admin\ReportImportController::class, 'store']
    )
    ->name('v2.admin.reports.imports.store');
""",
}

for name, route in routes.items():
    if name not in text:
        text += "\n" + route

path.write_text(text)
print("Report routes processed.")
PY


echo "[9/9] Running migrations and checks..."

php artisan migrate --force

php -l app/Models/Reports/ReportImport.php
php -l app/Models/Reports/ReportRow.php
php -l app/Services/V2/ReportImportService.php
php -l app/Services/V2/ReportAnalyticsService.php
php -l app/Http/Controllers/V2/ReportController.php
php -l app/Http/Controllers/V2/Admin/ReportImportController.php
php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "ReportsAnalyticsUI",\n  "installed": true,\n  "version": "4.0.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/reports-analytics-ui-installed.json

echo ""
echo "===== REPORT ROUTES ====="

php artisan route:list | grep -E \
"v2/(admin/reports/imports|reports)"

echo ""
echo "=============================================="
echo "REPORTS & ANALYTICS INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/reports-analytics-ui-installed.json

echo ""
echo "Open Reports:"
echo "https://artist.mixxtune.com/v2/reports"
echo "https://admin.mixxtune.com/v2/reports"

echo ""
echo "Import Reports:"
echo "https://admin.mixxtune.com/v2/admin/reports/imports"

echo ""
echo "Backup:"
echo "$BACKUP"
