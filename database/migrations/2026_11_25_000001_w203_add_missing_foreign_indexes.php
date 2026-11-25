<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W203 (25 Nov 2026 audit): `_id` columns that are filtered/joined on but had no index.
 * ADD INDEX only - no rows are touched. Safe to re-run (skips what already exists).
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string}> */
    private array $targets = [
        ['report_rows', 'release_id'],
        ['report_rows', 'track_id'],
        ['invoices', 'royalty_statement_id'],
        ['release_activity_logs', 'user_id'],
        ['release_activity_logs', 'track_id'],
        ['iprs_royalty_lines', 'release_id'],
        ['iprs_royalty_lines', 'track_id'],
        ['iprs_royalty_lines', 'client_id'],
        ['iprs_held_revenues', 'release_id'],
        ['iprs_held_revenues', 'track_id'],
        ['iprs_held_revenues', 'user_id'],
        ['iprs_held_revenues', 'royalty_ledger_id'],
        ['catalogue_transfers', 'from_user_id'],
        ['catalogue_transfers', 'to_user_id'],
        ['catalogue_transfers', 'from_owner_id'],
        ['catalogue_transfers', 'to_owner_id'],
        ['import_errors', 'revenue_row_id'],
        ['report_import_postings', 'wallet_id'],
        ['release_store_deliveries', 'store_release_id'],
        ['office_tasks', 'template_id'],
        ['office_tasks', 'functional_role_id'],
        ['account_agreements', 'renewed_from_id'],
    ];

    private function name(string $t, string $c): string
    {
        return "{$t}_{$c}_w203_index";
    }

    public function up(): void
    {
        foreach ($this->targets as [$t, $c]) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $c)) {
                continue;
            }
            if (Schema::hasIndex($t, [$c])) {
                continue; // some index already covers this column
            }
            Schema::table($t, function (Blueprint $table) use ($t, $c) {
                $table->index([$c], $this->name($t, $c));
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as [$t, $c]) {
            if (Schema::hasTable($t) && Schema::hasIndex($t, $this->name($t, $c))) {
                Schema::table($t, function (Blueprint $table) use ($t, $c) {
                    $table->dropIndex($this->name($t, $c));
                });
            }
        }
    }
};
