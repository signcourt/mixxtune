<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W137 MIXXTUNE_REPORT_COLUMN_PREFS
 * Which raw report columns a label / artist is allowed to see when they open or download their
 * revenue report. One row with label_id = NULL is the default template for everyone; a row with a
 * label_id overrides the default just for that label. The Super Admin always keeps the full report.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_column_prefs')) {
            return;
        }

        Schema::create('report_column_prefs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('label_id')->nullable()->unique(); // NULL = default template
            $table->json('columns')->nullable();   // raw_data keys the user may see; NULL = all
            $table->boolean('show_label_rate')->default(true);
            $table->boolean('show_earning')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_column_prefs');
    }
};
