<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W80 MIXXTUNE_KYC_EDIT_LOCK
 * After KYC is submitted / verified the owner cannot change it. A Super
 * Admin can open it again for one change, only with the owner's signed
 * change form uploaded; every opening is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_edit_unlocks')) {
            return;
        }

        Schema::create('kyc_edit_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('unlocked_by')->nullable();
            $table->text('reason');
            $table->string('document_path', 500);
            $table->string('document_name', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_edit_unlocks');
    }
};
