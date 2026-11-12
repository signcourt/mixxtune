<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-owner, per-type IPRS royalty rates.
 *
 * owner_share_percentage is what the owner KEEPS (e.g. 80 => owner gets 80%,
 * MIXX TUNE retains 20%). royalty_type lets a label carry different rates for
 * publishing, PPL, mechanical, etc. A row with owner_type "default" (owner_id 0)
 * is the global fallback for that royalty type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iprs_royalty_rates', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20);          // label | artist | default
            $table->unsignedBigInteger('owner_id')->default(0); // 0 for the global default
            $table->string('royalty_type', 30);        // publishing | ppl | mechanical | other
            $table->decimal('owner_share_percentage', 5, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'royalty_type'], 'iprs_rates_owner_type_unique');
        });

        Schema::table('iprs_uploads', function (Blueprint $table) {
            $table->string('royalty_type', 30)->default('publishing')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('iprs_uploads', function (Blueprint $table) {
            $table->dropColumn('royalty_type');
        });
        Schema::dropIfExists('iprs_royalty_rates');
    }
};
