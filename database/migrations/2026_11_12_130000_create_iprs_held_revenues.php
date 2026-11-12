<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * IPRS payments held back at publish because the owner has no user account yet,
 * or the user is inactive. Nothing is credited to a wallet while a row sits here;
 * once the owner has an active user, the hold is released into their wallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iprs_held_revenues', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->unsignedBigInteger('iprs_upload_id');
            $table->unsignedBigInteger('iprs_line_id')->unique(); // one hold per royalty line
            $table->string('work_int_no', 40);

            $table->string('owner_type', 20);          // label | artist
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->unsignedBigInteger('track_id')->nullable();
            $table->unsignedBigInteger('release_id')->nullable();
            $table->string('isrc', 64)->nullable();

            $table->decimal('gross_amount', 18, 8)->default(0);
            $table->decimal('share_percentage', 8, 4)->default(0);
            $table->decimal('net_amount', 18, 8)->default(0);
            $table->string('royalty_type', 30)->default('publishing');

            // owner_user_missing | owner_user_inactive
            $table->string('reason', 40);
            // held | released
            $table->string('status', 20)->default('held');

            $table->unsignedBigInteger('royalty_ledger_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('status');
            $table->index('iprs_upload_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iprs_held_revenues');
    }
};
