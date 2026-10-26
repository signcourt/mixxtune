<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W118 MIXXTUNE_MAKER_CHECKER
 * "Second approval": when switched on (Administration -> Second approval, OFF by default), money actions
 * need a "yes" from another Super Admin before they go through. One new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_approvals', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 190)->index();
            $t->text('what');
            $t->string('url', 500)->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('open')->index();
            $t->text('note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_approvals');
    }
};
