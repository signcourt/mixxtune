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
use Illuminate\Support\Facades\Storage;
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
            'country / region',
            'country region',
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
            'sales month',
            'sales_month',
            'month of sale',
            'month',
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

        'collected_revenue' => [
            'collected revenue',
            'collected_revenue',
            'gross revenue',
            'gross_revenue',
            'gross earnings',
            'gross_earnings',
            'gross amount',
            'source revenue',
            'source_revenue',
        ],

        'earnings' => [
            'earnings',
            'eranings',
            'earning',
            'net earnings',
            'net revenue',
            'revenue',
            'amount',
        ],
    ];

    public function import(
        UploadedFile $file,
        User $user,
        string $reportingMonth
    ): ReportImport {
        $storedPath = $file->store(
            'reports/imports',
            'local'
        );

        $import = ReportImport::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'original_filename' =>
                $file->getClientOriginalName(),

            'reporting_month' =>
                $reportingMonth,

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
                Storage::disk('local')
                    ->path($storedPath),
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

            Storage::disk('local')->put(
                $errorFilePath,
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

        $upc =
            $this->normaliseUpc(
                $mapped['upc']
            );

        $normalizedIsrc = $isrc
            ? strtoupper(
                preg_replace(
                    '/[^A-Z0-9]/i',
                    '',
                    $isrc
                )
            )
            : null;

        /*
         * AUTHORITATIVE REVENUE OWNERSHIP POLICY
         * ----------------------------------------
         * Revenue is resolved ONLY by normalized ISRC.
         *
         * Formatting differences are ignored:
         * DG-A05-22-22760
         * DG A05 22 22760
         * DGA052222760
         * all normalize to DGA052222760.
         *
         * No UPC, label-name, track-title or artist-name fallback
         * is allowed for automatic revenue ownership.
         */

        $persistentRule = $normalizedIsrc
            ? DB::table('revenue_mapping_rules')
                ->where('identifier_type', 'isrc')
                ->where(
                    'identifier_value',
                    $normalizedIsrc
                )
                ->first()
            : null;

        /*
         * Find the catalogue track using normalized alphanumeric
         * ISRC only. REGEXP_REPLACE removes every separator, not
         * just hyphens/spaces.
         */
        /*
         * FAIL-SAFE ISRC RESOLUTION
         * -------------------------
         * Exactly one active catalogue track must match the normalized
         * ISRC. Zero matches remain unmapped. Multiple matches are also
         * treated as unmapped so revenue can never be assigned to an
         * arbitrary catalogue owner.
         */
        $trackMatches = $normalizedIsrc
            ? Track::query()
                ->whereNull('deleted_at')
                ->whereRaw(
                    "{$this->normalizedIsrcSql()} = ?",
                    [$normalizedIsrc]
                )
                ->limit(2)
                ->get()
            : collect();

        $track = $trackMatches->count() === 1
            ? $trackMatches->first()
            : null;

        $release = $track?->release;

        $ownerType = null;
        $ownerId = null;

        /*
         * DETERMINISTIC REVENUE OWNERSHIP
         * ===============================
         *
         * Priority:
         *
         * 1. Explicit persistent ISRC rule
         * 2. Catalogue release ownership
         * 3. Otherwise unmapped
         *
         * Catalogue placement and financial
         * ownership remain separate:
         *
         * label_id = exact catalogue label
         *
         * revenue_owner_id =
         * financial/root owner resolved by
         * CatalogueOwnershipService.
         */
        if ($persistentRule) {
            $ownerType =
                !empty(
                    $persistentRule->owner_type
                )
                    ? (string)
                        $persistentRule->owner_type
                    : null;

            $ownerId =
                !empty(
                    $persistentRule->owner_id
                )
                    ? (int)
                        $persistentRule->owner_id
                    : null;

            /*
             * A persistent rule may provide
             * catalogue placement even when
             * no catalogue track exists.
             */
            if (
                !empty(
                    $persistentRule
                        ->catalogue_label_id
                )
            ) {
                $release = (object) [
                    'id' =>
                        !empty(
                            $persistentRule
                                ->release_id
                        )
                            ? (int)
                                $persistentRule
                                    ->release_id
                            : null,

                    'label_id' =>
                        (int)
                        $persistentRule
                            ->catalogue_label_id,

                    'artist_id' =>
                        !empty(
                            $persistentRule
                                ->artist_id
                        )
                            ? (int)
                                $persistentRule
                                    ->artist_id
                            : null,
                ];
            }
        } elseif ($release) {
            $resolvedOwner = app(
                CatalogueOwnershipService::class
            )->resolveReleaseOwner(
                $release
            );

            $ownerType =
                !empty(
                    $resolvedOwner['type']
                )
                    ? (string)
                        $resolvedOwner['type']
                    : null;

            $ownerId =
                !empty(
                    $resolvedOwner['id']
                )
                    ? (int)
                        $resolvedOwner['id']
                    : null;
        }

        $mappingStatus =
            !empty($ownerType)
            && !empty($ownerId)
                ? 'mapped'
                : 'unmapped';

        /*
         * MIXX TUNE AUTOMATIC REVENUE ENGINE
         * ==================================
         *
         * Source of truth:
         *
         *   Collected Revenue = DSP gross amount
         *   Account Rate      = assigned account %
         *   Earnings          = gross x rate
         *
         * The exact catalogue account is preferred
         * over the root/master financial owner.
         *
         * Example:
         *
         *   DSP gross   = 100
         *   Label rate  = 80%
         *   Earnings    = 80
         *
         * Unmapped rows remain reconciliation rows
         * and preserve imported financial values.
         */
        $collectedRevenue =
            $this->nullableNumber(
                $mapped['collected_revenue']
            );

        $importedRate =
            $this->nullableNumber(
                $mapped['label_rate']
            );

        $importedEarnings =
            $this->nullableNumber(
                $mapped['earnings']
            );

        $assignedRate = null;

        if ($mappingStatus === 'mapped') {
            if ($release?->label_id) {
                $assignedRate =
                    DB::table('labels')
                        ->where(
                            'id',
                            (int) $release->label_id
                        )
                        ->value(
                            'revenue_share_percentage'
                        );
            } elseif ($release?->artist_id) {
                $assignedRate =
                    DB::table('artists')
                        ->where(
                            'id',
                            (int) $release->artist_id
                        )
                        ->value(
                            'revenue_share_percentage'
                        );
            }

            /*
             * Persistent ISRC rules or legacy catalogue
             * placement may expose only the financial
             * owner. Use it strictly as fallback.
             */
            if ($assignedRate === null) {
                if ($ownerType === 'label') {
                    $assignedRate =
                        DB::table('labels')
                            ->where(
                                'id',
                                (int) $ownerId
                            )
                            ->value(
                                'revenue_share_percentage'
                            );
                } elseif ($ownerType === 'artist') {
                    $assignedRate =
                        DB::table('artists')
                            ->where(
                                'id',
                                (int) $ownerId
                            )
                            ->value(
                                'revenue_share_percentage'
                            );
                }
            }
        }

        if ($assignedRate !== null) {
            $assignedRate = max(
                0.0,
                min(
                    100.0,
                    (float) $assignedRate
                )
            );
        }

        /*
         * report_rows.label_rate uses decimal ratio:
         *
         *   80% => 0.80
         *   70% => 0.70
         *  100% => 1.00
         */
        if (
            $mappingStatus === 'mapped'
            && $collectedRevenue !== null
            && $assignedRate !== null
        ) {
            $effectiveLabelRate =
                round(
                    $assignedRate / 100,
                    8
                );

            $effectiveEarnings =
                round(
                    $collectedRevenue
                    * $effectiveLabelRate,
                    8
                );
        } else {
            $effectiveLabelRate =
                $importedRate ?? 0.0;

            $effectiveEarnings =
                $importedEarnings ?? 0.0;
        }

        ReportRow::query()->create([
            'row_hash' =>
                $hash,

            'report_import_id' =>
                $import->id,

            'reporting_month' =>
                $import->reporting_month,

            'release_id' =>
                $release?->id,

            'track_id' =>
                $track?->id,

            'artist_id' =>
                $release?->artist_id,

            'label_id' =>
                $release?->label_id,

            'revenue_owner_type' =>
                $ownerType,

            'revenue_owner_id' =>
                $ownerId,

            'mapping_status' =>
                $mappingStatus,

            'mapped_at' =>
                $mappingStatus === 'mapped'
                    ? now()
                    : null,

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
                $effectiveLabelRate,

            'collected_revenue' =>
                $collectedRevenue,

            'earnings' =>
                $effectiveEarnings,

            'raw_data' =>
                $raw,
        ]);
    }

    private function normalizeMappingText(
        ?string $value
    ): string {
        $value = mb_strtolower(
            trim((string) $value),
            'UTF-8'
        );

        return preg_replace(
            '/\\s+/u',
            ' ',
            $value
        ) ?? '';
    }

    private function normaliseUpc(
        ?string $value
    ): ?string {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        if (
            stripos($value, 'e') !== false
            && is_numeric($value)
        ) {
            return number_format(
                (float) $value,
                0,
                '',
                ''
            );
        }

        $digits = preg_replace(
            '/[^0-9]/',
            '',
            $value
        );

        return $digits !== ''
            ? $digits
            : null;
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

    private function normalizedIsrcSql(): string
    {
        /*
         * MySQL supports REGEXP_REPLACE directly.
         *
         * SQLite is used by the isolated test suite and
         * does not provide REGEXP_REPLACE by default.
         *
         * Standard ISRC formatting may contain separators,
         * so remove common separators while preserving the
         * same uppercase/alphanumeric comparison semantics.
         */
        if (
            DB::connection()
                ->getDriverName()
            === 'sqlite'
        ) {
            return "REPLACE("
                ."REPLACE("
                ."REPLACE("
                ."REPLACE("
                ."REPLACE("
                ."UPPER(TRIM(isrc)), "
                ."'-', ''), "
                ."' ', ''), "
                ."'_', ''), "
                ."'.', ''), "
                ."'/', '')";
        }

        return "REGEXP_REPLACE("
            ."UPPER(TRIM(isrc)), "
            ."'[^A-Z0-9]', "
            ."'')";
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
        $value = trim(
            (string) ($month ?: $date)
        );

        if ($value === '') {
            return null;
        }

        // Financial-year labels are not monthly periods.
        if (
            preg_match(
                '/^FY\\s*\\d{2,4}\\s*[-\\/]\\s*\\d{2,4}$/i',
                $value
            )
        ) {
            return null;
        }

        // Examples: Aug-25, August-25, Aug 25
        foreach (
            ['M-y', 'F-y', 'M y', 'F y']
            as $format
        ) {
            try {
                $parsed = Carbon::createFromFormat(
                    '!' . $format,
                    $value
                );

                if ($parsed !== false) {
                    return $parsed->format('Y-m');
                }
            } catch (Throwable) {
                // Try next known format.
            }
        }

        // Examples: 2025-08, 08-2025, 2025/08
        foreach (
            ['Y-m', 'm-Y', 'Y/m', 'm/Y']
            as $format
        ) {
            try {
                $parsed = Carbon::createFromFormat(
                    '!' . $format,
                    $value
                );

                if ($parsed !== false) {
                    return $parsed->format('Y-m');
                }
            } catch (Throwable) {
                // Try next known format.
            }
        }

        try {
            return Carbon::parse($value)
                ->startOfMonth()
                ->format('Y-m');
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableNumber(
        mixed $value
    ): ?float {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        /*
         * Preserve scientific notation.
         *
         * Valid examples:
         *   9.97728E-06
         *   1.23e-05
         *   -4.5E+03
         *   0.00000997728
         *
         * Removing E/e would catastrophically
         * change the numeric scale.
         */
        $normalized = trim(
            (string) $value
        );

        /*
         * Allow spreadsheet-style formatting
         * without modifying exponent syntax.
         */
        $normalized = str_replace(
            [
                ',',
                '₹',
                '$',
                '€',
                '£',
            ],
            '',
            $normalized
        );

        if (
            $normalized === ''
            || !preg_match(
                '/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/',
                $normalized
            )
            || !is_numeric($normalized)
        ) {
            return null;
        }

        return (float) $normalized;
    }

    private function number(
        mixed $value
    ): float {
        /*
         * All numeric CSV values must pass through
         * the scientific-notation-safe parser.
         *
         * Examples:
         *   9.97728E-06 => 0.00000997728
         *   1.25E+03    => 1250
         *   1,234.50    => 1234.50
         */
        return $this->nullableNumber(
            $value
        ) ?? 0.0;
    }
}
