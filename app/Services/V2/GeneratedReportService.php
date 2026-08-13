<?php

namespace App\Services\V2;

use App\Models\Reports\GeneratedReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class GeneratedReportService
{
    private const COLUMN_MAP = [
        'Reporting Month' => 'sale_month',
        'Sales Month' => 'sale_month',
        'Track Artist' => 'track_artist',
        'Track Title' => 'track_title',
        'Album Title' => 'album_title',
        'Album Artist' => 'album_artist',
        'Label' => 'label_name',
        'ISRC' => 'isrc',
        'UPC' => 'upc',
        'Platform' => 'platform',
        'Country / Region' => 'country_code',
        'CMS' => 'cms',
        'Sale Type' => 'sale_type',
        'Quantity / Streams' => '__quantity',
        'Currency' => 'currency',
        'Collected Revenue' => '__gross',
        'Assigned Rate' => '__share',
        'User Revenue' => '__net',
    ];

    public function __construct(
        private readonly ReportAnalyticsService $analytics,
        private readonly PermissionService $permissions
    ) {
    }

    public function generate(
        User $user,
        array $data
    ): GeneratedReport {
        $fromMonth = (string) $data['from_month'];
        $toMonth = (string) $data['to_month'];

        $this->validatePeriod(
            $fromMonth,
            $toMonth
        );

        $selectedColumns = array_values(
            array_unique(
                $data['selected_columns']
            )
        );

        $invalidColumns = array_values(
            array_diff(
                $selectedColumns,
                array_keys(self::COLUMN_MAP)
            )
        );

        if ($invalidColumns !== []) {
            throw ValidationException::withMessages([
                'selected_columns' =>
                    'Unsupported report columns: '
                    .implode(', ', $invalidColumns),
            ]);
        }

        if ($selectedColumns === []) {
            throw ValidationException::withMessages([
                'selected_columns' =>
                    'Select at least one report column.',
            ]);
        }

        $query = $this->analytics
            ->scopedQuery(
                $user,
                $this->permissions
            )
            ->whereBetween(
                'sale_month',
                [
                    $fromMonth,
                    $toMonth,
                ]
            )
            ->orderBy('sale_month')
            ->orderBy('id');

        /*
         * Security rule:
         * frontend scope IDs are never trusted.
         *
         * The base query above is already restricted
         * to the authenticated user's accessible data.
         *
         * Scope-specific filtering can be layered onto
         * this scoped query later without bypassing
         * authorization.
         */
        $scope = (string) (
            $data['scope']
            ?? 'full_catalogue'
        );

        $reportMode = (string) (
            $data['report_mode']
            ?? 'single'
        );

        $rowsCount =
            (clone $query)->count();

        if ($rowsCount === 0) {
            throw ValidationException::withMessages([
                'from_month' =>
                    'No report data is available for the selected period.',
            ]);
        }

        $grossAmount = round(
            (float)
                (clone $query)->sum('earnings'),
            8
        );

        $currencies =
            (clone $query)
                ->reorder()
                ->whereNotNull('currency')
                ->where('currency', '!=', '')
                ->distinct()
                ->pluck('currency')
                ->map(
                    fn ($currency) =>
                        strtoupper(
                            trim(
                                (string) $currency
                            )
                        )
                )
                ->filter()
                ->unique()
                ->values();

        $currency =
            $currencies->count() === 1
                ? $currencies->first()
                : (
                    $currencies->count() > 1
                        ? 'MULTI'
                        : null
                );

        $report = GeneratedReport::query()
            ->create([
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'user_id' =>
                    $user->id,

                'report_type' =>
                    'requested',

                'report_mode' =>
                    $reportMode,

                'scope' =>
                    $scope,

                'from_month' =>
                    $fromMonth,

                'to_month' =>
                    $toMonth,

                'selected_columns' =>
                    $selectedColumns,

                'filters' =>
                    $data['filters']
                    ?? null,

                'currency' =>
                    $currency,

                'gross_amount' =>
                    $grossAmount,

                /*
                 * Final net is updated after all
                 * row-level ownership calculations.
                 */
                'net_amount' =>
                    0,

                'rows_count' =>
                    $rowsCount,

                'status' =>
                    'processing',
            ]);

        try {
            $result = $this->writeWorkbook(
                $report,
                $query,
                $selectedColumns,
                $user
            );

            $report->update([
                'net_amount' =>
                    $result['net_amount'],

                'file_path' =>
                    $result['file_path'],

                'file_name' =>
                    $result['file_name'],

                'status' =>
                    'completed',

                'generated_at' =>
                    now(),

                'error_message' =>
                    null,
            ]);
        } catch (Throwable $e) {
            $report->update([
                'status' =>
                    'failed',

                'error_message' =>
                    mb_substr(
                        $e->getMessage(),
                        0,
                        5000
                    ),
            ]);

            throw $e;
        }

        return $report->fresh();
    }

    private function writeWorkbook(
        GeneratedReport $report,
        $query,
        array $selectedColumns,
        User $user
    ): array {
        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->setTitle(
            'Royalty Report'
        );

        foreach (
            $selectedColumns
            as $index => $column
        ) {
            $sheet->setCellValue(
                [
                    $index + 1,
                    1,
                ],
                $this->exportHeading(
                    $column,
                    $user
                )
            );
        }

        $sheet->freezePane('A2');

        $sheet->getStyle(
            '1:1'
        )->getFont()->setBold(true);

        $excelRow = 2;
        $totalNet = 0.0;

        $query->chunkById(
            1000,
            function ($rows) use (
                $sheet,
                $selectedColumns,
                &$excelRow,
                &$totalNet,
                $user
            ) {
                foreach ($rows as $row) {
                    $revenue =
                        $this->revenueForRow(
                            $row,
                            $user
                        );

                    $totalNet +=
                        $revenue['net'];

                    foreach (
                        $selectedColumns
                        as $index => $column
                    ) {
                        $value =
                            $this->columnValue(
                                $column,
                                $row,
                                $revenue
                            );

                        $cell = $sheet->getCell([
                            $index + 1,
                            $excelRow,
                        ]);

                        /*
                         * Preserve UPC/ISRC and other
                         * identifiers exactly as text.
                         */
                        if (
                            in_array(
                                $column,
                                [
                                    'ISRC',
                                    'UPC',
                                ],
                                true
                            )
                        ) {
                            $cell->setValueExplicit(
                                (string) ($value ?? ''),
                                DataType::TYPE_STRING
                            );
                        } else {
                            $cell->setValue(
                                $value
                            );
                        }
                    }

                    $excelRow++;
                }
            },
            'id'
        );

        foreach (
            range(
                1,
                count($selectedColumns)
            ) as $columnIndex
        ) {
            $sheet
                ->getColumnDimensionByColumn(
                    $columnIndex
                )
                ->setAutoSize(true);
        }

        $directory =
            'generated-reports/'
            .$user->id;

        Storage::disk('local')
            ->makeDirectory(
                $directory
            );

        $fileName =
            'royalty-report-'
            .$report->from_month
            .'-to-'
            .$report->to_month
            .'-'
            .$report->public_id
            .'.xlsx';

        $filePath =
            $directory
            .'/'
            .$fileName;

        $absolutePath =
            Storage::disk('local')
                ->path(
                    $filePath
                );

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            $absolutePath
        );

        $spreadsheet
            ->disconnectWorksheets();

        return [
            'file_path' =>
                $filePath,

            'file_name' =>
                $fileName,

            'net_amount' =>
                round(
                    $totalNet,
                    8
                ),
        ];
    }

    private function exportHeading(
        string $column,
        User $user
    ): string {
        $role =
            $this->permissions
                ->role(
                    $user
                );

        if ($column === 'Assigned Rate') {
            return match ($role) {
                'label' =>
                    'Label Rate',

                'artist' =>
                    'Artist Rate',

                default =>
                    'Assigned Rate',
            };
        }

        if ($column === 'User Revenue') {
            return match ($role) {
                'label',
                'artist' =>
                    'Earning',

                default =>
                    'User Revenue',
            };
        }

        return $column;
    }

    private function columnValue(
        string $column,
        $row,
        array $revenue
    ): mixed {
        return match ($column) {
            'Reporting Month',
            'Sales Month' =>
                $row->sale_month,

            'Track Artist' =>
                $row->track_artist,

            'Track Title' =>
                $row->track_title,

            'Album Title' =>
                $row->album_title,

            'Album Artist' =>
                $row->album_artist,

            'Label' =>
                $row->label_name,

            'ISRC' =>
                $row->isrc,

            'UPC' =>
                $row->upc,

            'Platform' =>
                $row->platform,

            'Country / Region' =>
                $row->country_code,

            'CMS' =>
                $row->cms,

            'Sale Type' =>
                $row->sale_type,

            'Quantity / Streams' =>
                $this->quantityForRow(
                    $row
                ),

            'Currency' =>
                $row->currency,

            'Collected Revenue' =>
                $revenue['gross'],

            'Assigned Rate' =>
                $revenue['share'],

            'User Revenue' =>
                $revenue['net'],

            default =>
                null,
        };
    }

    private function quantityForRow(
        $row
    ): float {
        $streams =
            (float) (
                $row->streams
                ?? 0
            );

        if ($streams != 0.0) {
            return $streams;
        }

        return (float) (
            $row->sale_units
            ?? 0
        );
    }

    private function revenueForRow(
        $row,
        User $user
    ): array {
        $collected =
            round(
                (float) (
                    $row->earnings
                    ?? 0
                ),
                8
            );

        $role =
            $this->permissions
                ->role(
                    $user
                );

        /*
         * Super Admin / Admin operational reports:
         * show source economics without account deductions.
         */
        if (
            $role === 'super_admin'
            || $role === 'admin'
        ) {
            return [
                'gross' => $collected,
                'share' => 100.0,
                'net' => $collected,
            ];
        }

        /*
         * Resolve the fixed commercial rate assigned by
         * Super Admin to this account.
         *
         * IMPORTANT:
         * Negative report rows do not modify this stored
         * rate. They only use an effective 100% rate for
         * that individual report row.
         */
        $accountRate = 100.0;

        if ($role === 'artist') {
            $rate =
                DB::table('artists')
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->value(
                        'revenue_share_percentage'
                    );

            if ($rate !== null) {
                $accountRate =
                    (float) $rate;
            }
        } elseif ($role === 'label') {
            /*
             * Prefer the label attached to this report row
             * when it belongs to the authenticated label
             * account. This keeps multi-label accounts
             * deterministic.
             */
            $rateQuery =
                DB::table('labels')
                    ->where(
                        'user_id',
                        $user->id
                    );

            if (!empty($row->label_id)) {
                $rowRate =
                    (clone $rateQuery)
                        ->where(
                            'id',
                            (int) $row->label_id
                        )
                        ->value(
                            'revenue_share_percentage'
                        );

                if ($rowRate !== null) {
                    $accountRate =
                        (float) $rowRate;
                } else {
                    $fallbackRate =
                        $rateQuery
                            ->orderBy('id')
                            ->value(
                                'revenue_share_percentage'
                            );

                    if ($fallbackRate !== null) {
                        $accountRate =
                            (float) $fallbackRate;
                    }
                }
            } else {
                $fallbackRate =
                    $rateQuery
                        ->orderBy('id')
                        ->value(
                            'revenue_share_percentage'
                        );

                if ($fallbackRate !== null) {
                    $accountRate =
                        (float) $fallbackRate;
                }
            }
        }

        $accountRate =
            max(
                0.0,
                min(
                    100.0,
                    $accountRate
                )
            );

        /*
         * FINAL REVENUE RULE
         * ------------------
         *
         * Positive / zero:
         * Collected Revenue × Assigned Account Rate.
         *
         * Negative:
         * 100% of the negative adjustment belongs to
         * the user. Therefore effective Assigned Rate
         * shown in that row becomes 100%.
         *
         * Example:
         * +100 @ 80% = +80
         *  -10 @100% = -10
         * User Revenue = 70
         */
        $effectiveRate =
            $collected < 0
                ? 100.0
                : $accountRate;

        $userRevenue =
            $collected < 0
                ? $collected
                : round(
                    $collected
                    * (
                        $effectiveRate
                        / 100
                    ),
                    8
                );

        return [
            'gross' =>
                $collected,

            'share' =>
                $effectiveRate,

            'net' =>
                $userRevenue,
        ];
    }

    private function shareResolutionDate(
        $row
    ): ?string {
        /*
         * Revenue-share contracts are resolved
         * on reporting-month basis.
         *
         * Example:
         * sale_date      = 2026-08-01
         * sale_month     = 2026-08
         * effective_from = 2026-08-11
         *
         * The August share must apply to the
         * August royalty period, so resolve
         * against the final day of sale_month.
         */
        if (!empty($row->sale_month)) {
            return Carbon::createFromFormat(
                '!Y-m',
                (string) $row->sale_month
            )
                ->endOfMonth()
                ->toDateString();
        }

        if (!empty($row->sale_date)) {
            return Carbon::parse(
                $row->sale_date
            )
                ->endOfMonth()
                ->toDateString();
        }

        return null;
    }

    private function activeShare(
        string $beneficiaryType,
        int $beneficiaryId,
        ?int $masterLabelId,
        mixed $saleDate
    ): ?object {
        $query =
            DB::table(
                'label_revenue_shares'
            )
                ->where(
                    'beneficiary_type',
                    $beneficiaryType
                )
                ->where(
                    'beneficiary_id',
                    $beneficiaryId
                )
                ->where(
                    'is_active',
                    true
                );

        if ($masterLabelId !== null) {
            $query->where(
                'master_label_id',
                $masterLabelId
            );
        }

        if ($saleDate) {
            $date =
                Carbon::parse(
                    $saleDate
                )->toDateString();

            $query
                ->where(
                    function ($builder) use ($date) {
                        $builder
                            ->whereNull(
                                'effective_from'
                            )
                            ->orWhere(
                                'effective_from',
                                '<=',
                                $date
                            );
                    }
                )
                ->where(
                    function ($builder) use ($date) {
                        $builder
                            ->whereNull(
                                'effective_to'
                            )
                            ->orWhere(
                                'effective_to',
                                '>=',
                                $date
                            );
                    }
                );
        }

        return $query
            ->orderByDesc(
                'effective_from'
            )
            ->orderByDesc('id')
            ->first();
    }

    private function validatePeriod(
        string $fromMonth,
        string $toMonth
    ): void {
        if (
            !preg_match(
                '/^\d{4}-\d{2}$/',
                $fromMonth
            )
            || !preg_match(
                '/^\d{4}-\d{2}$/',
                $toMonth
            )
        ) {
            throw ValidationException::withMessages([
                'from_month' =>
                    'Invalid reporting period.',
            ]);
        }

        $from =
            Carbon::createFromFormat(
                '!Y-m',
                $fromMonth
            );

        $to =
            Carbon::createFromFormat(
                '!Y-m',
                $toMonth
            );

        $difference =
            (
                ((int) $to->format('Y'))
                -
                ((int) $from->format('Y'))
            ) * 12
            +
            (
                ((int) $to->format('n'))
                -
                ((int) $from->format('n'))
            );

        if (
            $difference < 0
            || $difference > 2
        ) {
            throw ValidationException::withMessages([
                'to_month' =>
                    'Reports can cover a maximum of 3 months.',
            ]);
        }
    }
}
