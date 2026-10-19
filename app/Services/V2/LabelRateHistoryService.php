<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
 * MIXXTUNE_LABEL_RATE_HISTORY_V1
 *
 * Every change of labels.revenue_share_percentage is written to audit_logs
 * (action label.rate_changed) with old %, new %, who, from which screen and
 * which review_pending reports it affects. Approved reports are never
 * recalculated (their rows keep the rate used), so history also shows the
 * rate actually used in each report from report_rows.label_rate.
 */
class LabelRateHistoryService
{
    public const ACTION = 'label.rate_changed';

    private static int $muted = 0;

    /* Run $callback without automatic history entries (caller logs the net result). */
    public static function muted(callable $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    public static function isMuted(): bool
    {
        return self::$muted > 0;
    }

    private static function norm($value): ?float
    {
        return ($value === null || $value === '') ? null : round((float) $value, 4);
    }

    public static function format($value): string
    {
        return $value === null ? 'Not set' : rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.').'%';
    }

    public function source(): string
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return 'console';
        }

        $name = request()?->route()?->getName();

        return $name ?: (request()?->path() ? '/'.ltrim(request()->path(), '/') : 'system');
    }

    public function pendingImportIds(int $labelId): array
    {
        try {
            return DB::table('report_rows as rr')
                ->join('report_imports as ri', 'ri.id', '=', 'rr.report_import_id')
                ->where('ri.financial_status', 'review_pending')
                ->where('rr.revenue_owner_type', 'label')
                ->where('rr.revenue_owner_id', $labelId)
                ->distinct()
                ->pluck('rr.report_import_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    public function record(Label $label, $old, $new, ?string $source = null, ?string $note = null): void
    {
        $old = self::norm($old);
        $new = self::norm($new);

        if ($old === $new) {
            return;
        }

        $source ??= $this->source();
        $pending = $this->pendingImportIds((int) $label->id);
        $description = "Label {$label->name} (#{$label->id}) rate "
            .self::format($old).' -> '.self::format($new)." [{$source}]";
        $newValues = [
            'revenue_share_percentage' => $new,
            'source' => $source,
            'pending_import_ids' => $pending,
            'note' => $note,
        ];

        try {
            if (Schema::hasTable('audit_logs')) {
                app(AuditLogService::class)->record(
                    self::ACTION,
                    'labels',
                    $description,
                    ['revenue_share_percentage' => $old],
                    $newValues,
                    $label,
                    app()->bound('request') ? request() : null
                );

                return;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        Log::info('MIXXTUNE_LABEL_RATE_CHANGED', ['label_id' => $label->id, 'old' => $old] + $newValues);
    }

    public function history(?int $labelId, int $limit = 200): array
    {
        if (!Schema::hasTable('audit_logs')) {
            return [];
        }

        return DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('labels as l', 'l.id', '=', 'a.auditable_id')
            ->where('a.action', self::ACTION)
            ->where('a.auditable_type', Label::class)
            ->when($labelId, fn ($q) => $q->where('a.auditable_id', $labelId))
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.created_at', 'a.auditable_id', 'a.old_values', 'a.new_values', 'u.name as user_name', 'u.email as user_email', 'l.name as label_name'])
            ->map(function ($row) {
                $old = json_decode((string) $row->old_values, true) ?: [];
                $new = json_decode((string) $row->new_values, true) ?: [];

                return [
                    'id' => (int) $row->id,
                    'at' => (string) $row->created_at,
                    'label_id' => (int) $row->auditable_id,
                    'label_name' => $row->label_name,
                    'old' => $old['revenue_share_percentage'] ?? null,
                    'new' => $new['revenue_share_percentage'] ?? null,
                    'source' => $new['source'] ?? null,
                    'note' => $new['note'] ?? null,
                    'pending_import_ids' => $new['pending_import_ids'] ?? [],
                    'by' => $row->user_name ? $row->user_name.' <'.$row->user_email.'>' : 'System',
                ];
            })
            ->all();
    }

    /* Rate actually used in every report for this label (from the stored rows). */
    public function ratesUsed(int $labelId): array
    {
        return DB::table('report_rows as rr')
            ->join('report_imports as ri', 'ri.id', '=', 'rr.report_import_id')
            ->where('rr.revenue_owner_type', 'label')
            ->where('rr.revenue_owner_id', $labelId)
            ->groupBy('rr.report_import_id', 'rr.label_rate', 'ri.reporting_month', 'ri.financial_status', 'ri.original_filename')
            ->orderByDesc('rr.report_import_id')
            ->limit(200)
            ->get([
                'rr.report_import_id',
                'rr.label_rate',
                'ri.reporting_month',
                'ri.financial_status',
                'ri.original_filename',
                DB::raw('COUNT(*) as row_count'),
                DB::raw('SUM(rr.collected_revenue) as collected'),
                DB::raw('SUM(rr.earnings) as earnings'),
            ])
            ->map(fn ($r) => [
                'import_id' => (int) $r->report_import_id,
                'file' => $r->original_filename,
                'month' => $r->reporting_month ? substr((string) $r->reporting_month, 0, 7) : null,
                'status' => $r->financial_status,
                'rate' => round((float) $r->label_rate * 100, 4),
                'rows' => (int) $r->row_count,
                'collected' => round((float) $r->collected, 2),
                'earnings' => round((float) $r->earnings, 2),
            ])
            ->all();
    }
}
