<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('royalty_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('royalty_ledgers', 'source_type')) {
                $table->string('source_type', 40)
                    ->nullable()
                    ->after('revenue_label_mapping_id');
            }

            if (!Schema::hasColumn('royalty_ledgers', 'source_key')) {
                $table->string('source_key', 191)
                    ->nullable()
                    ->after('source_type');
            }

            if (!Schema::hasColumn('royalty_ledgers', 'beneficiary_type')) {
                $table->string('beneficiary_type', 30)
                    ->nullable()
                    ->after('source_key');
            }

            if (!Schema::hasColumn('royalty_ledgers', 'beneficiary_id')) {
                $table->unsignedBigInteger('beneficiary_id')
                    ->nullable()
                    ->after('beneficiary_type');
            }

            if (!Schema::hasColumn('royalty_ledgers', 'master_label_id')) {
                $table->unsignedBigInteger('master_label_id')
                    ->nullable()
                    ->after('beneficiary_id');
            }
        });

        /*
         * One canonical source allocation must never be posted twice.
         *
         * source_key will contain a deterministic hash representing:
         * source rows + period + beneficiary + applied split.
         */
        $indexName = 'royalty_ledgers_source_type_source_key_unique';

        $indexExists = match (DB::connection()->getDriverName()) {
            'sqlite' => collect(
                DB::select("PRAGMA index_list('royalty_ledgers')")
            )->contains(
                fn ($index) => ($index->name ?? null) === $indexName
            ),
            default => collect(
                DB::select("SHOW INDEX FROM royalty_ledgers")
            )->contains(
                fn ($index) => ($index->Key_name ?? null) === $indexName
            ),
        };

        if (!$indexExists) {
            Schema::table('royalty_ledgers', function (Blueprint $table) {
                $table->unique(
                    ['source_type', 'source_key'],
                    'royalty_ledgers_source_type_source_key_unique'
                );
            });
        }

        /*
         * Normalize existing label wallet identity.
         *
         * Sanatan Records:
         * label_id = 1
         * user_id  = 3
         *
         * Only attach label_id when no separate label wallet exists.
         */
        $userWallet = DB::table('wallets')
            ->where('user_id', 3)
            ->where('currency', 'INR')
            ->first();

        $labelWallet = DB::table('wallets')
            ->where('label_id', 1)
            ->where('currency', 'INR')
            ->first();

        if (
            $userWallet &&
            !$labelWallet &&
            $userWallet->label_id === null
        ) {
            DB::table('wallets')
                ->where('id', $userWallet->id)
                ->update([
                    'label_id' => 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $indexName = 'royalty_ledgers_source_type_source_key_unique';

        $indexExists = match (DB::connection()->getDriverName()) {
            'sqlite' => collect(
                DB::select("PRAGMA index_list('royalty_ledgers')")
            )->contains(
                fn ($index) => ($index->name ?? null) === $indexName
            ),
            default => collect(
                DB::select("SHOW INDEX FROM royalty_ledgers")
            )->contains(
                fn ($index) => ($index->Key_name ?? null) === $indexName
            ),
        };

        if ($indexExists) {
            Schema::table('royalty_ledgers', function (Blueprint $table) {
                $table->dropUnique(
                    'royalty_ledgers_source_type_source_key_unique'
                );
            });
        }

        Schema::table('royalty_ledgers', function (Blueprint $table) {
            $columns = [
                'source_type',
                'source_key',
                'beneficiary_type',
                'beneficiary_id',
                'master_label_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('royalty_ledgers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
