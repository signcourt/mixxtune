<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use App\Models\Finance\ReportColumnPref;
use App\Models\Finance\RevenueImport;
use App\Models\Finance\RevenueRow;

/*
 * W137 MIXXTUNE_REPORT_COLUMN_PREFS
 * Decides which raw report columns a label / artist may see, and works out the Label Rate and
 * Earning columns that are added to their report. The Super Admin always keeps the full report.
 */
class ReportVisibilityService
{
    /** The raw column keys that appear anywhere in this import (header order kept as first seen). */
    public function allColumns(RevenueImport $import): array
    {
        $keys = [];
        RevenueRow::query()
            ->where('revenue_import_id', $import->id)
            ->orderBy('id')
            ->limit(500)
            ->get(['raw_data'])
            ->each(function (RevenueRow $row) use (&$keys) {
                foreach (array_keys((array) ($row->raw_data ?? [])) as $k) {
                    $keys[$k] = true;
                }
            });

        return array_keys($keys);
    }

    /** The default template (label_id = NULL), created on first use. */
    public function defaultPref(): ReportColumnPref
    {
        return ReportColumnPref::query()->firstOrNew(['label_id' => null], [
            'columns' => null,
            'show_label_rate' => true,
            'show_earning' => true,
        ]);
    }

    /** The pref that applies to a label: its own override if set, otherwise the default. */
    public function prefForLabel(?int $labelId): ReportColumnPref
    {
        if ($labelId) {
            $own = ReportColumnPref::query()->where('label_id', $labelId)->first();
            if ($own) {
                return $own;
            }
        }

        return $this->defaultPref();
    }

    /**
     * The visible column keys for a label, in the import's own column order.
     * An empty / null `columns` on the pref means "show everything".
     */
    public function visibleKeys(array $allKeys, ?int $labelId): array
    {
        $pref = $this->prefForLabel($labelId);
        $allow = $pref->columns;

        if (empty($allow)) {
            return $allKeys;
        }

        $allow = array_flip($allow);

        return array_values(array_filter($allKeys, fn ($k) => isset($allow[$k])));
    }

    /** Label rate as a fraction (80% -> 0.80). */
    public function labelRate(?Label $label): float
    {
        if (!$label) {
            return 0.0;
        }

        $pct = (float) ($label->royalty_share_percentage ?? 0);

        return round($pct / 100, 4);
    }

    /** Earning for one row at the label rate (net amount x rate). */
    public function earning(RevenueRow $row, float $rate): float
    {
        return round((float) $row->net_amount * $rate, 2);
    }
}
