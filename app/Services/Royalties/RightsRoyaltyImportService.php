<?php

namespace App\Services\Royalties;

use App\Models\Finance\RightsRoyaltyImport;
use App\Models\Finance\RightsRoyaltyRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RightsRoyaltyImportService
{
    /*
     * MIXXTUNE_RIGHTS_ROYALTY_IMPORT_V1
     *
     * Stage 1:
     * upload → detect headers → preview
     *
     * Stage 2:
     * column mapping → normalized rows → ISRC mapping
     *
     * No wallet posting.
     */

    public function inspect(UploadedFile $file): array
    {
        $sheet = IOFactory::load(
            $file->getRealPath()
        )->getActiveSheet();

        $rows = $sheet->toArray(
            null,
            true,
            true,
            false
        );

        if (count($rows) < 2) {
            throw new InvalidArgumentException(
                'The report must contain a header row and at least one data row.'
            );
        }

        $headers = array_map(
            fn ($value) => trim((string) $value),
            array_shift($rows)
        );

        $preview = [];

        foreach (array_slice($rows, 0, 8) as $row) {
            $item = [];

            foreach ($headers as $index => $header) {
                $key = $header !== ''
                    ? $header
                    : 'Column '.($index + 1);

                $item[$key] =
                    $row[$index] ?? null;
            }

            $preview[] = $item;
        }

        return [
            'headers' => $headers,
            'preview' => $preview,
            'row_count' => count($rows),
            'suggested_mapping' =>
                $this->suggestMapping($headers),
        ];
    }

    public function import(
        UploadedFile $file,
        array $data,
        int $userId
    ): RightsRoyaltyImport {
        $sheet = IOFactory::load(
            $file->getRealPath()
        )->getActiveSheet();

        $allRows = $sheet->toArray(
            null,
            true,
            true,
            false
        );

        if (count($allRows) < 2) {
            throw new InvalidArgumentException(
                'No royalty rows found.'
            );
        }

        $headers = array_map(
            fn ($value) => trim((string) $value),
            array_shift($allRows)
        );

        $headerIndexes = [];

        foreach ($headers as $index => $header) {
            $headerIndexes[$header] = $index;
        }

        $mapping = $data['mapping'];

        foreach (['isrc', 'amount'] as $required) {
            $header = $mapping[$required] ?? null;

            if (
                ! $header
                || ! array_key_exists(
                    $header,
                    $headerIndexes
                )
            ) {
                throw new InvalidArgumentException(
                    "Invalid {$required} column mapping."
                );
            }
        }

        $storedPath = $file->store(
            'rights-royalties/'.$data['statement_month']
        );

        return DB::transaction(
            function () use (
                $file,
                $data,
                $userId,
                $headers,
                $headerIndexes,
                $mapping,
                $allRows,
                $storedPath
            ) {
                $import = RightsRoyaltyImport::create([
                    'public_id' => (string) Str::ulid(),
                    'source' => strtoupper(
                        $data['source']
                    ),
                    'statement_month' =>
                        $data['statement_month'],
                    'currency' => strtoupper(
                        $data['currency']
                    ),
                    'original_filename' =>
                        $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'uploaded_by' => $userId,
                    'status' => 'processing',
                    'metadata' => [
                        'headers' => $headers,
                        'mapping' => $mapping,
                    ],
                ]);

                $gross = 0.0;
                $created = 0;

                foreach (
                    $allRows as $offset => $sourceRow
                ) {
                    if ($this->rowIsEmpty($sourceRow)) {
                        continue;
                    }

                    $amountRaw = $this->value(
                        $sourceRow,
                        $headerIndexes,
                        $mapping['amount']
                    );

                    $amount = $this->parseAmount(
                        $amountRaw
                    );

                    $isrc = $this->value(
                        $sourceRow,
                        $headerIndexes,
                        $mapping['isrc']
                    );

                    $trackTitle = $this->optionalValue(
                        $sourceRow,
                        $headerIndexes,
                        $mapping['track_title']
                            ?? null
                    );

                    $rightsType = $this->optionalValue(
                        $sourceRow,
                        $headerIndexes,
                        $mapping['rights_type']
                            ?? null
                    );

                    $territory = $this->optionalValue(
                        $sourceRow,
                        $headerIndexes,
                        $mapping['territory']
                            ?? null
                    );

                    $raw = [];

                    foreach (
                        $headers as $index => $header
                    ) {
                        $key = $header !== ''
                            ? $header
                            : 'Column '.($index + 1);

                        $raw[$key] =
                            $sourceRow[$index] ?? null;
                    }

                    RightsRoyaltyRow::create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'rights_royalty_import_id' =>
                            $import->id,

                        /*
                         * Spreadsheet row:
                         * row 1 = headers.
                         */
                        'source_row_number' =>
                            $offset + 2,

                        'isrc' =>
                            trim((string) $isrc),

                        'track_title' =>
                            $trackTitle,

                        'rights_type' =>
                            $rightsType,

                        'territory' =>
                            $territory,

                        'gross_amount' =>
                            $amount,

                        'mapping_status' =>
                            'unmapped',

                        'mapping_reason' =>
                            'Awaiting catalogue mapping',

                        'raw_data' =>
                            $raw,
                    ]);

                    $gross += $amount;
                    $created++;
                }

                $import->update([
                    'total_rows' => $created,
                    'gross_amount' =>
                        round($gross, 8),
                ]);

                app(
                    RightsRoyaltyMappingService::class
                )->mapImport($import);

                return $import->fresh();
            }
        );
    }

    private function suggestMapping(
        array $headers
    ): array {
        $result = [
            'isrc' => null,
            'amount' => null,
            'track_title' => null,
            'rights_type' => null,
            'territory' => null,
        ];

        foreach ($headers as $header) {
            $normalized = strtolower(
                preg_replace(
                    '/[^a-z0-9]+/',
                    '',
                    $header
                )
            );

            if (
                $result['isrc'] === null
                && in_array(
                    $normalized,
                    [
                        'isrc',
                        'recordingisrc',
                        'trackisrc',
                    ],
                    true
                )
            ) {
                $result['isrc'] = $header;
            }

            if (
                $result['amount'] === null
                && in_array(
                    $normalized,
                    [
                        'amount',
                        'royalty',
                        'royaltyamount',
                        'netamount',
                        'grossamount',
                        'payable',
                        'earnings',
                    ],
                    true
                )
            ) {
                $result['amount'] = $header;
            }

            if (
                $result['track_title'] === null
                && in_array(
                    $normalized,
                    [
                        'track',
                        'tracktitle',
                        'title',
                        'worktitle',
                        'recordingtitle',
                    ],
                    true
                )
            ) {
                $result['track_title'] =
                    $header;
            }

            if (
                $result['rights_type'] === null
                && in_array(
                    $normalized,
                    [
                        'rightstype',
                        'righttype',
                        'royaltytype',
                        'usagetype',
                    ],
                    true
                )
            ) {
                $result['rights_type'] =
                    $header;
            }

            if (
                $result['territory'] === null
                && in_array(
                    $normalized,
                    [
                        'territory',
                        'country',
                        'countrycode',
                    ],
                    true
                )
            ) {
                $result['territory'] =
                    $header;
            }
        }

        return $result;
    }

    private function value(
        array $row,
        array $indexes,
        string $header
    ): mixed {
        return $row[
            $indexes[$header]
        ] ?? null;
    }

    private function optionalValue(
        array $row,
        array $indexes,
        ?string $header
    ): ?string {
        if (
            ! $header
            || ! array_key_exists(
                $header,
                $indexes
            )
        ) {
            return null;
        }

        $value = trim(
            (string) (
                $row[$indexes[$header]]
                ?? ''
            )
        );

        return $value === ''
            ? null
            : $value;
    }

    private function rowIsEmpty(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    private function parseAmount(
        mixed $value
    ): float {
        if (
            is_int($value)
            || is_float($value)
        ) {
            return round(
                (float) $value,
                8
            );
        }

        $text = trim((string) $value);

        if ($text === '') {
            return 0.0;
        }

        $negative = false;

        if (
            str_starts_with($text, '(')
            && str_ends_with($text, ')')
        ) {
            $negative = true;
        }

        $clean = preg_replace(
            '/[^0-9.\-]/',
            '',
            str_replace(',', '', $text)
        );

        if (
            $clean === ''
            || $clean === '-'
            || ! is_numeric($clean)
        ) {
            return 0.0;
        }

        $amount = (float) $clean;

        if (
            $negative
            && $amount > 0
        ) {
            $amount *= -1;
        }

        return round($amount, 8);
    }
}
