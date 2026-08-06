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
        User $user
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
