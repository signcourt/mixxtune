<?php

namespace App\Services\Revenue;

use App\Models\Finance\ImportError;
use App\Models\Finance\RevenueImport;
use App\Models\Finance\RevenueRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class RevenueWorkbookParser
{
    private const MONTH_PATTERN = '/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-\d{2}$/i';

    public function parse(RevenueImport $import): RevenueImport
    {
        $path = Storage::disk('local')->path($import->stored_file_path);

        $import->update([
            'status' => 'processing',
            'processing_started_at' => now(),
            'failure_reason' => null,
        ]);

        try {
            $spreadsheet = IOFactory::load($path);

            $totalRows = 0;
            $errorRows = 0;
            $grossRevenue = 0;
            $netRevenue = 0;

            DB::transaction(function () use (
                $import,
                $spreadsheet,
                &$totalRows,
                &$errorRows,
                &$grossRevenue,
                &$netRevenue
            ): void {
                $import->rows()->delete();
                $import->errors()->delete();

                foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                    if (!$this->shouldParseSheet($sheet)) {
                        continue;
                    }

                    $result = $this->parseSheet($import, $sheet);

                    $totalRows += $result['total_rows'];
                    $errorRows += $result['error_rows'];
                    $grossRevenue += $result['gross_revenue'];
                    $netRevenue += $result['net_revenue'];
                }
            });

            $import->update([
                'total_rows' => $totalRows,
                'error_rows' => $errorRows,
                'gross_revenue' => $grossRevenue,
                'net_revenue' => $netRevenue,
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => array_merge(
                    $import->metadata ?? [],
                    [
                        'parsed_sheets' => $this->parsedSheetNames($spreadsheet),
                    ]
                ),
            ]);

            return $import->refresh();
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function shouldParseSheet(Worksheet $sheet): bool
    {
        $name = trim($sheet->getTitle());

        if (
            preg_match(self::MONTH_PATTERN, $name) === 1
            || strcasecmp($name, 'PPL Data') === 0
        ) {
            return true;
        }

        /*
         * CSV and normal single-sheet XLSX files often use names such as
         * "Worksheet", "Sheet1" or the original filename. Detect them from
         * their headers rather than from the worksheet name.
         */
        $highestColumn = $sheet->getHighestDataColumn();

        $headers = $sheet->rangeToArray(
            'A1:' . $highestColumn . '1',
            null,
            true,
            true,
            false
        )[0];

        $normalisedHeaders = array_map(
            fn ($header) => $this->normaliseHeader($header),
            $headers
        );

        $recognisedHeaders = [
            'track title',
            'product title',
            'isrc',
            'upc',
            'track artist',
            'product artist name',
            'platform',
            'global customer name',
            'stream',
            'sale units',
            'earnings',
            'eranings',
        ];

        return count(
            array_intersect($normalisedHeaders, $recognisedHeaders)
        ) >= 4;
    }

    private function parsedSheetNames($spreadsheet): array
    {
        $names = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->shouldParseSheet($sheet)) {
                $names[] = $sheet->getTitle();
            }
        }

        return $names;
    }

    private function parseSheet(
        RevenueImport $import,
        Worksheet $sheet
    ): array {
        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();

        $headers = $sheet->rangeToArray(
            'A1:' . $highestColumn . '1',
            null,
            true,
            true,
            false
        )[0];

        $map = $this->buildHeaderMap($headers);

        $required = [
            'track_title',
            'artist_name',
            'upc',
            'isrc',
            'label_name',
            'transaction_date',
            'store_name',
            'country_code',
            'units',
        ];

        $missing = array_values(array_filter(
            $required,
            fn (string $field) => !array_key_exists($field, $map)
        ));

        if ($missing !== []) {
            throw new \RuntimeException(
                sprintf(
                    'Sheet "%s" is missing required columns: %s',
                    $sheet->getTitle(),
                    implode(', ', $missing)
                )
            );
        }

        $buffer = [];
        $errorBuffer = [];
        $totalRows = 0;
        $errorRows = 0;
        $grossRevenue = 0;
        $netRevenue = 0;

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray(
                'A' . $rowNumber . ':' . $highestColumn . $rowNumber,
                null,
                true,
                true,
                false
            )[0];

            if ($this->isEmptyRow($values)) {
                continue;
            }

            try {
                $row = $this->normaliseRow(
                    $import,
                    $sheet->getTitle(),
                    $rowNumber,
                    $values,
                    $map
                );

                $buffer[] = $row;
                $totalRows++;
                $grossRevenue += (float) $row['gross_amount'];
                $netRevenue += (float) $row['net_amount'];
            } catch (Throwable $exception) {
                $errorBuffer[] = [
                    'revenue_import_id' => $import->id,
                    'source_row_number' => $rowNumber,
                    'error_type' => 'row_parse_error',
                    'message' => $exception->getMessage(),
                    'row_data' => json_encode($values),
                    'is_resolved' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $errorRows++;
            }

            if (count($buffer) >= 500) {
                RevenueRow::insert($buffer);
                $buffer = [];
            }

            if (count($errorBuffer) >= 500) {
                ImportError::insert($errorBuffer);
                $errorBuffer = [];
            }
        }

        if ($buffer !== []) {
            RevenueRow::insert($buffer);
        }

        if ($errorBuffer !== []) {
            ImportError::insert($errorBuffer);
        }

        return [
            'total_rows' => $totalRows,
            'error_rows' => $errorRows,
            'gross_revenue' => $grossRevenue,
            'net_revenue' => $netRevenue,
        ];
    }

    private function buildHeaderMap(array $headers): array
    {
        $aliases = [
            'track_title' => [
                'product title',
                'track title',
                'title',
            ],
            'artist_name' => [
                'product artist name',
                'track artist',
                'artist',
                'artist name',
            ],
            'release_title' => [
                'product album name',
                'album title',
                'release title',
            ],
            'upc' => [
                'upc',
                'product origin id',
                "product's origin id",
            ],
            'isrc' => [
                'isrc',
            ],
            'label_name' => [
                'label name',
                'label',
            ],
            'transaction_date' => [
                'sales month',
                'sale month',
                'reporting month',
                'transaction date',
                'report date',
                'month',
            ],
            'store_name' => [
                'platform',
                'global customer name',
                'customer account name',
                'e-retailer name',
                'store name',
            ],
            'sale_type' => [
                'customer revenue type',
                'sale type',
                'transaction type',
            ],
            'country_code' => [
                'country / region',
                'country region',
                'country name',
                'end consumer country',
                'e-retailer country',
                'country',
            ],
            'units' => [
                'stream',
                'sale units',
                'units sold',
                'streams',
                'quantity',
            ],
            'currency' => [
                'client payment currency',
                'payment currency',
                'currency',
            ],
            'gross_amount' => [
                'gross amount',
                'gross revenue',
            ],
            'net_amount' => [
                'eranings',
                'earnings',
                'net amount',
                'net revenue',
                'royalty',
            ],
            'reporting_month' => [
                'reporting month',
                'statement month',
            ],
            'cms' => [
                'cms',
            ],
            'subscription_type' => [
                'streaming subscription type',
                'subscription type',
            ],
            'label_rate' => [
                'label rate',
                'label share',
                'label percentage',
            ],
        ];

        $map = [];

        foreach ($headers as $index => $header) {
            $normalised = $this->normaliseHeader($header);

            foreach ($aliases as $field => $possibleHeaders) {
                if (in_array($normalised, $possibleHeaders, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function normaliseRow(
        RevenueImport $import,
        string $sheetName,
        int $rowNumber,
        array $values,
        array $map
    ): array {
        $isrc = $this->cleanIdentifier(
            $this->value($values, $map, 'isrc')
        );

        $upc = $this->cleanIdentifier(
            $this->value($values, $map, 'upc')
        );

        $trackTitle = $this->cleanText(
            $this->value($values, $map, 'track_title')
        );

        $artistName = $this->cleanText(
            $this->value($values, $map, 'artist_name')
        );

        if ($trackTitle === '' && $isrc === '') {
            throw new \RuntimeException(
                'Track title and ISRC are both missing.'
            );
        }

        $units = $this->toNumber(
            $this->value($values, $map, 'units')
        );

        $gross = $this->toNumber(
            $this->value($values, $map, 'gross_amount')
        );

        $net = $this->toNumber(
            $this->value($values, $map, 'net_amount')
        );

        $sourcePayload = [
            'sheet' => $sheetName,
            'row' => $rowNumber,
            'values' => $values,
        ];

        $reportingMonthValue = $this->value(
            $values,
            $map,
            'reporting_month'
        );

        /*
         * Fallback:
         * Some distributor sheets contain Reporting Month immediately
         * before Sale Month, but the heading may not match an alias.
         */
        if (
            ($reportingMonthValue === null ||
                $this->cleanText($reportingMonthValue) === '') &&
            isset($map['transaction_date']) &&
            $map['transaction_date'] > 0
        ) {
            $reportingMonthValue =
                $values[$map['transaction_date'] - 1] ?? null;
        }

        $reportingMonth = $this->normaliseMonth(
            $reportingMonthValue,
            $sheetName
        );

        return [
            'revenue_import_id' => $import->id,
            'source_row_number' => $rowNumber,
            'isrc' => $isrc !== '' ? $isrc : null,
            'upc' => $upc !== '' ? $upc : null,
            'track_title' => $trackTitle !== '' ? $trackTitle : null,
            'release_title' => $this->nullableText(
                $this->value($values, $map, 'release_title')
            ),
            'artist_name' => $artistName !== '' ? $artistName : null,
            'label_name' => $this->nullableText(
                $this->value($values, $map, 'label_name')
            ),
            'store_name' => $this->nullableText(
                $this->value($values, $map, 'store_name')
            ),
            'country_code' => $this->normaliseCountry(
                $this->value($values, $map, 'country_code')
            ),
            'sale_type' => $this->nullableText(
                $this->value($values, $map, 'sale_type')
            ),
            'sale_month' => $this->normaliseMonth(
                $this->value($values, $map, 'transaction_date'),
                $sheetName
            ),
            'reporting_month' => $reportingMonth,
            'currency' => strtoupper(
                $this->cleanText(
                    $this->value($values, $map, 'currency')
                        ?: $import->currency
                )
            ),
            'streams' => (int) round($units),
            'quantity' => $units,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'source_row_hash' => hash(
                'sha256',
                json_encode($sourcePayload)
            ),
            'match_status' => 'pending',
            'raw_data' => json_encode($sourcePayload),
            'metadata' => json_encode([
                'source_sheet' => $sheetName,
                'reporting_month' => $reportingMonth,
                'cms' => $this->nullableText(
                    $this->value($values, $map, 'cms')
                ),
                'subscription_type' => $this->nullableText(
                    $this->value($values, $map, 'subscription_type')
                ),
                'label_rate' => $this->toNumber(
                    $this->value($values, $map, 'label_rate')
                ),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function value(
        array $values,
        array $map,
        string $field
    ): mixed {
        if (!array_key_exists($field, $map)) {
            return null;
        }

        return $values[$map[$field]] ?? null;
    }

    private function normaliseHeader(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text !== '' ? $text : null;
    }

    private function cleanIdentifier(mixed $value): string
    {
        return strtoupper(
            preg_replace('/[^A-Z0-9]/i', '', (string) ($value ?? ''))
        );
    }

    private function normaliseCountry(mixed $value): ?string
    {
        $country = strtoupper($this->cleanText($value));

        return $country !== '' ? $country : null;
    }

    private function normaliseMonth(
        mixed $value,
        string $sheetName
    ): ?string {
        $text = $this->cleanText($value);

        foreach ([$text, $sheetName] as $candidate) {
            if (
                preg_match(
                    '/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-(\d{2})$/i',
                    $candidate,
                    $matches
                )
            ) {
                $date = \DateTimeImmutable::createFromFormat(
                    'M-y',
                    ucfirst(strtolower($matches[1])) . '-' . $matches[2]
                );

                if ($date) {
                    return $date->format('Y-m-01');
                }
            }
        }

        return null;
    }

    private function toNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalised = str_replace(
            [',', '£', '$', '€', '₹', ' '],
            '',
            (string) $value
        );

        return is_numeric($normalised)
            ? (float) $normalised
            : 0;
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
