<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W226 - money inside a distributor's tree (Mixx Tune pays the distributor; he pays his sub-labels himself).
 *   distributor_sub_label_rates    = the sub-label's share % frozen per report month
 *   distributor_sub_label_payments = the distributor's own register of what he paid a sub-label
 * New tables only. Nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('distributor_sub_label_rates')) {
            Schema::create('distributor_sub_label_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distributor_id')->index();
                $table->unsignedBigInteger('label_id');
                $table->string('report_month', 7);
                $table->decimal('percent', 7, 3);
                $table->timestamp('created_at')->nullable();
                $table->unique(['label_id', 'report_month'], 'w226_rate_label_month');
            });
        }
        if (!Schema::hasTable('distributor_sub_label_payments')) {
            Schema::create('distributor_sub_label_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distributor_id')->index();
                $table->unsignedBigInteger('label_id')->index();
                $table->decimal('amount', 18, 2);
                $table->date('paid_on');
                $table->string('method', 40)->nullable();
                $table->string('reference', 120)->nullable();
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->string('cancel_reason', 300)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the tables stay */
    }
};
