<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('revenue_imports', 'public_id')) {
            Schema::table('revenue_imports', function (Blueprint $table) {
                $table->uuid('public_id')
                    ->nullable()
                    ->after('id');
            });
        }

        DB::table('revenue_imports')
            ->whereNull('public_id')
            ->orderBy('id')
            ->each(function ($row): void {
                DB::table('revenue_imports')
                    ->where('id', $row->id)
                    ->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
            });

        $indexes = collect(
            DB::select("SHOW INDEX FROM revenue_imports")
        )->pluck('Key_name');

        if (!$indexes->contains('revenue_imports_public_id_unique')) {
            Schema::table('revenue_imports', function (Blueprint $table) {
                $table->unique(
                    'public_id',
                    'revenue_imports_public_id_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Existing production records सुरक्षित रखने के लिए rollback खाली है।
    }
};
