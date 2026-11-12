<?php

namespace App\Services\V2;

use App\Models\Reports\ReportImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Moves a revenue report to a different reporting month EVERYWHERE it shows,
 * without changing any amount.
 *
 * A published report's earnings live as royalty_allocations under a per-owner,
 * per-month royalty_statement (whose totals are the SUM of its allocations).
 * Moving = re-point this report's allocations from the old-month statement to
 * the new-month statement, recompute both statements' totals, and re-tag the
 * report's ledgers and rows. Wallet balances never change (the credit already
 * happened); only the month bucket moves. A statement already "paid" blocks the
 * move.
 */
class ReportMonthChangeService
{
    /**
     * @return array{moved:bool,published:bool,old_month:string,new_month:string,statements_touched:int}
     */
    public function move(ReportImport $import, string $newMonth, int $actorId): array
    {
        $oldMonth = (string) $import->reporting_month;

        if ($oldMonth === $newMonth) {
            return ['moved' => false, 'published' => false, 'old_month' => $oldMonth, 'new_month' => $newMonth, 'statements_touched' => 0];
        }

        $published = in_array((string) $import->financial_status, ['approved_scheduled', 'published'], true);

        return DB::transaction(function () use ($import, $oldMonth, $newMonth, $published, $actorId) {
            $touched = 0;

            if ($published) {
                $allocs = DB::table('royalty_allocations as ra')
                    ->join('report_rows as rr', 'ra.report_row_id', '=', 'rr.id')
                    ->where('rr.report_import_id', $import->id)
                    ->select('ra.id', 'ra.royalty_statement_id')
                    ->get();

                $sourceIds = $allocs->pluck('royalty_statement_id')->unique()->filter()->values();
                $sources = DB::table('royalty_statements')->whereIn('id', $sourceIds)->lockForUpdate()->get()->keyBy('id');

                foreach ($sources as $s) {
                    if ($s->status === 'paid') {
                        throw new RuntimeException('A '.$oldMonth.' statement is already paid; move it before it is paid.');
                    }
                }

                /*
                 * Recoupment safety: if any affected statement carries an advance
                 * recovery, the statement's net_payable has deductions that do not
                 * line up one-to-one with this import's allocations, and the
                 * recovery rows are tagged to the old month. Re-pointing would
                 * silently mis-state the advance. Block and let an admin reverse
                 * the recoupment first.
                 */
                if ($sourceIds->isNotEmpty()
                    && DB::table('recoupment_recoveries')->whereIn('royalty_statement_id', $sourceIds)->exists()) {
                    throw new RuntimeException('This report has an advance recovery (recoupment) applied; its month cannot be changed automatically. Reverse the recoupment for '.$oldMonth.' first.');
                }

                // Map each source (old-month) statement to its matching new-month statement for the same owner+currency.
                $targetFor = [];
                foreach ($sources as $s) {
                    $target = $this->findOrCreateStatement($newMonth, $s);
                    if ($target->status === 'paid') {
                        throw new RuntimeException('The '.$newMonth.' statement is already paid; cannot merge into it.');
                    }
                    $targetFor[$s->id] = $target->id;
                }

                // Re-point this report's allocations from each source to its target.
                foreach ($sources as $s) {
                    $ids = $allocs->where('royalty_statement_id', $s->id)->pluck('id')->all();
                    if ($ids !== []) {
                        DB::table('royalty_allocations')->whereIn('id', $ids)->update(['royalty_statement_id' => $targetFor[$s->id], 'updated_at' => now()]);
                    }
                }

                // Re-point this import's postings to the matching target statement (audit trail follows the move).
                foreach ($sources as $s) {
                    DB::table('report_import_postings')
                        ->where('report_import_id', $import->id)
                        ->where('royalty_statement_id', $s->id)
                        ->update(['royalty_statement_id' => $targetFor[$s->id], 'updated_at' => now()]);
                }

                // Recompute totals for every affected statement (old + new).
                foreach (array_unique(array_merge($sourceIds->all(), array_values($targetFor))) as $sid) {
                    $this->recompute((int) $sid);
                    $touched++;
                }

                /*
                 * Re-tag this import's wallet transactions. Report postings store
                 * the month inside metadata (reporting_month) and in the human
                 * description, not in financial_month (which stays null for
                 * reports). The wallet balance never changes; only the month label
                 * and the statement pointer in metadata move.
                 */
                $walletTxns = DB::table('wallet_transactions')
                    ->whereRaw("JSON_EXTRACT(metadata, '$.report_import_id') = ?", [$import->id])
                    ->get();
                foreach ($walletTxns as $tx) {
                    $meta = json_decode((string) ($tx->metadata ?? '{}'), true) ?: [];
                    $meta['reporting_month'] = $newMonth;
                    if (isset($meta['statement_id']) && isset($targetFor[(int) $meta['statement_id']])) {
                        $meta['statement_id'] = $targetFor[(int) $meta['statement_id']];
                    }
                    DB::table('wallet_transactions')->where('id', $tx->id)->update([
                        'metadata' => json_encode($meta),
                        'description' => 'Royalty import '.$newMonth,
                        'financial_month' => $tx->financial_month !== null ? $newMonth.'-01' : null,
                        'updated_at' => now(),
                    ]);
                }
            }

            // Always: the report import and its rows carry reporting_month.
            DB::table('report_rows')
                ->where('report_import_id', $import->id)
                ->update(['reporting_month' => $newMonth, 'updated_at' => now()]);

            DB::table('report_imports')->where('id', $import->id)->update(['reporting_month' => $newMonth, 'updated_at' => now()]);

            return ['moved' => true, 'published' => $published, 'old_month' => $oldMonth, 'new_month' => $newMonth, 'statements_touched' => $touched];
        });
    }

    /** Find the new-month statement for the same owner+currency, or create an empty one. */
    private function findOrCreateStatement(string $month, object $source): object
    {
        $q = DB::table('royalty_statements')
            ->where('statement_month', $month)
            ->where('currency', $source->currency);

        $source->artist_id === null ? $q->whereNull('artist_id') : $q->where('artist_id', $source->artist_id);
        $source->label_id === null ? $q->whereNull('label_id') : $q->where('label_id', $source->label_id);

        $existing = $q->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        $id = DB::table('royalty_statements')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'artist_id' => $source->artist_id,
            'label_id' => $source->label_id,
            'statement_month' => $month,
            'currency' => $source->currency,
            'gross_earnings' => 0,
            'commission_amount' => 0,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 0,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('royalty_statements')->where('id', $id)->lockForUpdate()->first();
    }

    /** Recompute a statement's totals from its remaining allocations. */
    private function recompute(int $statementId): void
    {
        $t = DB::table('royalty_allocations')
            ->where('royalty_statement_id', $statementId)
            ->selectRaw('COALESCE(SUM(gross_amount),0) as gross_amount, COALESCE(SUM(net_amount),0) as net_amount')
            ->first();

        $gross = round((float) ($t->gross_amount ?? 0), 8);
        $allocNet = round((float) ($t->net_amount ?? 0), 8);

        // Preserve any statement-level deductions already recorded (tax, other).
        // net_payable = allocation net minus those deductions, never below zero.
        $stmt = DB::table('royalty_statements')->where('id', $statementId)->first();
        $tax = round((float) ($stmt->tax_amount ?? 0), 8);
        $other = round((float) ($stmt->other_deductions ?? 0), 8);
        $net = max(0.0, round($allocNet - $tax - $other, 8));

        DB::table('royalty_statements')->where('id', $statementId)->update([
            'gross_earnings' => $gross,
            'commission_amount' => round($gross - $allocNet, 8),
            'net_payable' => $net,
            'updated_at' => now(),
        ]);
    }
}
