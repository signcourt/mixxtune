<?php

namespace App\Services\V2;

use App\Mail\OwnerStatementMail;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/*
 * MIXXTUNE_OWNER_STATEMENT_V1
 *
 * Monthly owner statement: a readable PDF (summary, by store, by country,
 * top tracks) built from the report rows that were posted into the
 * statement, plus e-mailing it to the label / artist owner. Sending is
 * recorded in audit_logs (statement.emailed) so nobody gets it twice by
 * accident. Read-only on money: nothing here changes amounts.
 */
class OwnerStatementService
{
    public const SENT_ACTION = 'statement.emailed';
    public const BATCH_SIZE = 10;

    /* ------------------------------------------------------------ recipients */

    public function owner(RoyaltyStatement $statement): array
    {
        if ($statement->label_id) {
            $row = DB::table('labels as l')
                ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->where('l.id', $statement->label_id)
                ->first(['l.id', 'l.name as label_name', 'u.id as user_id', 'u.name as user_name', 'u.email', 'u.account_status']);

            return [
                'type' => 'label',
                'id' => (int) $statement->label_id,
                'title' => $row->label_name ?? ('Label #'.$statement->label_id),
                'user_id' => $row && $row->user_id ? (int) $row->user_id : null,
                'name' => $row->user_name ?? null,
                'email' => $row->email ?? null,
                'account_status' => $row->account_status ?? null,
            ];
        }

        $row = DB::table('artists as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.id', $statement->artist_id)
            ->first(['a.id', 'a.stage_name', 'u.id as user_id', 'u.name as user_name', 'u.email', 'u.account_status']);

        return [
            'type' => 'artist',
            'id' => (int) $statement->artist_id,
            'title' => $row->stage_name ?? ('Artist #'.$statement->artist_id),
            'user_id' => $row && $row->user_id ? (int) $row->user_id : null,
            'name' => $row->user_name ?? null,
            'email' => $row->email ?? null,
            'account_status' => $row->account_status ?? null,
        ];
    }

    /* ------------------------------------------------------------ data */

    private ?array $importOverride = null;

    private function importIds(RoyaltyStatement $statement): array
    {
        if ($this->importOverride !== null) {
            return $this->importOverride;
        }

        if (!Schema::hasTable('report_import_postings')) {
            return [];
        }

        return DB::table('report_import_postings')
            ->where('royalty_statement_id', $statement->id)
            ->pluck('report_import_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function rows(RoyaltyStatement $statement)
    {
        $owner = $statement->label_id ? ['label', (int) $statement->label_id] : ['artist', (int) $statement->artist_id];

        return DB::table('report_rows')
            ->whereIn('report_import_id', $this->importIds($statement) ?: [0])
            ->where('revenue_owner_type', $owner[0])
            ->where('revenue_owner_id', $owner[1]);
    }

    public function data(RoyaltyStatement $statement): array
    {
        $owner = $this->owner($statement);
        $importIds = $this->importIds($statement);

        $summary = $this->rows($statement)
            ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(collected_revenue),0) as collected, COALESCE(SUM(earnings),0) as earnings, MIN(label_rate) as min_rate, MAX(label_rate) as max_rate')
            ->first();

        $byStore = $this->rows($statement)
            ->selectRaw("COALESCE(NULLIF(platform,''),'Other') as name, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(collected_revenue),0) as collected, COALESCE(SUM(earnings),0) as earnings")
            ->groupBy('name')
            ->orderByDesc('earnings')
            ->limit(40)
            ->get();

        $byCountry = $this->rows($statement)
            ->selectRaw("COALESCE(NULLIF(country_code,''),'--') as name, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(earnings),0) as earnings")
            ->groupBy('name')
            ->orderByDesc('earnings')
            ->limit(15)
            ->get();

        $topTracks = $this->rows($statement)
            ->selectRaw("COALESCE(NULLIF(track_title,''),'(untitled)') as title, COALESCE(isrc,'') as isrc, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(earnings),0) as earnings")
            ->groupBy('title', 'isrc')
            ->orderByDesc('earnings')
            ->limit(50)
            ->get();

        $reports = DB::table('report_imports')
            ->whereIn('id', $importIds ?: [0])
            ->orderBy('id')
            ->get(['id', 'original_filename', 'reporting_month']);

        $minRate = $summary && $summary->min_rate !== null ? round((float) $summary->min_rate * 100, 2) : null;
        $maxRate = $summary && $summary->max_rate !== null ? round((float) $summary->max_rate * 100, 2) : null;

        return [
            'statement' => $statement,
            'owner' => $owner,
            'branding' => $this->branding(),
            'reports' => $reports,
            'summary' => [
                'rows' => (int) ($summary->row_count ?? 0),
                'streams' => (float) ($summary->streams ?? 0),
                'collected' => (float) ($summary->collected ?? 0),
                'earnings' => (float) ($summary->earnings ?? 0),
                'rate' => $minRate === null ? null : ($minRate === $maxRate ? $minRate.'%' : $minRate.'% – '.$maxRate.'%'),
                'gross' => (float) $statement->gross_earnings,
                'deductions' => (float) $statement->commission_amount + (float) $statement->tax_amount + (float) $statement->other_deductions,
                'net' => (float) $statement->net_payable,
            ],
            'by_store' => $byStore,
            'by_country' => $byCountry,
            'top_tracks' => $topTracks,
            'month_label' => $this->monthLabel((string) $statement->statement_month),
            'generated_at' => now()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
        ];
    }

    private function branding(): array
    {
        $name = config('app.name');
        $logo = null;

        try {
            $branding = app(SystemSettingService::class)->branding();
            $name = $branding['name'] ?? $name;
            $url = (string) ($branding['logo_url'] ?? '');

            if ($url !== '' && !str_starts_with($url, 'http')) {
                $path = public_path(ltrim($url, '/'));

                if (is_file($path) && filesize($path) < 2_000_000) {
                    $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
                    $logo = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return ['name' => $name, 'logo' => $logo];
    }

    public function monthLabel(string $month): string
    {
        $time = strtotime(substr($month, 0, 7).'-01');

        return $time ? date('F Y', $time) : $month;
    }

    public function fileName(RoyaltyStatement $statement): string
    {
        $owner = $this->owner($statement);
        $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $owner['title']), '-') ?: 'owner';

        return 'MixxTune-Statement-'.substr((string) $statement->statement_month, 0, 7).'-'.$slug.'.pdf';
    }

    public function pdf(RoyaltyStatement $statement): string
    {
        return Pdf::loadView('pdf.owner-statement', $this->data($statement))
            ->setPaper('a4')
            ->output();
    }

    /*
     * Preview (not saved, not sent): what the statement of $labelId will
     * look like for a report that is still pending review.
     */
    public function previewPdf(int $labelId, int $importId): string
    {
        $import = DB::table('report_imports')->where('id', $importId)->first(['id', 'reporting_month']);
        abort_unless($import, 404, 'Report not found.');

        $earnings = (float) DB::table('report_rows')
            ->where('report_import_id', $importId)
            ->where('revenue_owner_type', 'label')
            ->where('revenue_owner_id', $labelId)
            ->sum('earnings');

        $statement = new RoyaltyStatement([
            'label_id' => $labelId,
            'statement_month' => substr((string) $import->reporting_month, 0, 7),
            'currency' => 'INR',
            'gross_earnings' => $earnings,
            'commission_amount' => 0,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => $earnings,
            'status' => 'preview (not final)',
        ]);
        $statement->id = 0;

        $this->importOverride = [$importId];

        try {
            return Pdf::loadView('pdf.owner-statement', $this->data($statement) + ['preview' => true])->setPaper('a4')->output();
        } finally {
            $this->importOverride = null;
        }
    }

    public function pendingImports(): array
    {
        return DB::table('report_imports')
            ->where('financial_status', 'review_pending')
            ->orderByDesc('id')
            ->get(['id', 'original_filename', 'reporting_month'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->original_filename, 'month' => substr((string) $r->reporting_month, 0, 7)])
            ->all();
    }

    /* ------------------------------------------------------------ e-mail */

    public function months(): array
    {
        return DB::table('royalty_statements')
            ->select('statement_month')
            ->distinct()
            ->orderByDesc('statement_month')
            ->pluck('statement_month')
            ->map(fn ($m) => (string) $m)
            ->all();
    }

    public function sentLog(array $statementIds): array
    {
        if (!$statementIds || !Schema::hasTable('audit_logs')) {
            return [];
        }

        $out = [];

        foreach (DB::table('audit_logs')
            ->where('action', self::SENT_ACTION)
            ->where('auditable_type', RoyaltyStatement::class)
            ->whereIn('auditable_id', $statementIds)
            ->orderBy('id')
            ->get(['auditable_id', 'created_at', 'new_values']) as $row) {
            $values = json_decode((string) $row->new_values, true) ?: [];
            $out[(int) $row->auditable_id] = ['at' => (string) $row->created_at, 'to' => $values['to'] ?? null];
        }

        return $out;
    }

    public function listForMonth(string $month): array
    {
        $statements = RoyaltyStatement::query()->where('statement_month', $month)->orderByDesc('net_payable')->get();
        $sent = $this->sentLog($statements->pluck('id')->all());

        return $statements->map(function (RoyaltyStatement $s) use ($sent) {
            $owner = $this->owner($s);

            return [
                'id' => (int) $s->id,
                'owner_type' => $owner['type'],
                'title' => $owner['title'],
                'owner_name' => $owner['name'],
                'email' => $owner['email'],
                'account_status' => $owner['account_status'],
                'status' => $s->status,
                'gross' => round((float) $s->gross_earnings, 2),
                'net' => round((float) $s->net_payable, 2),
                'sent_at' => $sent[$s->id]['at'] ?? null,
                'sent_to' => $sent[$s->id]['to'] ?? null,
            ];
        })->values()->all();
    }

    /*
     * Send up to BATCH_SIZE statements of the month that were not e-mailed
     * yet (or exactly $onlyIds, which may be re-sent when $force is true).
     */
    public function sendBatch(string $month, ?array $onlyIds, bool $force, ?Request $request = null): array
    {
        @set_time_limit(300);

        $query = RoyaltyStatement::query()->where('statement_month', $month)->orderByDesc('net_payable');

        if ($onlyIds) {
            $query->whereIn('id', array_map('intval', $onlyIds));
        }

        $statements = $query->get();
        $sent = $this->sentLog($statements->pluck('id')->all());
        $results = [];
        $done = 0;

        foreach ($statements as $statement) {
            if ($done >= self::BATCH_SIZE) {
                break;
            }

            if (isset($sent[$statement->id]) && !$force) {
                continue;
            }

            $owner = $this->owner($statement);

            if (!$owner['email']) {
                if (!$onlyIds && isset($sent[$statement->id])) {
                    continue;
                }

                $results[] = ['id' => (int) $statement->id, 'title' => $owner['title'], 'status' => 'no_email', 'message' => 'No owner account / e-mail for this '.$owner['type'].'.'];

                continue;
            }

            $done++;

            try {
                Mail::to($owner['email'])->send(new OwnerStatementMail(
                    $owner,
                    $this->monthLabel((string) $statement->statement_month),
                    round((float) $statement->net_payable, 2),
                    (string) $statement->currency ?: 'INR',
                    $this->pdf($statement),
                    $this->fileName($statement)
                ));

                $this->recordSent($statement, $owner, $request);
                $results[] = ['id' => (int) $statement->id, 'title' => $owner['title'], 'status' => 'sent', 'message' => $owner['email']];
            } catch (\Throwable $exception) {
                report($exception);
                $results[] = ['id' => (int) $statement->id, 'title' => $owner['title'], 'status' => 'failed', 'message' => $exception->getMessage()];
            }
        }

        $remaining = 0;

        if (!$onlyIds) {
            $after = $this->sentLog($statements->pluck('id')->all());

            foreach ($statements as $statement) {
                if (!isset($after[$statement->id]) && $this->owner($statement)['email']) {
                    $remaining++;
                }
            }
        }

        return ['results' => $results, 'remaining' => $remaining];
    }

    private function recordSent(RoyaltyStatement $statement, array $owner, ?Request $request): void
    {
        $description = "Statement {$statement->statement_month} for {$owner['type']} {$owner['title']} e-mailed to {$owner['email']}.";
        $values = ['to' => $owner['email'], 'owner_type' => $owner['type'], 'owner_id' => $owner['id'], 'net_payable' => (float) $statement->net_payable];

        try {
            if (Schema::hasTable('audit_logs')) {
                app(AuditLogService::class)->record(self::SENT_ACTION, 'statements', $description, [], $values, $statement, $request);

                return;
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        Log::info('MIXXTUNE_STATEMENT_EMAILED', ['statement_id' => $statement->id] + $values);
    }
}
