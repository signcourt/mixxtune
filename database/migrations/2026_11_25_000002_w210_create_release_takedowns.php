<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W210 - every takedown / takedown request with who, why, when and the status before */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('release_takedowns')) {
            return;
        }
        Schema::create('release_takedowns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->index();
            $table->string('status', 20)->default('requested')->index(); /* requested | done | rejected */
            $table->text('reason')->nullable();
            $table->string('status_before', 40)->nullable();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->unsignedBigInteger('approval_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the table stays */
    }
};
