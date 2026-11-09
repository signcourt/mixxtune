<?php

namespace App\Http\Controllers\V2\Label;

use App\Http\Controllers\Controller;
use App\Models\Core\Label;
use App\Models\Finance\RevenueImport;
use App\Models\Finance\RevenueMatch;
use App\Models\Finance\RevenueRow;
use App\Services\V2\ReportVisibilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * W137 MIXXTUNE_REPORT_COLUMN_PREFS
 * The label / artist view of their own revenue report: only the rows that belong to them, only the
 * columns the Super Admin shared, plus the Label Rate and Earning columns. Also a CSV download of
 * exactly that filtered view.
 */
class MyRevenueReportController extends Controller
{
    public function __construct(private ReportVisibilityService $vis)
    {
    }

    private function myLabel(Request $request): Label
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'label', 403, 'Label access required.');

        return Label::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /** Row ids in an import that belong to this label. */
    private function myRowIds(int $importId, int $labelId)
    {
        return RevenueRow::query()
            ->where('revenue_import_id', $importId)
            ->whereIn('id', RevenueMatch::query()->where('label_id', $labelId)->select('revenue_row_id'))
            ->pluck('id');
    }

    public function index(Request $request)
    {
        $label = $this->myLabel($request);
        $rate = $this->vis->labelRate($label);

        /* imports that have at least one row for this label and are processed */
        $importIds = RevenueRow::query()
            ->whereIn('id', RevenueMatch::query()->where('label_id', $label->id)->select('revenue_row_id'))
            ->distinct()
            ->pluck('revenue_import_id');

        $reports = RevenueImport::query()
            ->whereIn('id', $importIds)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->get()
            ->map(function (RevenueImport $import) use ($label, $rate) {
                $rowIds = $this->myRowIds($import->id, $label->id);
                $net = RevenueRow::query()->whereIn('id', $rowIds)->sum('net_amount');

                return [
                    'id' => $import->id,
                    'title' => $import->original_filename ?? ('Report #'.$import->id),
                    'month' => $import->statement_month,
                    'rows' => $rowIds->count(),
                    'earning' => round((float) $net * $rate, 2),
                ];
            })
            ->values();

        return Inertia::render('V2/Label/RevenueReports/Index', [
            'reports' => $reports,
            'labelRate' => $rate,
        ]);
    }

    public function show(Request $request, RevenueImport $revenueImport)
    {
        $label = $this->myLabel($request);
        $rate = $this->vis->labelRate($label);

        $rowIds = $this->myRowIds($revenueImport->id, $label->id);
        abort_if($rowIds->isEmpty(), 404, 'No report for you here.');

        $pref = $this->vis->prefForLabel($label->id);
        $columns = $this->vis->visibleKeys($this->vis->allColumns($revenueImport), $label->id);

        $rows = RevenueRow::query()
            ->whereIn('id', $rowIds)
            ->orderBy('id')
            ->get(['id', 'net_amount', 'raw_data'])
            ->map(function (RevenueRow $row) use ($columns, $rate, $pref) {
                $cells = [];
                foreach ($columns as $k) {
                    $cells[$k] = (string) (($row->raw_data[$k] ?? ''));
                }
                if ($pref->show_label_rate) {
                    $cells['Label Rate'] = number_format($rate * 100, 2).'%';
                }
                if ($pref->show_earning) {
                    $cells['Earning'] = number_format($this->vis->earning($row, $rate), 2);
                }

                return $cells;
            })
            ->values();

        $header = $columns;
        if ($pref->show_label_rate) {
            $header[] = 'Label Rate';
        }
        if ($pref->show_earning) {
            $header[] = 'Earning';
        }

        return Inertia::render('V2/Label/RevenueReports/Show', [
            'report' => [
                'id' => $revenueImport->id,
                'title' => $revenueImport->original_filename ?? ('Report #'.$revenueImport->id),
                'month' => $revenueImport->statement_month,
            ],
            'header' => $header,
            'rows' => $rows,
            'labelRate' => $rate,
            'totalEarning' => round((float) RevenueRow::query()->whereIn('id', $rowIds)->sum('net_amount') * $rate, 2),
        ]);
    }

    public function export(Request $request, RevenueImport $revenueImport): StreamedResponse
    {
        $label = $this->myLabel($request);
        $rate = $this->vis->labelRate($label);
        $rowIds = $this->myRowIds($revenueImport->id, $label->id);
        abort_if($rowIds->isEmpty(), 404, 'No report for you here.');

        $pref = $this->vis->prefForLabel($label->id);
        $columns = $this->vis->visibleKeys($this->vis->allColumns($revenueImport), $label->id);

        $header = $columns;
        if ($pref->show_label_rate) {
            $header[] = 'Label Rate';
        }
        if ($pref->show_earning) {
            $header[] = 'Earning';
        }

        $filename = 'revenue-report-'.$revenueImport->id.'.csv';

        return response()->streamDownload(function () use ($revenueImport, $rowIds, $columns, $header, $rate, $pref) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            RevenueRow::query()
                ->where('revenue_import_id', $revenueImport->id)
                ->whereIn('id', $rowIds)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out, $columns, $rate, $pref) {
                    foreach ($rows as $row) {
                        $line = [];
                        foreach ($columns as $k) {
                            $line[] = (string) (($row->raw_data[$k] ?? ''));
                        }
                        if ($pref->show_label_rate) {
                            $line[] = number_format($rate * 100, 2).'%';
                        }
                        if ($pref->show_earning) {
                            $line[] = number_format($this->vis->earning($row, $rate), 2);
                        }
                        fputcsv($out, $line);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
