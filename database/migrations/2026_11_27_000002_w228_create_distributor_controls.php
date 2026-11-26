<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W228 - Super Admin's controls over one distributor: hold the whole tree, limits, agreement file. New table only. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('distributor_controls')) {
            return;
        }
        Schema::create('distributor_controls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributor_id')->unique();
            $table->boolean('tree_hold')->default(false);
            $table->string('hold_reason', 300)->nullable();
            $table->timestamp('hold_at')->nullable();
            $table->unsignedInteger('max_sub_labels')->nullable();
            $table->unsignedInteger('max_monthly_releases')->nullable();
            $table->string('agreement_path', 255)->nullable();
            $table->string('agreement_name', 190)->nullable();
            $table->timestamp('agreement_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the table stays */
    }
};
