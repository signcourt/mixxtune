<?php

namespace App\Services;

use App\Models\MasterReportBatch;
use App\Models\MasterReportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterReportAnalyzerService
{
    private array $aliases = [
        'store' => [
            'store', 'dsp', 'platform', 'service', 'store name',
            'platform name', 'digital service provider',
        ],
        'report_month' => [
            'report month', 'reporting month', 'month', 'sales month',
            'statement month',
        ],
        'track_title' => [
            'track', 'track title', 'title', 'song', 'song title',
            'asset title',
        ],
        'version' => [
            'version', 'track version', 'mix version',
        ],
        'artist' => [
            'artist', 'artist name', 'primary artist',
            'display artist',
        ],
        'label' => [
            'label', 'label name', 'record label',
        ],
        'isrc' => [
            'isrc', 'isrc code', 'track isrc',
        ],
        'upc' => [
            'upc', 'upc code', 'ean', 'barcode', 'release upc',
        ],
        'release_title' => [
            'release', 'release title', 'album', 'album title',
            'product title',
        ],
        'country' => [
            'country', 'territory', 'country code',
            'territory code',
        ],
        'currency' => [
            'currency', 'currency code',
        ],
        'streams' => [
            'streams', 'stream', 'units', 'quantity', 'qty',
            'plays', 'views', 'usage count',
        ],
        'gross_revenue' => [
            'gross revenue', 'gross', 'gross amount',
            'gross royalty', 'gross royalties',
            'sales revenue',
        ],
        'vendor_revenue' => [
            'vendor revenue', 'net revenue', 'net amount',
            'net royalty', 'net royalties', 'payable',
            'amount payable',
        ],
        'content_type' => [
            'content type', 'asset type',
        ],
        'usage_type' => [
            'usage type', 'usage', 'transaction type',
        ],
        'subscription_type' => [
            'subscription type', 'subscription', 'plan type',
        ],
    ];

    public function analyze(UploadedFile $file, User $user): MasterReportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException(
                'Only CSV, XLSX and XLS files are supported.'
            );
        }

        $batch = MasterReportBatch::create([
            'batch_code' => 'MRA-'.now()->format('YmdHis').'-'.
                strtoupper(Str::random(6)),
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
            'status' => 'processing',
        ]);

        try {
            $storedPath = $file->store(
                'master-report-analyzer/'.$batch->batch_code,
                'local'
            );

            $batch->update([
                'stored_path' => $storedPath,
            ]);

            $absolutePath = Storage::disk('local')->path($storedPath);

            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();

            $data = $sheet->toArray(
                null,
                true,
                true,
                false
            );

            if (count($data) < 2) {
                throw new \RuntimeException(
                    'The uploaded report contains no data rows.'
                );
            }

            $rawHeaders = array_shift($data);
            $headerMap = $this->buildHeaderMap($rawHeaders);

            if (
                ! isset($headerMap['gross_revenue']) &&
                ! isset($headerMap['vendor_revenue'])
            ) {
                throw new \RuntimeException(
                    'Revenue columns could not be detected. Gross Revenue or Vendor Revenue is required.'
                );
            }

            DB::transaction(function () use (
                $data,
                $rawHeaders,
                $headerMap,
                $batch
            ): void {
                $buffer = [];
                $sourceRow = 2;

                foreach ($data as $row) {
                    if ($this->rowIsEmpty($row)) {
                        $sourceRow++;
                        continue;
                    }

                    $rawData = [];

                    foreach ($rawHeaders as $index => $header) {
                        $name = trim((string) $header);

                        if ($name === '') {
                            $name = 'column_'.($index + 1);
                        }

                        $rawData[$name] = $row[$index] ?? null;
                    }

                    $store = $this->value(
                        $row,
                        $headerMap,
                        'store'
                    );

                    $track = $this->value(
                        $row,
                        $headerMap,
                        'track_title'
                    );

                    $artist = $this->value(
                        $row,
                        $headerMap,
                        'artist'
                    );

                    $label = $this->value(
                        $row,
                        $headerMap,
                        'label'
                    );

                    $isrc = $this->value(
                        $row,
                        $headerMap,
                        'isrc'
                    );

                    $upc = $this->value(
                        $row,
                        $headerMap,
                        'upc'
                    );

                    $rawStreams = $this->value(
                        $row,
                        $headerMap,
                        'streams'
                    );

                    $rawGross = $this->value(
                        $row,
                        $headerMap,
                        'gross_revenue'
                    );

                    $rawVendor = $this->value(
                        $row,
                        $headerMap,
                        'vendor_revenue'
                    );

                    $streams = $this->number($rawStreams);
                    $gross = $this->number($rawGross);
                    $vendor = $this->number($rawVendor);

                    $deduction = $gross - $vendor;

                    $deductionPercent = $gross != 0.0
                        ? ($deduction / $gross) * 100
                        : 0;

                    $messages = [];

                    if ($store === null || $store === '') {
                        $messages[] = 'Store is missing.';
                    }

                    if ($track === null || $track === '') {
                        $messages[] = 'Track title is missing.';
                    }

                    if ($isrc === null || $isrc === '') {
                        $messages[] = 'ISRC is missing.';
                    }

                    if ($vendor > $gross && $gross != 0.0) {
                        $messages[] =
                            'Vendor Revenue is greater than Gross Revenue.';
                    }

                    $buffer[] = [
                        'batch_id' => $batch->id,
                        'source_row_number' => $sourceRow,

                        'store' => $this->clean($store),
                        'report_month' => $this->clean(
                            $this->value(
                                $row,
                                $headerMap,
                                'report_month'
                            )
                        ),
                        'track_title' => $this->clean($track),
                        'version' => $this->clean(
                            $this->value($row, $headerMap, 'version')
                        ),
                        'artist' => $this->clean($artist),
                        'label' => $this->clean($label),
                        'isrc' => $this->clean($isrc),
                        'upc' => $this->clean($upc),
                        'release_title' => $this->clean(
                            $this->value(
                                $row,
                                $headerMap,
                                'release_title'
                            )
                        ),
                        'country' => $this->clean(
                            $this->value($row, $headerMap, 'country')
                        ),
                        'currency' => $this->clean(
                            $this->value($row, $headerMap, 'currency')
                        ),

                        'streams' => $streams,
                        'gross_revenue' => $gross,
                        'vendor_revenue' => $vendor,
                        'deduction_amount' => $deduction,
                        'deduction_percent' => $deductionPercent,

                        'content_type' => $this->clean(
                            $this->value(
                                $row,
                                $headerMap,
                                'content_type'
                            )
                        ),
                        'usage_type' => $this->clean(
                            $this->value(
                                $row,
                                $headerMap,
                                'usage_type'
                            )
                        ),
                        'subscription_type' => $this->clean(
                            $this->value(
                                $row,
                                $headerMap,
                                'subscription_type'
                            )
                        ),

                        'validation_status' =>
                            empty($messages) ? 'valid' : 'warning',

                        'validation_messages' =>
                            json_encode(
                                $messages,
                                JSON_UNESCAPED_UNICODE
                            ),

                        'raw_store' => $this->rawString($store),
                        'raw_track' => $this->rawString($track),
                        'raw_artist' => $this->rawString($artist),
                        'raw_label' => $this->rawString($label),
                        'raw_isrc' => $this->rawString($isrc),
                        'raw_upc' => $this->rawString($upc),
                        'raw_streams' => $this->rawString($rawStreams),
                        'raw_gross_revenue' =>
                            $this->rawString($rawGross),
                        'raw_vendor_revenue' =>
                            $this->rawString($rawVendor),

                        'raw_data' => json_encode(
                            $rawData,
                            JSON_UNESCAPED_UNICODE
                        ),

                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($buffer) >= 1000) {
                        MasterReportRow::insert($buffer);
                        $buffer = [];
                    }

                    $sourceRow++;
                }

                if ($buffer !== []) {
                    MasterReportRow::insert($buffer);
                }

                $this->refreshBatchTotals($batch);
            });

            $batch->update([
                'status' => 'completed',
                'error_message' => null,
            ]);

            $spreadsheet->disconnectWorksheets();

            return $batch->fresh();

        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function refreshBatchTotals(
        MasterReportBatch $batch
    ): void {
        $query = MasterReportRow::query()
            ->where('batch_id', $batch->id);

        $totalRows = (clone $query)->count();

        $totalStores = (clone $query)
            ->whereNotNull('store')
            ->where('store', '<>', '')
            ->distinct()
            ->count('store');

        $totalTracks = (clone $query)
            ->whereNotNull('isrc')
            ->where('isrc', '<>', '')
            ->distinct()
            ->count('isrc');

        $totals = (clone $query)
            ->selectRaw(
                'COALESCE(SUM(streams),0) AS total_streams,
                 COALESCE(SUM(gross_revenue),0) AS gross_revenue,
                 COALESCE(SUM(vendor_revenue),0) AS vendor_revenue'
            )
            ->first();

        $gross = (float) $totals->gross_revenue;
        $vendor = (float) $totals->vendor_revenue;
        $deduction = $gross - $vendor;

        $batch->update([
            'total_rows' => $totalRows,
            'total_tracks' => $totalTracks,
            'total_stores' => $totalStores,
            'total_streams' => $totals->total_streams,
            'gross_revenue' => $gross,
            'vendor_revenue' => $vendor,
            'deduction_amount' => $deduction,
            'deduction_percent' =>
                $gross != 0.0
                    ? ($deduction / $gross) * 100
                    : 0,
            'currency' => MasterReportRow::query()
                ->where('batch_id', $batch->id)
                ->whereNotNull('currency')
                ->where('currency', '<>', '')
                ->value('currency'),
        ]);
    }

    private function buildHeaderMap(array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $index => $header) {
            $normalizedHeaders[
                $this->normalizeHeader((string) $header)
            ] = $index;
        }

        $map = [];

        foreach ($this->aliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias =
                    $this->normalizeHeader($alias);

                if (
                    array_key_exists(
                        $normalizedAlias,
                        $normalizedHeaders
                    )
                ) {
                    $map[$field] =
                        $normalizedHeaders[$normalizedAlias];
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::lower(trim($value));

        $value = preg_replace(
            '/[\s_\-\/]+/u',
            ' ',
            $value
        );

        return trim((string) $value);
    }

    private function value(
        array $row,
        array $map,
        string $field
    ): mixed {
        if (! array_key_exists($field, $map)) {
            return null;
        }

        return $row[$map[$field]] ?? null;
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $negative = false;

        if (
            str_starts_with($value, '(') &&
            str_ends_with($value, ')')
        ) {
            $negative = true;
            $value = trim($value, '()');
        }

        $value = str_replace(
            [',', '₹', '$', '€', '£'],
            '',
            $value
        );

        $number = is_numeric($value)
            ? (float) $value
            : 0.0;

        return $negative ? -$number : $number;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rawString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (
                $value !== null &&
                trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }
}
