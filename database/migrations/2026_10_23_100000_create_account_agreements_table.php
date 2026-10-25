<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W94 MIXXTUNE_ACCOUNT_AGREEMENTS
 * The signed agreement of a Label / Artist account: signed on, for how long,
 * ends on (worked out), the signed copy and the renewal history. The Super
 * Admin dashboard warns every day from 90 days before the end.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_agreements')) {
            return;
        }

        Schema::create('account_agreements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('signed_on');
            $table->unsignedSmallInteger('term_months');
            $table->date('ends_on');
            $table->unsignedSmallInteger('notice_days')->default(90);
            $table->string('status', 20)->default('active'); /* active | renewed | ended */
            $table->string('document_path', 500)->nullable();
            $table->string('document_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('renewed_from_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('close_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_agreements');
    }
};
