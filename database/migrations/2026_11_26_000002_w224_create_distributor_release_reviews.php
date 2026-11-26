<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W224 - a sub-label's release waits here for its distributor before Mixx Tune reviews it */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('distributor_release_reviews')) {
            return;
        }
        Schema::create('distributor_release_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->index();
            $table->unsignedBigInteger('distributor_id')->index();
            $table->unsignedBigInteger('label_id')->nullable()->index();
            $table->string('status', 20)->default('pending')->index(); /* pending | approved | changes | rejected | closed */
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the table stays */
    }
};
