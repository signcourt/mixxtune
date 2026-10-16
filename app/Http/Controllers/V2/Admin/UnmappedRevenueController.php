<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\LabelIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\V2\ReportEarningsRecalculationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnmappedRevenueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $month = trim((string) $request->input('month', ''));

        $reportImportId = $request->filled('report_import_id')
            ? (int) $request->input('report_import_id')
            : null;

        $sort = (string) $request->input(
            'sort',
            'revenue'
        );

        $direction = strtolower(
            (string) $request->input(
                'direction',
                'desc'
            )
        );

        $allowedSorts = [
            'track' => 'track_title',
            'label' => 'report_label',
            'rows' => 'rows_count',
            'revenue' => 'revenue',
        ];

        if (!array_key_exists($sort, $allowedSorts)) {
            $sort = 'revenue';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $base = DB::table('report_rows')
            ->whereNull('label_id')
            ->whereNotNull('isrc')
            ->where('isrc', '!=', '');

        if ($reportImportId !== null && $reportImportId > 0) {
            $base->where('report_import_id', $reportImportId);
        }

        if ($month !== '') {
            $base->where('sale_month', $month);
        }

        if ($search !== '') {
            $base->where(function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query
                    ->where('isrc', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('track_title', 'like', $like)
                    ->orWhere('track_artist', 'like', $like)
                    ->orWhere('label_name', 'like', $like);
            });
        }

        /*
         * Keep the filtered base query untouched.
         * Table sorting/grouping must run on its own clone so summary
         * queries do not inherit GROUP BY / ORDER BY aliases.
         */
        /*
         * Group the UI by normalized ISRC, not by the raw report
         * formatting. Therefore:
         *
         * IN-D71-22-11104
         * IN D71 22 11104
         * IND712211104
         *
         * are displayed as one financial identity.
         */
        $normalizedIsrcSql = "
            REGEXP_REPLACE(
                UPPER(TRIM(isrc)),
                '[^A-Z0-9]',
                ''
            )
        ";

        $rows = (clone $base)
            ->selectRaw("
                {$normalizedIsrcSql} as normalized_isrc,
                MIN(isrc) as isrc,
                MAX(upc) as upc,
                MAX(track_title) as track_title,
                MAX(track_artist) as track_artist,
                MAX(label_name) as report_label,
                MIN(sale_month) as first_month,
                MAX(sale_month) as last_month,
                COUNT(*) as rows_count,
                SUM(collected_revenue) as revenue
            ")
            ->groupByRaw($normalizedIsrcSql)
            ->orderBy(
                $allowedSorts[$sort],
                $direction
            )
            ->orderBy('normalized_isrc')
            ->paginate(50)
            ->withQueryString();

        $identifiers = collect($rows->items())
            ->pluck('isrc')
            ->filter()
            ->map(fn ($value) => $this->normalizeCode($value))
            ->values();

        $rules = DB::table('revenue_mapping_rules')
            ->where('identifier_type', 'isrc')
            ->whereIn('identifier_value', $identifiers)
            ->get()
            ->keyBy('identifier_value');

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) use ($rules) {
                $normalized = $this->normalizeCode($row->isrc);

                $row->normalized_isrc = $normalized;
                $row->rule = $rules->get($normalized);

                return $row;
            })
        );

        /*
         * MIXXTUNE_UNMAPPED_LABEL_DISAMBIGUATION_V1
         *
         * Same label name can legitimately exist under different
         * accounts. Keep IDs intact and expose account information
         * so mapping targets are never ambiguous.
         */
        $labels = DB::table('labels as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereNull('l.deleted_at')
            ->orderBy('l.name')
            ->orderBy('l.id')
            ->get([
                'l.id',
                'l.name',
                'l.public_id',
                'l.parent_label_id',
                'l.label_type',
                'l.revenue_share_percentage',
                'l.user_id',
                'u.name as account_name',
                'u.email as account_email',
            ]);

        $artists = DB::table('artists')
            ->whereNull('deleted_at')
            ->orderBy('stage_name')
            ->get([
                'id',
                'stage_name',
                'label_id',
                'public_id',
            ]);

        $monthsQuery = DB::table('report_rows')
            ->whereNull('label_id')
            ->whereNotNull('sale_month');

        if ($reportImportId !== null && $reportImportId > 0) {
            $monthsQuery->where(
                'report_import_id',
                $reportImportId
            );
        }

        $months = $monthsQuery
            ->distinct()
            ->orderByDesc('sale_month')
            ->pluck('sale_month');

        /*
         * Summary must use exactly the same filters as the table.
         * Clone $base before aggregation so search/month stay consistent.
         */
        $summaryBase = clone $base;

        $summary = [
            'rows' => (clone $summaryBase)->count(),

            'revenue' => (float) (clone $summaryBase)
                ->sum('collected_revenue'),

            'unique_isrc' => (clone $summaryBase)
                ->selectRaw("
                    {$normalizedIsrcSql} as normalized_isrc
                ")
                ->distinct()
                ->count(
                    DB::raw($normalizedIsrcSql)
                ),

            'rules' => DB::table('revenue_mapping_rules')->count(),
        ];

        $sourceImport = null;

        if ($reportImportId !== null && $reportImportId > 0) {
            $sourceImport = DB::table('report_imports')
                ->where('id', $reportImportId)
                ->first([
                    'id',
                    'original_filename',
                    'reporting_month',
                    'status',
                    'financial_status',
                ]);

            abort_unless(
                $sourceImport,
                404,
                'Report import not found.'
            );
        }

        return Inertia::render(
            'V2/Admin/UnmappedRevenue/Index',
            [
                'rows' => $rows,
                'sourceImport' => $sourceImport,
                'labels' => $labels,
                'artists' => $artists,
                'months' => $months,
                'summary' => $summary,
                'mappingPreview' => $request->session()->get(
                'unmapped_mapping_preview'
            ),
            'filters' => [
                    'sort' => $sort,
                    'direction' => $direction,
                    'search' => $search,
                    'month' => $month,
                    'report_import_id' => $reportImportId,
                ],
            ]
        );
    }

    /*
     * MIXXTUNE_UNMAPPED_MAPPING_XLSX_EXPORT_V1
     *
     * Read-only workbook for bulk ownership mapping.
     * One normalized ISRC = one spreadsheet row.
     */
    public function exportMappingWorkbook(Request $request)
    {
        $reportImportId = $request->filled('report_import_id')
            ? (int) $request->input('report_import_id')
            : null;

        if ($reportImportId !== null) {
            abort_if(
                $reportImportId < 1,
                422,
                'Invalid report import.'
            );

            abort_unless(
                DB::table('report_imports')
                    ->where('id', $reportImportId)
                    ->exists(),
                404,
                'Report import not found.'
            );
        }

        $query = DB::table('report_rows as rr')
            ->where('rr.mapping_status', 'unmapped')
            ->whereNotNull('rr.isrc')
            ->where('rr.isrc', '<>', '');

        if ($reportImportId !== null) {
            $query->where(
                'rr.report_import_id',
                $reportImportId
            );
        }

        $rows = $query
            ->get([
                'rr.isrc',
                'rr.upc',
                'rr.track_title',
                'rr.track_artist',
                'rr.label_name',
                'rr.collected_revenue',
            ])
            ->groupBy(
                fn ($row) =>
                    $this->normalizeCode($row->isrc)
            )
            ->filter(
                fn ($group, $identifier) =>
                    $identifier !== ''
            );

        abort_if(
            $rows->isEmpty(),
            422,
            'No unmapped ISRCs are available to export.'
        );

        $spreadsheet =
            new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Unmapped ISRC');

        $headers = [
            'ISRC',
            'UPC',
            'Track',
            'Artist',
            'Report Label',
            'Unmapped Rows',
            'Collected Revenue',
            'Master Label ID',
            'Catalogue Label ID',
            'Artist ID (Optional)',
            'Mapping Note',
        ];

        foreach ($headers as $column => $header) {
            $sheet->setCellValue(
                [$column + 1, 1],
                $header
            );
        }

        $excelRow = 2;

        foreach ($rows as $identifier => $group) {
            $first = $group->first();

            $sheet->setCellValueExplicit(
                [1, $excelRow],
                $identifier,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            $sheet->setCellValueExplicit(
                [2, $excelRow],
                (string) ($first->upc ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );

            $sheet->setCellValue(
                [3, $excelRow],
                (string) ($first->track_title ?? '')
            );

            $sheet->setCellValue(
                [4, $excelRow],
                (string) ($first->track_artist ?? '')
            );

            $sheet->setCellValue(
                [5, $excelRow],
                (string) ($first->label_name ?? '')
            );

            $sheet->setCellValue(
                [6, $excelRow],
                $group->count()
            );

            $sheet->setCellValue(
                [7, $excelRow],
                round(
                    (float) $group->sum(
                        'collected_revenue'
                    ),
                    8
                )
            );

            /*
             * Mapping columns intentionally blank.
             * IDs are authoritative during future import.
             */
            $sheet->setCellValue(
                [8, $excelRow],
                ''
            );

            $sheet->setCellValue(
                [9, $excelRow],
                ''
            );

            $sheet->setCellValue(
                [10, $excelRow],
                ''
            );

            $sheet->setCellValue(
                [11, $excelRow],
                ''
            );

            $excelRow++;
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter(
            'A1:K'.($excelRow - 1)
        );

        $sheet->getStyle('A1:K1')
            ->getFont()
            ->setBold(true);

        foreach (
            [
                'A' => 18,
                'B' => 18,
                'C' => 34,
                'D' => 34,
                'E' => 28,
                'F' => 16,
                'G' => 20,
                'H' => 18,
                'I' => 20,
                'J' => 20,
                'K' => 36,
            ]
            as $column => $width
        ) {
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }

        /*
         * Reference sheet:
         * gives authoritative IDs so names/spellings
         * are never used as financial mapping keys.
         */
        $reference =
            $spreadsheet->createSheet();

        $reference->setTitle('Label Reference');

        $referenceHeaders = [
            'Label ID',
            'Label Name',
            'Parent Label ID',
            'Revenue Rate %',
        ];

        foreach (
            $referenceHeaders
            as $column => $header
        ) {
            $reference->setCellValue(
                [$column + 1, 1],
                $header
            );
        }

        $labels = DB::table('labels')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'parent_label_id',
                'revenue_share_percentage',
            ]);

        $referenceRow = 2;

        foreach ($labels as $label) {
            $reference->setCellValue(
                [1, $referenceRow],
                $label->id
            );

            $reference->setCellValue(
                [2, $referenceRow],
                $label->name
            );

            $reference->setCellValue(
                [3, $referenceRow],
                $label->parent_label_id
            );

            $reference->setCellValue(
                [4, $referenceRow],
                $label->revenue_share_percentage
            );

            $referenceRow++;
        }

        $reference->freezePane('A2');
        $reference->setAutoFilter(
            'A1:D'.max(1, $referenceRow - 1)
        );
        $reference->getStyle('A1:D1')
            ->getFont()
            ->setBold(true);

        $reference->getColumnDimension('A')
            ->setWidth(14);
        $reference->getColumnDimension('B')
            ->setWidth(36);
        $reference->getColumnDimension('C')
            ->setWidth(18);
        $reference->getColumnDimension('D')
            ->setWidth(18);

        /*
         * Artist reference.
         */
        $artistSheet =
            $spreadsheet->createSheet();

        $artistSheet->setTitle('Artist Reference');

        $artistHeaders = [
            'Artist ID',
            'Artist Name',
            'Label ID',
            'Revenue Rate %',
        ];

        foreach (
            $artistHeaders
            as $column => $header
        ) {
            $artistSheet->setCellValue(
                [$column + 1, 1],
                $header
            );
        }

        $artists = DB::table('artists')
            ->orderBy('stage_name')
            ->orderBy('id')
            ->get([
                'id',
                'stage_name',
                'label_id',
                'revenue_share_percentage',
            ]);

        $artistRow = 2;

        foreach ($artists as $artist) {
            $artistSheet->setCellValue(
                [1, $artistRow],
                $artist->id
            );

            $artistSheet->setCellValue(
                [2, $artistRow],
                $artist->stage_name
            );

            $artistSheet->setCellValue(
                [3, $artistRow],
                $artist->label_id
            );

            $artistSheet->setCellValue(
                [4, $artistRow],
                $artist->revenue_share_percentage
            );

            $artistRow++;
        }

        $artistSheet->freezePane('A2');
        $artistSheet->setAutoFilter(
            'A1:D'.max(1, $artistRow - 1)
        );
        $artistSheet->getStyle('A1:D1')
            ->getFont()
            ->setBold(true);

        $artistSheet->getColumnDimension('A')
            ->setWidth(14);
        $artistSheet->getColumnDimension('B')
            ->setWidth(36);
        $artistSheet->getColumnDimension('C')
            ->setWidth(14);
        $artistSheet->getColumnDimension('D')
            ->setWidth(18);

        $spreadsheet->setActiveSheetIndex(0);

        $filename =
            'unmapped-revenue-mapping'.
            ($reportImportId !== null
                ? '-import-'.$reportImportId
                : '').
            '-'.now()->format('Ymd-His').
            '.xlsx';

        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer =
                    new \PhpOffice\PhpSpreadsheet\Writer\Xlsx(
                        $spreadsheet
                    );

                $writer->save('php://output');

                $spreadsheet
                    ->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    /*
     * MIXXTUNE_BULK_MAPPING_XLSX_PREVIEW_V1
     *
     * XLSX validation / preview only.
     * ZERO mapping, financial, wallet or publication mutation.
     */
    public function previewMappingWorkbook(Request $request)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240',
            ],
            'report_import_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $reportImportId = isset(
            $validated['report_import_id']
        )
            ? (int) $validated['report_import_id']
            : null;

        if ($reportImportId !== null) {
            $import = DB::table('report_imports')
                ->where('id', $reportImportId)
                ->first([
                    'id',
                    'financial_status',
                ]);

            abort_unless(
                $import,
                404,
                'Report import not found.'
            );

            abort_unless(
                $import->financial_status ===
                    'review_pending',
                422,
                'Only review-pending imports can be bulk mapped.'
            );
        }

        try {
            $spreadsheet =
                \PhpOffice\PhpSpreadsheet\IOFactory::load(
                    $request->file('file')
                        ->getRealPath()
                );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' =>
                    'Unable to read the Excel workbook.',
            ]);
        }

        $sheet = $spreadsheet->getSheetByName(
            'Unmapped ISRC'
        );

        if (!$sheet) {
            $spreadsheet->disconnectWorksheets();

            return back()->withErrors([
                'file' =>
                    'Sheet "Unmapped ISRC" was not found.',
            ]);
        }

        $expectedHeaders = [
            'A' => 'ISRC',
            'H' => 'Master Label ID',
            'I' => 'Catalogue Label ID',
            'J' => 'Artist ID (Optional)',
            'K' => 'Mapping Note',
        ];

        foreach (
            $expectedHeaders
            as $column => $expected
        ) {
            $actual = trim(
                (string) $sheet
                    ->getCell($column.'1')
                    ->getValue()
            );

            if ($actual !== $expected) {
                $spreadsheet->disconnectWorksheets();

                return back()->withErrors([
                    'file' =>
                        'Invalid mapping template. '.
                        'Expected "'.$expected.
                        '" in '.$column.'1.',
                ]);
            }
        }

        $highestRow = $sheet->getHighestDataRow();

        $rawRows = [];
        $duplicateTracker = [];

        for (
            $excelRow = 2;
            $excelRow <= $highestRow;
            $excelRow++
        ) {
            $isrc = $this->normalizeCode(
                (string) $sheet
                    ->getCell('A'.$excelRow)
                    ->getFormattedValue()
            );

            $masterRaw = trim(
                (string) $sheet
                    ->getCell('H'.$excelRow)
                    ->getFormattedValue()
            );

            $catalogueRaw = trim(
                (string) $sheet
                    ->getCell('I'.$excelRow)
                    ->getFormattedValue()
            );

            $artistRaw = trim(
                (string) $sheet
                    ->getCell('J'.$excelRow)
                    ->getFormattedValue()
            );

            $note = trim(
                (string) $sheet
                    ->getCell('K'.$excelRow)
                    ->getFormattedValue()
            );

            /*
             * Completely blank mapping line:
             * intentionally skipped.
             */
            if (
                $isrc === '' &&
                $masterRaw === '' &&
                $catalogueRaw === '' &&
                $artistRaw === '' &&
                $note === ''
            ) {
                continue;
            }

            /*
             * Exported reference row where user has
             * not filled any mapping fields.
             */
            if (
                $isrc !== '' &&
                $masterRaw === '' &&
                $catalogueRaw === '' &&
                $artistRaw === '' &&
                $note === ''
            ) {
                $rawRows[] = [
                    'excel_row' => $excelRow,
                    'isrc' => $isrc,
                    'master_label_id' => null,
                    'catalogue_label_id' => null,
                    'artist_id' => null,
                    'note' => '',
                    'status' => 'skipped',
                    'message' =>
                        'No mapping details entered.',
                ];

                continue;
            }

            $masterId = ctype_digit($masterRaw)
                ? (int) $masterRaw
                : null;

            $catalogueId = ctype_digit(
                $catalogueRaw
            )
                ? (int) $catalogueRaw
                : null;

            $artistId = $artistRaw === ''
                ? null
                : (
                    ctype_digit($artistRaw)
                        ? (int) $artistRaw
                        : null
                );

            $rawRows[] = [
                'excel_row' => $excelRow,
                'isrc' => $isrc,
                'master_label_id' => $masterId,
                'catalogue_label_id' =>
                    $catalogueId,
                'artist_id' => $artistId,
                'note' => mb_substr(
                    $note,
                    0,
                    1000
                ),
                'status' => 'pending',
                'message' => '',
                '_raw_master' => $masterRaw,
                '_raw_catalogue' =>
                    $catalogueRaw,
                '_raw_artist' => $artistRaw,
            ];

            if ($isrc !== '') {
                $duplicateTracker[$isrc][] =
                    count($rawRows) - 1;
            }
        }

        $labelIds = collect($rawRows)
            ->filter(
                fn ($row) =>
                    $row['status'] === 'pending'
            )
            ->flatMap(
                fn ($row) => [
                    $row['master_label_id'],
                    $row['catalogue_label_id'],
                ]
            )
            ->filter()
            ->unique()
            ->values();

        $labels = DB::table('labels')
            ->whereIn('id', $labelIds)
            ->get([
                'id',
                'name',
                'parent_label_id',
            ])
            ->keyBy('id');

        $artistIds = collect($rawRows)
            ->filter(
                fn ($row) =>
                    $row['status'] === 'pending'
            )
            ->pluck('artist_id')
            ->filter()
            ->unique()
            ->values();

        $artists = DB::table('artists')
            ->whereIn('id', $artistIds)
            ->get([
                'id',
                'stage_name',
                'label_id',
            ])
            ->keyBy('id');

        $rootFor = function (
            int $labelId
        ) use ($labels) {
            $current = $labelId;
            $seen = [];

            while ($current > 0) {
                if (isset($seen[$current])) {
                    return null;
                }

                $seen[$current] = true;

                $label = $labels->get($current);

                if (!$label) {
                    $label = DB::table('labels')
                        ->where('id', $current)
                        ->first([
                            'id',
                            'parent_label_id',
                        ]);
                }

                if (!$label) {
                    return null;
                }

                $parent = (int) (
                    $label->parent_label_id ?? 0
                );

                if ($parent < 1) {
                    return (int) $label->id;
                }

                $current = $parent;
            }

            return null;
        };

        foreach ($rawRows as $index => &$row) {
            if ($row['status'] !== 'pending') {
                continue;
            }

            $errors = [];

            if ($row['isrc'] === '') {
                $errors[] = 'ISRC is missing.';
            }

            if (
                $row['_raw_master'] === '' ||
                $row['master_label_id'] === null
            ) {
                $errors[] =
                    'Master Label ID must be a valid integer.';
            }

            if (
                $row['_raw_catalogue'] === '' ||
                $row['catalogue_label_id'] === null
            ) {
                $errors[] =
                    'Catalogue Label ID must be a valid integer.';
            }

            if (
                $row['_raw_artist'] !== '' &&
                $row['artist_id'] === null
            ) {
                $errors[] =
                    'Artist ID must be a valid integer.';
            }

            $master = $row['master_label_id']
                ? $labels->get(
                    $row['master_label_id']
                )
                : null;

            $catalogue =
                $row['catalogue_label_id']
                    ? $labels->get(
                        $row['catalogue_label_id']
                    )
                    : null;

            if (
                $row['master_label_id'] &&
                !$master
            ) {
                $errors[] =
                    'Master Label ID does not exist.';
            }

            if (
                $row['catalogue_label_id'] &&
                !$catalogue
            ) {
                $errors[] =
                    'Catalogue Label ID does not exist.';
            }

            if ($master) {
                $masterRoot = $rootFor(
                    (int) $master->id
                );

                if (
                    $masterRoot !==
                    (int) $master->id
                ) {
                    $errors[] =
                        'Selected Master Label is not a root label.';
                }
            }

            if (
                $master &&
                $catalogue
            ) {
                $catalogueRoot = $rootFor(
                    (int) $catalogue->id
                );

                if (
                    $catalogueRoot !==
                    (int) $master->id
                ) {
                    $errors[] =
                        'Catalogue Label does not belong to the selected Master Label.';
                }
            }

            $artist = $row['artist_id']
                ? $artists->get(
                    $row['artist_id']
                )
                : null;

            if (
                $row['artist_id'] &&
                !$artist
            ) {
                $errors[] =
                    'Artist ID does not exist.';
            }

            if (
                $artist &&
                $catalogue &&
                (int) ($artist->label_id ?? 0) !==
                    (int) $catalogue->id
            ) {
                $errors[] =
                    'Artist does not belong to the selected Catalogue Label.';
            }

            if (
                $row['isrc'] !== '' &&
                isset(
                    $duplicateTracker[
                        $row['isrc']
                    ]
                ) &&
                count(
                    $duplicateTracker[
                        $row['isrc']
                    ]
                ) > 1
            ) {
                $errors[] =
                    'Duplicate ISRC exists in workbook.';
            }

            if ($row['isrc'] !== '') {
                $scope = DB::table(
                    'report_rows as rr'
                )
                    ->join(
                        'report_imports as ri',
                        'ri.id',
                        '=',
                        'rr.report_import_id'
                    )
                    ->where(
                        'ri.financial_status',
                        'review_pending'
                    )
                    ->where(
                        'rr.mapping_status',
                        'unmapped'
                    )
                    ->whereRaw(
                        "REGEXP_REPLACE(UPPER(TRIM(rr.isrc)), '[^A-Z0-9]', '') = ?",
                        [$row['isrc']]
                    );

                if ($reportImportId !== null) {
                    $scope->where(
                        'rr.report_import_id',
                        $reportImportId
                    );
                }

                $rowCount = $scope->count();

                if ($rowCount < 1) {
                    $errors[] =
                        'No review-pending unmapped rows currently match this ISRC.';
                }

                $row['matching_rows'] =
                    $rowCount;

                $row['collected_revenue'] =
                    round(
                        (float) (
                            clone $scope
                        )->sum(
                            'rr.collected_revenue'
                        ),
                        8
                    );
            }

            $existingRule = null;

            if ($row['isrc'] !== '') {
                $existingRule = DB::table(
                    'revenue_mapping_rules'
                )
                    ->where(
                        'identifier_type',
                        'isrc'
                    )
                    ->where(
                        'identifier_value',
                        $row['isrc']
                    )
                    ->first();
            }

            if ($existingRule) {
                $same =
                    $existingRule
                        ->revenue_owner_type ===
                        'label' &&
                    (int) $existingRule
                        ->revenue_owner_id ===
                        (int) $row[
                            'master_label_id'
                        ] &&
                    (int) (
                        $existingRule
                            ->catalogue_label_id
                        ?? 0
                    ) ===
                        (int) $row[
                            'catalogue_label_id'
                        ] &&
                    (int) (
                        $existingRule
                            ->artist_id
                        ?? 0
                    ) ===
                        (int) (
                            $row['artist_id']
                            ?? 0
                        );

                if (!$same) {
                    $errors[] =
                        'ISRC already has a different persistent mapping rule.';
                }
            }

            $row['master_label_name'] =
                $master->name ?? null;

            $row['catalogue_label_name'] =
                $catalogue->name ?? null;

            $row['artist_name'] =
                $artist->stage_name ?? null;

            if ($errors) {
                $row['status'] = 'invalid';
                $row['message'] =
                    implode(' ', $errors);
            } else {
                $row['status'] = 'valid';
                $row['message'] =
                    'Ready for bulk mapping.';
            }

            unset(
                $row['_raw_master'],
                $row['_raw_catalogue'],
                $row['_raw_artist']
            );
        }

        unset($row);

        $spreadsheet->disconnectWorksheets();

        foreach ($rawRows as &$row) {
            unset(
                $row['_raw_master'],
                $row['_raw_catalogue'],
                $row['_raw_artist']
            );
        }

        unset($row);

        $preview = [
            'report_import_id' =>
                $reportImportId,
            'total_rows' =>
                count($rawRows),
            'valid_rows' =>
                collect($rawRows)
                    ->where('status', 'valid')
                    ->count(),
            'invalid_rows' =>
                collect($rawRows)
                    ->where('status', 'invalid')
                    ->count(),
            'skipped_rows' =>
                collect($rawRows)
                    ->where('status', 'skipped')
                    ->count(),
            'rows' => $rawRows,
        ];

        /*
         * Preview lives only in session.
         * No database mutation occurs here.
         */
        $request->session()->put(
            'unmapped_mapping_preview',
            $preview
        );

        return redirect()
            ->route(
                'single.super-admin.unmapped-revenue.index',
                array_filter([
                    'report_import_id' =>
                        $reportImportId,
                ])
            )
            ->with(
                'success',
                'Excel validated: '.
                $preview['valid_rows'].
                ' valid, '.
                $preview['invalid_rows'].
                ' invalid, '.
                $preview['skipped_rows'].
                ' skipped.'
            );
    }

    /*
     * MIXXTUNE_BULK_MAPPING_XLSX_APPLY_V1
     *
     * Bulk XLSX hierarchy mapping.
     *
     * Financial safety:
     * - Excel financial/reference columns are NEVER trusted.
     * - Only mapping IDs + ISRC + note are consumed.
     * - Persistent ISRC rule is saved.
     * - applyIsrcRule() mutates review_pending imports only.
     * - approved_scheduled / published remain immutable.
     * - NO wallet posting.
     * - NO publication.
     */
    public function applyMappingWorkbook(Request $request)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:10240',
            ],

            'report_import_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $reportImportId =
            !empty($validated['report_import_id'])
                ? (int) $validated['report_import_id']
                : null;

        if ($reportImportId !== null) {
            $import = DB::table('report_imports')
                ->where('id', $reportImportId)
                ->first([
                    'id',
                    'financial_status',
                ]);

            abort_unless(
                $import,
                404,
                'Report import not found.'
            );

            abort_unless(
                $import->financial_status ===
                    'review_pending',
                422,
                'Only review_pending imports can be bulk mapped.'
            );
        }

        $spreadsheet =
            \PhpOffice\PhpSpreadsheet\IOFactory::load(
                $request->file('file')->getRealPath()
            );

        $sheet =
            $spreadsheet->getSheetByName(
                'Unmapped ISRC'
            );

        abort_unless(
            $sheet,
            422,
            'Worksheet "Unmapped ISRC" was not found.'
        );

        $highestRow =
            (int) $sheet->getHighestDataRow();

        abort_if(
            $highestRow < 2,
            422,
            'The mapping workbook contains no data rows.'
        );

        /*
         * Exact template header verification.
         */
        $expectedHeaders = [
            'ISRC',
            'UPC',
            'Track',
            'Artist',
            'Report Label',
            'Unmapped Rows',
            'Collected Revenue',
            'Master Label ID',
            'Catalogue Label ID',
            'Artist ID (Optional)',
            'Mapping Note',
        ];

        foreach (
            $expectedHeaders as $index => $expected
        ) {
            $actual = trim(
                (string) $sheet->getCell(
                    [$index + 1, 1]
                )->getValue()
            );

            abort_unless(
                $actual === $expected,
                422,
                'Invalid mapping workbook header at column '.
                    ($index + 1).
                    '. Expected "'.
                    $expected.
                    '".'
            );
        }

        $entries = [];
        $errors = [];
        $seen = [];

        for (
            $excelRow = 2;
            $excelRow <= $highestRow;
            $excelRow++
        ) {
            $identifier =
                $this->normalizeCode(
                    (string) $sheet->getCell(
                        [1, $excelRow]
                    )->getFormattedValue()
                );

            $masterRaw = trim(
                (string) $sheet->getCell(
                    [8, $excelRow]
                )->getFormattedValue()
            );

            $catalogueRaw = trim(
                (string) $sheet->getCell(
                    [9, $excelRow]
                )->getFormattedValue()
            );

            $artistRaw = trim(
                (string) $sheet->getCell(
                    [10, $excelRow]
                )->getFormattedValue()
            );

            $notes = trim(
                (string) $sheet->getCell(
                    [11, $excelRow]
                )->getFormattedValue()
            );

            /*
             * Completely blank mapping row:
             * intentionally skipped.
             */
            if (
                $identifier === ''
                && $masterRaw === ''
                && $catalogueRaw === ''
                && $artistRaw === ''
                && $notes === ''
            ) {
                continue;
            }

            /*
             * ISRC exists but mapping columns are blank:
             * user intentionally did not map this item.
             */
            if (
                $identifier !== ''
                && $masterRaw === ''
                && $catalogueRaw === ''
                && $artistRaw === ''
            ) {
                continue;
            }

            if ($identifier === '') {
                $errors[] =
                    "Excel row {$excelRow}: invalid ISRC.";
                continue;
            }

            if (isset($seen[$identifier])) {
                $errors[] =
                    "Excel row {$excelRow}: duplicate ISRC {$identifier}.";
                continue;
            }

            $seen[$identifier] = true;

            if (
                !ctype_digit($masterRaw)
                || (int) $masterRaw < 1
            ) {
                $errors[] =
                    "Excel row {$excelRow}: invalid Master Label ID.";
                continue;
            }

            if (
                !ctype_digit($catalogueRaw)
                || (int) $catalogueRaw < 1
            ) {
                $errors[] =
                    "Excel row {$excelRow}: invalid Catalogue Label ID.";
                continue;
            }

            if (
                $artistRaw !== ''
                && (
                    !ctype_digit($artistRaw)
                    || (int) $artistRaw < 1
                )
            ) {
                $errors[] =
                    "Excel row {$excelRow}: invalid Artist ID.";
                continue;
            }

            $masterId = (int) $masterRaw;
            $catalogueLabelId =
                (int) $catalogueRaw;

            $artistId =
                $artistRaw !== ''
                    ? (int) $artistRaw
                    : null;

            $master =
                \App\Models\Core\Label::query()
                    ->find($masterId);

            if (!$master) {
                $errors[] =
                    "Excel row {$excelRow}: Master Label ID {$masterId} not found.";
                continue;
            }

            $catalogueLabel =
                \App\Models\Core\Label::query()
                    ->find($catalogueLabelId);

            if (!$catalogueLabel) {
                $errors[] =
                    "Excel row {$excelRow}: Catalogue Label ID {$catalogueLabelId} not found.";
                continue;
            }

            /*
             * Reuse the same hierarchy semantics as the
             * manual mapping UI.
             *
             * Walk upwards from catalogue placement until
             * the root label is reached.
             */
            $root = $catalogueLabel;
            $visited = [];

            while (
                $root->parent_label_id !== null
            ) {
                abort_if(
                    isset($visited[$root->id]),
                    422,
                    'Invalid cyclic label hierarchy.'
                );

                $visited[$root->id] = true;

                $parent =
                    \App\Models\Core\Label::query()
                        ->find(
                            $root->parent_label_id
                        );

                if (!$parent) {
                    break;
                }

                $root = $parent;
            }

            if (
                (int) $root->id !== $masterId
            ) {
                $errors[] =
                    "Excel row {$excelRow}: Catalogue Label ID {$catalogueLabelId} does not belong to Master Label ID {$masterId}.";
                continue;
            }

            if ($artistId !== null) {
                $artist =
                    \App\Models\Core\Artist::query()
                        ->find($artistId);

                if (!$artist) {
                    $errors[] =
                        "Excel row {$excelRow}: Artist ID {$artistId} not found.";
                    continue;
                }

                /*
                 * Artist must belong somewhere inside the
                 * selected catalogue branch.
                 */
                if (
                    !$artist->label_id
                    || (int) $artist->label_id !==
                        $catalogueLabelId
                ) {
                    $errors[] =
                        "Excel row {$excelRow}: Artist ID {$artistId} does not belong to Catalogue Label ID {$catalogueLabelId}.";
                    continue;
                }
            }

            /*
             * The uploaded ISRC must still have an
             * unresolved row in the requested scope.
             *
             * This prevents stale workbooks from creating
             * unrelated persistent rules.
             */
            $sourceQuery =
                DB::table('report_rows as rr')
                    ->join(
                        'report_imports as ri',
                        'ri.id',
                        '=',
                        'rr.report_import_id'
                    )
                    ->where(
                        'rr.mapping_status',
                        'unmapped'
                    )
                    ->where(
                        'ri.financial_status',
                        'review_pending'
                    )
                    ->whereRaw(
                        "
                        REGEXP_REPLACE(
                            UPPER(TRIM(rr.isrc)),
                            '[^A-Z0-9]',
                            ''
                        ) = ?
                        ",
                        [$identifier]
                    );

            if ($reportImportId !== null) {
                $sourceQuery->where(
                    'rr.report_import_id',
                    $reportImportId
                );
            }

            if (!$sourceQuery->exists()) {
                $errors[] =
                    "Excel row {$excelRow}: ISRC {$identifier} is no longer unresolved in this scope.";
                continue;
            }

            $entries[] = [
                'excel_row' =>
                    $excelRow,

                'identifier' =>
                    $identifier,

                'master_id' =>
                    $masterId,

                'catalogue_label_id' =>
                    $catalogueLabelId,

                'artist_id' =>
                    $artistId,

                'notes' =>
                    $notes !== ''
                        ? $notes
                        : null,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        abort_if(
            !empty($errors),
            422,
            implode(
                ' ',
                array_slice($errors, 0, 20)
            ).
            (
                count($errors) > 20
                    ? ' Additional validation errors: '.
                        (count($errors) - 20).
                        '.'
                    : ''
            )
        );

        abort_if(
            empty($entries),
            422,
            'No completed mapping rows were found in the workbook.'
        );

        $userId =
            auth()->id();

        $rulesSaved = 0;
        $mappedRows = 0;

        DB::transaction(
            function () use (
                $entries,
                $userId,
                &$rulesSaved,
                &$mappedRows,
                $reportImportId
            ) {
                foreach ($entries as $entry) {
                    /*
                     * Financial owner is the root/master.
                     *
                     * Exact catalogue placement remains
                     * catalogue_label_id.
                     */
                    DB::table(
                        'revenue_mapping_rules'
                    )->updateOrInsert(
                        [
                            'identifier_type' =>
                                'isrc',

                            'identifier_value' =>
                                $entry['identifier'],
                        ],
                        [
                            'owner_type' =>
                                'label',

                            'owner_id' =>
                                $entry['master_id'],

                            'catalogue_label_id' =>
                                $entry[
                                    'catalogue_label_id'
                                ],

                            'artist_id' =>
                                $entry['artist_id'],

                            'track_id' =>
                                null,

                            'release_id' =>
                                null,

                            'source' =>
                                'bulk_xlsx',

                            'notes' =>
                                $entry['notes'],

                            'updated_by' =>
                                $userId,

                            'updated_at' =>
                                now(),

                            'created_by' =>
                                $userId,

                            'created_at' =>
                                now(),
                        ]
                    );

                    $rulesSaved++;

                    /*
                     * Existing authoritative mapping helper.
                     *
                     * It already:
                     * - locks rows,
                     * - limits mutation to review_pending,
                     * - uses Collected Revenue,
                     * - applies commercial rate,
                     * - calculates earnings,
                     * - resolves missing currency.
                     */
                    $mappedRows +=
                        $this->applyIsrcRule(
                            $entry['identifier'],
                            'label',
                            $entry['master_id'],
                            $entry[
                                'catalogue_label_id'
                            ],
                            $entry['artist_id'],
                            $reportImportId
                        );
                }
            }
        );

        return back()->with(
            'success',
            $rulesSaved.
            ' persistent ISRC mapping rule(s) saved and '.
            $mappedRows.
            ' review-pending report row(s) mapped. No wallet or publication posting was performed.'
        );
    }

    public function store(Request $request)
    {
        \Log::info('MIXXTUNE_MAP_ISRC_LIVE_REQUEST', [
            'user_id' => optional($request->user())->id,
            'identifier_type' => $request->input('identifier_type'),
            'identifier_value' => $request->input('identifier_value'),
            'owner_type' => $request->input('owner_type'),
            'owner_id' => $request->input('owner_id'),
            'catalogue_label_id' => $request->input('catalogue_label_id'),
            'artist_id' => $request->input('artist_id'),
            'report_import_id' => $request->input('report_import_id'),
            'url' => $request->fullUrl(),
        ]);
        $data = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:191',
            ],

            'catalogue_label_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'artist_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'report_import_id' => [
                'nullable',
                'integer',
                'exists:report_imports,id',
            ],
        ]);

        $reportImportId =
            !empty($data['report_import_id'])
                ? (int) $data['report_import_id']
                : null;

        $identifier =
            $this->normalizeCode(
                $data['isrc']
            );

        abort_if(
            $identifier === '',
            422,
            'Invalid ISRC.'
        );

        $catalogueLabelId =
            (int) $data[
                'catalogue_label_id'
            ];

        $artistId =
            !empty($data['artist_id'])
                ? (int) $data['artist_id']
                : null;

        $this->validateHierarchyTarget(
            $catalogueLabelId,
            $artistId
        );

        $financialOwnerId =
            app(
                \App\Services\V2\LabelHierarchyService::class
            )->rootLabelId(
                $catalogueLabelId
            );

        DB::transaction(
            function () use (
                $data,
                $identifier,
                $request,
                $catalogueLabelId,
                $artistId,
                $financialOwnerId,
                $reportImportId
            ) {
                $userId =
                    $request->user()?->id;

                DB::table(
                    'revenue_mapping_rules'
                )->updateOrInsert(
                    [
                        'identifier_type' =>
                            'isrc',

                        'identifier_value' =>
                            $identifier,
                    ],
                    [
                        /*
                         * Financial owner is always
                         * the hierarchy root/master.
                         */
                        'owner_type' =>
                            'label',

                        'owner_id' =>
                            $financialOwnerId,

                        /*
                         * Catalogue placement remains
                         * the exact selected level.
                         */
                        'catalogue_label_id' =>
                            $catalogueLabelId,

                        'artist_id' =>
                            $artistId,

                        'source' =>
                            'manual_hierarchy',

                        'notes' =>
                            $data['notes']
                                ?? null,

                        'updated_by' =>
                            $userId,

                        'updated_at' =>
                            now(),

                        'created_by' =>
                            $userId,

                        'created_at' =>
                            now(),
                    ]
                );

                $this->applyIsrcRule(
                    $identifier,
                    'label',
                    $financialOwnerId,
                    $catalogueLabelId,
                    $artistId,
                    $reportImportId
                );
            }
        );

        return back()->with(
            'success',
            'ISRC hierarchy mapping saved and existing report rows updated.'
        );
    }

    /*
     * MIXXTUNE_BULK_SELECTED_MAPPING_BACKEND_V1
     *
     * Maps explicitly selected ISRC identifiers to one
     * validated catalogue hierarchy.
     *
     * Financial owner is derived from the selected
     * Catalogue Label's root hierarchy.
     *
     * applyIsrcRule() remains the canonical report-row
     * mutation path and protects approved/published imports.
     */
    public function bulkMapSelected(Request $request)
    {
        /*
         * MIXXTUNE_BULK_MAP_SELECTED_LIVE_REQUEST
         * Temporary diagnostic trace. Remove after verification.
         */
        \Log::info(
            'MIXXTUNE_BULK_MAP_SELECTED_LIVE_REQUEST',
            [
                'user_id' => auth()->id(),
                'isrc_count' => count((array) $request->input('isrcs', [])),
                'isrcs' => array_values((array) $request->input('isrcs', [])),
                'catalogue_label_id' => $request->input('catalogue_label_id'),
                'artist_id' => $request->input('artist_id'),
                'report_import_id' => $request->input('report_import_id'),
                'url' => $request->fullUrl(),
            ]
        );

        $data = $request->validate([
            'isrcs' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            'isrcs.*' => [
                'required',
                'string',
                'max:64',
            ],
            'catalogue_label_id' => [
                'required',
                'integer',
                'exists:labels,id',
            ],
            'artist_id' => [
                'nullable',
                'integer',
                'exists:artists,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'report_import_id' => [
                'nullable',
                'integer',
                'exists:report_imports,id',
            ],
        ]);

        $catalogueLabelId =
            (int) $data['catalogue_label_id'];

        $artistId =
            !empty($data['artist_id'])
                ? (int) $data['artist_id']
                : null;

        /*
         * Reuse the same hierarchy validator used by
         * individual mapping.
         */
        $this->validateHierarchyTarget(
            $catalogueLabelId,
            $artistId
        );

        $financialOwnerId =
            app(
                \App\Services\V2\LabelHierarchyService::class
            )->rootLabelId(
                $catalogueLabelId
            );

        if ((int) $financialOwnerId <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'catalogue_label_id' =>
                    'Unable to resolve the financial owner for the selected Catalogue Label.',
            ]);
        }

        $identifiers = collect($data['isrcs'])
            ->map(function ($value) {
                return strtoupper(
                    preg_replace(
                        '/[^A-Z0-9]/',
                        '',
                        trim((string) $value)
                    )
                );
            })
            ->filter()
            ->unique()
            ->values();

        if ($identifiers->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'isrcs' =>
                    'No valid ISRC identifiers were selected.',
            ]);
        }

        /*
         * When opened from a specific report, selected
         * identifiers must still be unresolved in that
         * report at submission time.
         */
        $reportImportId =
            !empty($data['report_import_id'])
                ? (int) $data['report_import_id']
                : null;

        if ($reportImportId) {
            $validIdentifiers =
                DB::table('report_rows')
                    ->where(
                        'report_import_id',
                        $reportImportId
                    )
                    ->where(function ($query) {
                        $query
                            ->whereNull(
                                'mapping_status'
                            )
                            ->orWhere(
                                'mapping_status',
                                'unmapped'
                            );
                    })
                    ->whereNotNull('isrc')
                    ->get(['isrc'])
                    ->map(function ($row) {
                        return strtoupper(
                            preg_replace(
                                '/[^A-Z0-9]/',
                                '',
                                trim(
                                    (string) $row->isrc
                                )
                            )
                        );
                    })
                    ->filter()
                    ->unique();

            /*
             * MIXXTUNE_BULK_MAP_STALE_SELECTION_IDEMPOTENCY_V1
             *
             * The browser selection can become stale when one of the
             * selected ISRCs is mapped separately before this bulk
             * request is submitted. Do not fail the entire batch.
             *
             * Intersect with identifiers that are STILL unresolved in
             * this exact report. This preserves report scoping and
             * prevents already-mapped rows from being overwritten.
             */
            $identifiers =
                $identifiers
                    ->intersect($validIdentifiers)
                    ->values();

            if ($identifiers->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'isrcs' =>
                        'None of the selected ISRCs remain unresolved in this report. Reload the page and try again.',
                ]);
            }
        }

        $userId =
            (int) auth()->id();

        $notes =
            isset($data['notes'])
                ? trim((string) $data['notes'])
                : null;

        $updatedRows = 0;

        DB::transaction(
            function () use (
                $identifiers,
                $financialOwnerId,
                $catalogueLabelId,
                $artistId,
                $notes,
                $userId,
                $reportImportId,
                &$updatedRows
            ) {
                foreach ($identifiers as $identifier) {
                    DB::table(
                        'revenue_mapping_rules'
                    )->updateOrInsert(
                        [
                            'identifier_type' =>
                                'isrc',
                            'identifier_value' =>
                                $identifier,
                        ],
                        [
                            'owner_type' =>
                                'label',
                            'owner_id' =>
                                (int) $financialOwnerId,
                            'catalogue_label_id' =>
                                $catalogueLabelId,
                            'artist_id' =>
                                $artistId,
                            'notes' =>
                                $notes,
                            'created_by' =>
                                $userId,
                            'updated_at' =>
                                now(),
                            'created_at' =>
                                now(),
                        ]
                    );

                    $updatedRows +=
                        $this->applyIsrcRule(
                            $identifier,
                            'label',
                            (int) $financialOwnerId,
                            $catalogueLabelId,
                            $artistId,
                            $reportImportId
                        );
                }
            }
        );

        return back()->with(
            'success',
            $identifiers->count().
            ' selected ISRC mapping(s) saved. '.
            number_format($updatedRows).
            ' review-pending report row(s) updated. Wallets were not credited.'
        );
    }


    public function createAndMapLabel(Request $request)
    {
        /*
         * MIXXTUNE_CREATE_MAP_REQUEST_TRACE_V1
         * Temporary diagnostic trace.
         */
        \Log::info(
            'MIXXTUNE_CREATE_MAP_REQUEST_HIT',
            [
                'user_id' => auth()->id(),
                'isrc' => $request->input('isrc'),
                'label_name' => $request->input('label_name'),
                'report_import_id' =>
                    $request->input('report_import_id'),
                'url' => $request->fullUrl(),
            ]
        );
        $data = $request->validate([
            'isrc' => [
                'required',
                'string',
                'max:191',
            ],

            'label_name' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * MIXXTUNE_CREATE_MAP_RATE_REQUIRED_BACKEND_V1
             *
             * A newly-created financial label must
             * have an explicit Revenue Rate.
             */
            'royalty_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
            'report_import_id' => [
                'nullable',
                'integer',
                'exists:report_imports,id',
            ],
        ]);

        $reportImportId =
            !empty($data['report_import_id'])
                ? (int) $data['report_import_id']
                : null;

        $clickedIdentifier =
            $this->normalizeCode(
                $data['isrc']
            );

        abort_if(
            $clickedIdentifier === '',
            422,
            'Invalid ISRC.'
        );

        $labelName =
            trim($data['label_name']);

        abort_if(
            $labelName === '',
            422,
            'Label name is required.'
        );

        /*
         * Safety:
         * The clicked row must still exist as unmapped.
         */
        $sourceExistsQuery =
            DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'unmapped'
                )
                ->whereRaw(
                    'LOWER(TRIM(label_name)) = ?',
                    [mb_strtolower($labelName)]
                )
                ->whereRaw(
                    "
                    REGEXP_REPLACE(
                        UPPER(TRIM(isrc)),
                        '[^A-Z0-9]',
                        ''
                    ) = ?
                    ",
                    [$clickedIdentifier]
                );

        if ($reportImportId !== null) {
            $sourceExistsQuery->where(
                'report_import_id',
                $reportImportId
            );
        }

        $sourceExists =
            $sourceExistsQuery->exists();

        abort_unless(
            $sourceExists,
            422,
            'The selected label/ISRC is no longer unmapped.'
        );

        /*
         * Do not create duplicate labels.
         *
         * Existing labels must use the normal hierarchy
         * mapping flow instead.
         */
        $existingLabel =
            app(LabelIdentityService::class)
                ->representative(
                    $labelName
                );

        if ($existingLabel) {
            return back()->withErrors([
                'label_name' =>
                    'Label already exists as "'.
                    $existingLabel->name.
                    '" (ID '.
                    $existingLabel->id.
                    '). Select the existing label instead.',
            ]);
        }

        /*
         * Collect ALL currently-unmapped ISRCs belonging
         * to this exact report label.
         */
        $sourceRowsQuery =
            DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'unmapped'
                )
                ->whereRaw(
                    'LOWER(TRIM(label_name)) = ?',
                    [mb_strtolower($labelName)]
                );

        if ($reportImportId !== null) {
            $sourceRowsQuery->where(
                'report_import_id',
                $reportImportId
            );
        }

        $sourceRows =
            $sourceRowsQuery->get([
                'id',
                'isrc',
            ]);

        abort_if(
            $sourceRows->isEmpty(),
            422,
            'No unmapped report rows remain for this label.'
        );

        $identifiers =
            $sourceRows
                ->pluck('isrc')
                ->map(
                    fn ($value) =>
                        $this->normalizeCode($value)
                )
                ->filter(
                    fn ($value) =>
                        $value !== ''
                )
                ->unique()
                ->values();

        abort_if(
            $identifiers->isEmpty(),
            422,
            'No valid ISRCs were found for this label.'
        );

        /*
         * Critical collision guard.
         *
         * Since this operation creates a NEW label,
         * none of its ISRCs may already have permanent
         * ownership rules.
         */
        $conflictingRules =
            DB::table('revenue_mapping_rules')
                ->where(
                    'identifier_type',
                    'isrc'
                )
                ->whereIn(
                    'identifier_value',
                    $identifiers->all()
                )
                ->get([
                    'identifier_value',
                    'owner_type',
                    'owner_id',
                    'catalogue_label_id',
                    'artist_id',
                ]);

        if ($conflictingRules->isNotEmpty()) {
            $sample =
                $conflictingRules
                    ->pluck('identifier_value')
                    ->take(5)
                    ->implode(', ');

            return back()->withErrors([
                'label_name' =>
                    $conflictingRules->count().
                    ' ISRC(s) already have permanent mapping rules. '.
                    'Nothing was changed. Review conflicts first. '.
                    ($sample !== ''
                        ? 'Example: '.$sample
                        : ''),
            ]);
        }

        $royaltyPercentage =
            (float)
                $data['royalty_percentage'];

        $userId =
            $request->user()?->id;

        $result =
            DB::transaction(
                function () use (
                    $labelName,
                    $royaltyPercentage,
                    $userId,
                    $reportImportId,
                    $identifiers
                ) {
                    /*
                     * Logical duplicate recheck inside the
                     * transaction using the same global
                     * normalization rule as every live
                     * label-creation path.
                     *
                     * Existing physical duplicate records
                     * remain untouched.
                     */
                    $duplicate =
                        app(LabelIdentityService::class)
                            ->representative(
                                $labelName
                            );

                    if ($duplicate) {
                        abort(
                            422,
                            'Label already exists as "'.
                            $duplicate->name.
                            '". Select the existing label.'
                        );
                    }

                    /*
                     * Rule collision recheck inside
                     * transaction.
                     */
                    $ruleConflict =
                        DB::table(
                            'revenue_mapping_rules'
                        )
                            ->where(
                                'identifier_type',
                                'isrc'
                            )
                            ->whereIn(
                                'identifier_value',
                                $identifiers->all()
                            )
                            ->lockForUpdate()
                            ->exists();

                    abort_if(
                        $ruleConflict,
                        422,
                        'One or more ISRC mapping rules changed. Nothing was created.'
                    );

                    $baseSlug =
                        Str::slug($labelName);

                    if ($baseSlug === '') {
                        $baseSlug = 'label';
                    }

                    $slug = $baseSlug;
                    $counter = 1;

                    while (
                        DB::table('labels')
                            ->where(
                                'slug',
                                $slug
                            )
                            ->exists()
                    ) {
                        $slug =
                            $baseSlug.
                            '-'.
                            $counter;

                        $counter++;
                    }

                    $now = now();

                    $labelId =
                        DB::table('labels')
                            ->insertGetId([
                                'public_id' =>
                                    (string)
                                        Str::ulid(),

                                'label_type' =>
                                    'label',

                                'name' =>
                                    $labelName,

                                'slug' =>
                                    $slug,

                                /*
                                 * MIXXTUNE_CREATE_MAP_AUTHORITATIVE_RATE_V1
                                 *
                                 * Keep the legacy percentage populated for
                                 * compatibility, but the V2 financial engine
                                 * uses revenue_share_percentage as the
                                 * authoritative commercial rate.
                                 */
                                'royalty_share_percentage' =>
                                    $royaltyPercentage,

                                'revenue_share_percentage' =>
                                    $royaltyPercentage,

                                'parent_commission_percentage' =>
                                    0,

                                'status' =>
                                    'active',

                                'created_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);

                    /*
                     * Build permanent rules for every
                     * unique ISRC belonging to the
                     * report label.
                     */
                    $ruleRows = [];

                    foreach (
                        $identifiers as $identifier
                    ) {
                        $ruleRows[] = [
                            'identifier_type' =>
                                'isrc',

                            'identifier_value' =>
                                $identifier,

                            'owner_type' =>
                                'label',

                            'owner_id' =>
                                $labelId,

                            'catalogue_label_id' =>
                                $labelId,

                            'artist_id' =>
                                null,

                            'track_id' =>
                                null,

                            'release_id' =>
                                null,

                            'source' =>
                                'bulk_create_and_map_label',

                            'notes' =>
                                'Bulk created from Unmapped Revenue label: '.
                                $labelName,

                            'created_by' =>
                                $userId,

                            'updated_by' =>
                                $userId,

                            'created_at' =>
                                $now,

                            'updated_at' =>
                                $now,
                        ];
                    }

                    foreach (
                        array_chunk(
                            $ruleRows,
                            100
                        ) as $chunk
                    ) {
                        DB::table(
                            'revenue_mapping_rules'
                        )->insert($chunk);
                    }

                    $rulesCreated =
                        DB::table(
                            'revenue_mapping_rules'
                        )
                            ->where(
                                'identifier_type',
                                'isrc'
                            )
                            ->whereIn(
                                'identifier_value',
                                $identifiers->all()
                            )
                            ->where(
                                'owner_type',
                                'label'
                            )
                            ->where(
                                'owner_id',
                                $labelId
                            )
                            ->count();

                    if (
                        $rulesCreated !==
                        $identifiers->count()
                    ) {
                        throw new \RuntimeException(
                            'Rule creation verification failed.'
                        );
                    }

                    /*
                     * Map ONLY rows belonging to this
                     * exact report label.
                     *
                     * This intentionally does NOT use
                     * applyIsrcRule(), because that
                     * helper maps every unmapped row
                     * sharing an ISRC regardless of
                     * report label.
                     */
                    /*
                     * FF384-C2 CREATE AND MAP SAFETY
                     * ===============================
                     *
                     * Only report rows whose parent
                     * financial import is still under
                     * review may be changed.
                     *
                     * approved_scheduled / published
                     * imports remain immutable.
                     */
                    $eligibleRowIds =
                        DB::table('report_rows as rr')
                            ->join(
                                'report_imports as ri',
                                'ri.id',
                                '=',
                                'rr.report_import_id'
                            )
                            ->where(
                                'rr.mapping_status',
                                'unmapped'
                            )
                            ->where(
                                'ri.financial_status',
                                'review_pending'
                            )
                            ->when(
                                $reportImportId !== null,
                                fn ($query) =>
                                    $query->where(
                                        'rr.report_import_id',
                                        $reportImportId
                                    )
                            )
                            ->whereRaw(
                                'LOWER(TRIM(rr.label_name)) = ?',
                                [
                                    mb_strtolower(
                                        $labelName
                                    ),
                                ]
                            )
                            ->whereRaw(
                                "
                                REGEXP_REPLACE(
                                    UPPER(TRIM(rr.isrc)),
                                    '[^A-Z0-9]',
                                    ''
                                ) IN (".
                                implode(
                                    ',',
                                    array_fill(
                                        0,
                                        $identifiers
                                            ->count(),
                                        '?'
                                    )
                                ).
                                ")
                                ",
                                $identifiers->all()
                            )
                            ->lockForUpdate()
                            ->pluck('rr.id');

                    $updated =
                        DB::table('report_rows')
                            ->whereIn(
                                'id',
                                $eligibleRowIds
                                    ->all()
                            )
                            ->where(
                                'mapping_status',
                                'unmapped'
                            )
                            ->update([
                                'label_id' =>
                                    $labelId,

                                'artist_id' =>
                                    null,

                                'revenue_owner_type' =>
                                    'label',

                                'revenue_owner_id' =>
                                    $labelId,

                                'mapping_status' =>
                                    'mapped',

                                'mapped_at' =>
                                    $now,

                                'updated_at' =>
                                    $now,
                            ]);

                    $eligibleRowsCount =
                        $eligibleRowIds->count();

                    if (
                        $updated !==
                        $eligibleRowsCount
                    ) {
                        throw new \RuntimeException(
                            'Mapped-row verification failed.'
                        );
                    }

                    return [
                        'label_id' =>
                            $labelId,

                        'rules_created' =>
                            $rulesCreated,

                        'updated' =>
                            $updated,

                        /*
                         * MIXXTUNE_CREATE_MAP_TOUCHED_ROWS_V1
                         *
                         * Carry exact touched row IDs outside
                         * the transaction for scoped financial
                         * recalculation/currency resolution.
                         */
                        'eligible_row_ids' =>
                            $eligibleRowIds
                                ->map(
                                    fn ($id) =>
                                        (int) $id
                                )
                                ->values()
                                ->all(),
                    ];
                }
            );

        /*
         * FF384-C2:
         * Refresh financial values through the
         * authoritative recalculation service.
         *
         * FF383-B already restricts this service
         * to review_pending imports only.
         */
        /*
         * MIXXTUNE_CREATE_MAP_IMPORT_SCOPED_RECALC_V1
         *
         * Only imports actually touched by this Create & Map
         * operation are recalculated. Other review_pending
         * imports belonging to the same label are not swept
         * unnecessarily.
         */
        $eligibleRowIds =
            collect(
                $result['eligible_row_ids'] ?? []
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->filter(
                    fn ($id) =>
                        $id > 0
                )
                ->unique()
                ->values();

        $financialImportIds =
            DB::table('report_rows')
                ->whereIn(
                    'id',
                    $eligibleRowIds->all()
                )
                ->whereNotNull(
                    'report_import_id'
                )
                ->distinct()
                ->pluck(
                    'report_import_id'
                );

        $earningsService =
            app(
                ReportEarningsRecalculationService::class
            );

        foreach (
            $financialImportIds as $financialImportId
        ) {
            $earningsService
                ->recalculateForLabelInImport(
                    \App\Models\Core\Label::query()
                        ->findOrFail(
                            (int) $result['label_id']
                        ),
                    (int) $financialImportId
                );
        }

        /*
         * MIXXTUNE_MAPPING_CURRENCY_SYNC
         *
         * Mapping has completed and the authoritative
         * financial recalculation has run.
         *
         * Resolve only missing currency values for the
         * review_pending imports touched by this operation.
         *
         * CSV/source currency is never overwritten.
         */
        $currencyImportIds =
            DB::table('report_rows')
                ->whereIn(
                    'id',
                    $eligibleRowIds->all()
                )
                ->whereNotNull(
                    'report_import_id'
                )
                ->distinct()
                ->pluck(
                    'report_import_id'
                );

        foreach (
            $currencyImportIds as $currencyImportId
        ) {
            app(
                \App\Services\V2\ReportCurrencyResolutionService::class
            )->resolveForImport(
                (int) $currencyImportId
            );
        }

        return back()->with(
            'success',
            'Label "'.
            $labelName.
            '" created (ID '.
            $result['label_id'].
            ') with '.
            $result['rules_created'].
            ' permanent ISRC rule(s), and '.
            $result['updated'].
            ' report row(s) mapped.'
        );
    }

    /*
     * MIXXTUNE_UNMAPPED_AUTO_RESOLVE_V2_QUEUE
     *
     * Browser request only validates + dispatches.
     * Heavy mapping runs on the existing queue workers.
     *
     * No wallet posting.
     * No publication.
     */
    public function autoResolve(Request $request)
    {
        $data = $request->validate([
            'report_import_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $reportImportId =
            (int) $data['report_import_id'];

        $reportImport =
            DB::table('report_imports')
                ->where('id', $reportImportId)
                ->first([
                    'id',
                    'financial_status',
                    'status',
                ]);

        abort_unless(
            $reportImport,
            404,
            'Report import not found.'
        );

        abort_unless(
            $reportImport->financial_status ===
                'review_pending',
            422,
            'Only a report under financial review can be auto-resolved.'
        );

        abort_unless(
            in_array(
                $reportImport->status,
                [
                    'completed',
                    'completed_with_errors',
                ],
                true
            ),
            422,
            'Report processing must be complete before auto-resolution.'
        );

        \Illuminate\Support\Facades\Log::info(
            'MIXXTUNE_AUTO_RESOLVE_REQUEST_HIT',
            [
                'report_import_id' => $reportImportId,
                'user_id' => $request->user()?->id,
            ]
        );

        \App\Jobs\AutoResolveUnmappedRevenueJob::dispatch(
            $reportImportId,
            $request->user()?->id
        );

        \Illuminate\Support\Facades\Log::info(
            'MIXXTUNE_AUTO_RESOLVE_JOB_DISPATCHED',
            [
                'report_import_id' => $reportImportId,
                'user_id' => $request->user()?->id,
            ]
        );

        /*
         * MIXXTUNE_AUTO_RESOLVE_PLAIN_POST_REDIRECT_V1
         *
         * Explicit 303 avoids keeping this operation inside an
         * Inertia POST response lifecycle.
         */
        return redirect()
            ->to(
                '/super-admin/unmapped-revenue?report_import_id='.
                $reportImportId
            )
            ->setStatusCode(303)
            ->with(
                'success',
                'Auto Resolve started in background. Refresh shortly to see the updated unmapped count.'
            );
    }


    public function applyAll(Request $request)
    {
        $rules =
            DB::table(
                'revenue_mapping_rules'
            )
                ->where(
                    'identifier_type',
                    'isrc'
                )
                ->orderBy('id')
                ->get();

        $updated = 0;

        DB::transaction(
            function () use (
                $rules,
                &$updated
            ) {
                foreach ($rules as $rule) {
                    $updated +=
                        $this->applyIsrcRule(
                            $rule
                                ->identifier_value,

                            $rule->owner_type,

                            (int)
                                $rule->owner_id,

                            !empty(
                                $rule
                                    ->catalogue_label_id
                            )
                                ? (int)
                                    $rule
                                        ->catalogue_label_id
                                : null,

                            !empty(
                                $rule->artist_id
                            )
                                ? (int)
                                    $rule
                                        ->artist_id
                                : null
                        );
                }
            }
        );

        return back()->with(
            'success',
            $updated.
            ' report rows mapped from saved ISRC rules.'
        );
    }

    private function applyIsrcRule(
        string $identifier,
        string $ownerType,
        int $ownerId,
        ?int $catalogueLabelId = null,
        ?int $artistId = null,
        ?int $reportImportId = null
    ): int {
        /*
         * FF384-B FINANCIAL SAFETY
         * ========================
         *
         * Revenue mapping is mutable only while the
         * parent import is still under financial review.
         *
         * approved_scheduled / published imports are
         * immutable financial history and must never be
         * changed by a later ISRC mapping rule.
         *
         * Legacy rows without report_import_id are also
         * intentionally excluded from this workflow.
         */

        $assignedRate = null;

        /*
         * Commercial rate follows the exact catalogue
         * placement first, matching ReportImportService.
         */
        if ($catalogueLabelId !== null) {
            $assignedRate =
                DB::table('labels')
                    ->where(
                        'id',
                        $catalogueLabelId
                    )
                    ->value(
                        'revenue_share_percentage'
                    );
        } elseif ($artistId !== null) {
            $assignedRate =
                DB::table('artists')
                    ->where(
                        'id',
                        $artistId
                    )
                    ->value(
                        'revenue_share_percentage'
                    );
        }

        /*
         * Persistent/legacy rules may expose only the
         * financial owner. Use that owner strictly as
         * the fallback rate source.
         */
        if ($assignedRate === null) {
            if ($ownerType === 'label') {
                $assignedRate =
                    DB::table('labels')
                        ->where(
                            'id',
                            $ownerId
                        )
                        ->value(
                            'revenue_share_percentage'
                        );
            } elseif ($ownerType === 'artist') {
                $assignedRate =
                    DB::table('artists')
                        ->where(
                            'id',
                            $ownerId
                        )
                        ->value(
                            'revenue_share_percentage'
                        );
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

        $rowsQuery =
            DB::table('report_rows as rr')
                ->join(
                    'report_imports as ri',
                    'ri.id',
                    '=',
                    'rr.report_import_id'
                )
                ->where(
                    'rr.mapping_status',
                    'unmapped'
                )
                ->where(
                    'ri.financial_status',
                    'review_pending'
                );

        if ($reportImportId !== null) {
            $rowsQuery->where(
                'rr.report_import_id',
                $reportImportId
            );
        }

        $rows =
            $rowsQuery
                ->whereRaw(
                    "
                    REGEXP_REPLACE(
                        UPPER(TRIM(rr.isrc)),
                        '[^A-Z0-9]',
                        ''
                    ) = ?
                    ",
                    [$identifier]
                )
                ->select([
                    'rr.id',
                    'rr.report_import_id',
                    'rr.collected_revenue',
                ])
                ->lockForUpdate()
                ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $now = now();
        $updated = 0;

        foreach ($rows as $row) {
            $collectedRevenue =
                $row->collected_revenue !== null
                    ? (float)
                        $row->collected_revenue
                    : null;

            /*
             * FINAL FINANCIAL RULE
             * ====================
             *
             * Collected Revenue is immutable source
             * financial value.
             *
             * Positive / zero:
             *   Collected Revenue x account rate
             *
             * Negative:
             *   100% charge to mapped owner.
             *
             * Missing commercial rate:
             *   keep earnings at zero so Final Review
             *   continues to block approval.
             */
            if (
                $collectedRevenue !== null
                && $collectedRevenue < 0
            ) {
                $effectiveRate = 1.0;

                $earnings =
                    round(
                        $collectedRevenue,
                        8
                    );
            } elseif (
                $collectedRevenue !== null
                && $assignedRate !== null
            ) {
                $effectiveRate =
                    round(
                        $assignedRate / 100,
                        8
                    );

                $earnings =
                    round(
                        $collectedRevenue
                        * $effectiveRate,
                        8
                    );
            } else {
                $effectiveRate = 0.0;
                $earnings = 0.0;
            }

            $values = [
                'revenue_owner_type' =>
                    $ownerType,

                'revenue_owner_id' =>
                    $ownerId,

                'mapping_status' =>
                    'mapped',

                'mapped_at' =>
                    $now,

                'label_rate' =>
                    $effectiveRate,

                'earnings' =>
                    $earnings,

                'updated_at' =>
                    $now,
            ];

            if ($catalogueLabelId !== null) {
                $values['label_id'] =
                    $catalogueLabelId;

                $values['artist_id'] =
                    $artistId;
            }

            $updated +=
                DB::table('report_rows')
                    ->where(
                        'id',
                        $row->id
                    )
                    ->where(
                        'mapping_status',
                        'unmapped'
                    )
                    ->update($values);
        }

        /*
         * MIXXTUNE_MAPPING_CURRENCY_SYNC
         *
         * The helper can map the same ISRC across
         * multiple review_pending imports.
         *
         * Resolve missing currency once per affected
         * import after mapping is complete.
         */
        $affectedImportIds =
            $rows
                ->pluck('report_import_id')
                ->filter()
                ->unique()
                ->values();

        foreach (
            $affectedImportIds as $affectedImportId
        ) {
            app(
                \App\Services\V2\ReportCurrencyResolutionService::class
            )->resolveForImport(
                (int) $affectedImportId
            );
        }

        return $updated;
    }

    private function validateHierarchyTarget(
        int $catalogueLabelId,
        ?int $artistId
    ): void {
        $labelExists =
            DB::table('labels')
                ->where(
                    'id',
                    $catalogueLabelId
                )
                ->whereNull('deleted_at')
                ->exists();

        abort_unless(
            $labelExists,
            422,
            'Selected catalogue level does not exist.'
        );

        if ($artistId === null) {
            return;
        }

        $artist =
            DB::table('artists')
                ->where(
                    'id',
                    $artistId
                )
                ->whereNull('deleted_at')
                ->first([
                    'id',
                    'label_id',
                ]);

        abort_unless(
            $artist,
            422,
            'Selected artist does not exist.'
        );

        $allowedLabelIds =
            app(
                \App\Services\V2\LabelHierarchyService::class
            )->descendantIds(
                $catalogueLabelId,
                true
            );

        $allowedLabelIds =
            collect(
                $allowedLabelIds
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                );

        abort_unless(
            $artist->label_id
            && $allowedLabelIds->contains(
                (int) $artist->label_id
            ),
            422,
            'Selected artist is outside the selected catalogue hierarchy.'
        );

        /*
         * MIXXTUNE_MAPPING_RATE_GUARD_V1
         *
         * Mapping must never silently create zero/unknown
         * commercial earnings.
         *
         * Exact catalogue placement is the primary rate source.
         * If an Artist is explicitly selected, its own rate may
         * also be used by the canonical mapping/calculation path.
         *
         * Rates are maintained centrally on Labels / Artists.
         */
        $catalogueRate = DB::table('labels')
            ->where('id', $catalogueLabelId)
            ->value('revenue_share_percentage');

        $artistRate = null;

        if ($artistId !== null) {
            $artistRate = DB::table('artists')
                ->where('id', $artistId)
                ->value('revenue_share_percentage');
        }

        if (
            $catalogueRate === null &&
            $artistRate === null
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'catalogue_label_id' =>
                    'Revenue Rate is not set for the selected Catalogue Label. Set the Label Rate first, then map the ISRC.',
            ]);
        }

    }

    private function normalizeCode(?string $value): string
    {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim((string) $value)
            )
        );
    }
}
