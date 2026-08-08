<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_rows')) {
            return;
        }

        if (! Schema::hasColumn('report_rows', 'revenue_owner_type')) {
            Schema::table('report_rows', function (Blueprint $table): void {
                $table->string('revenue_owner_type', 32)
                    ->nullable()
                    ->index();
            });
        }

        if (! Schema::hasColumn('report_rows', 'revenue_owner_id')) {
            Schema::table('report_rows', function (Blueprint $table): void {
                $table->unsignedBigInteger('revenue_owner_id')
                    ->nullable()
                    ->index();
            });
        }

        if (! Schema::hasColumn('report_rows', 'mapping_status')) {
            Schema::table('report_rows', function (Blueprint $table): void {
                $table->string('mapping_status', 32)
                    ->nullable()
                    ->index();
            });
        }

        if (! Schema::hasColumn('report_rows', 'mapped_at')) {
            Schema::table('report_rows', function (Blueprint $table): void {
                $table->timestamp('mapped_at')
                    ->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('report_rows')) {
            return;
        }

        foreach ([
            'mapped_at',
            'mapping_status',
            'revenue_owner_id',
            'revenue_owner_type',
        ] as $column) {
            if (Schema::hasColumn('report_rows', $column)) {
                Schema::table(
                    'report_rows',
                    function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    }
                );
            }
        }
    }
};
