<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'labels',
                'revenue_share_percentage'
            )
        ) {
            Schema::table('labels', function (Blueprint $table) {
                $table
                    ->decimal(
                        'revenue_share_percentage',
                        5,
                        2
                    )
                    ->nullable()
                    ->after('royalty_share_percentage');
            });

            /*
             * Preserve existing label commercial rates.
             */
            DB::table('labels')->update([
                'revenue_share_percentage' =>
                    DB::raw(
                        'COALESCE(royalty_share_percentage, 100)'
                    ),
            ]);
        }

        if (
            ! Schema::hasColumn(
                'artists',
                'revenue_share_percentage'
            )
        ) {
            Schema::table('artists', function (Blueprint $table) {
                $table
                    ->decimal(
                        'revenue_share_percentage',
                        5,
                        2
                    )
                    ->nullable()
                    ->after('currency');
            });

            /*
             * Existing artist accounts remain financially
             * neutral until Super Admin explicitly changes
             * their assigned rate.
             */
            DB::table('artists')->update([
                'revenue_share_percentage' => 100,
            ]);
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'artists',
                'revenue_share_percentage'
            )
        ) {
            Schema::table('artists', function (Blueprint $table) {
                $table->dropColumn(
                    'revenue_share_percentage'
                );
            });
        }

        if (
            Schema::hasColumn(
                'labels',
                'revenue_share_percentage'
            )
        ) {
            Schema::table('labels', function (Blueprint $table) {
                $table->dropColumn(
                    'revenue_share_percentage'
                );
            });
        }
    }
};
