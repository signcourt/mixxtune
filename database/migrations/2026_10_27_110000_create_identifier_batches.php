<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W116 MIXXTUNE_CODE_LISTS
 * The UPC / ISRC codes allotted to Mixx Tune, uploaded as a list (Excel / CSV / txt or a first-last range).
 * Generate takes the next free one. One new table + one empty column on upc_codes / isrc_codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifier_batches', function (Blueprint $t) {
            $t->id();
            $t->string('type', 4);
            $t->string('source', 190)->nullable();
            $t->unsignedInteger('added')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('upc_codes', function (Blueprint $t) {
            $t->unsignedBigInteger('batch_id')->nullable()->after('notes')->index();
        });
        Schema::table('isrc_codes', function (Blueprint $t) {
            $t->unsignedBigInteger('batch_id')->nullable()->after('notes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('isrc_codes', fn (Blueprint $t) => $t->dropColumn('batch_id'));
        Schema::table('upc_codes', fn (Blueprint $t) => $t->dropColumn('batch_id'));
        Schema::dropIfExists('identifier_batches');
    }
};
