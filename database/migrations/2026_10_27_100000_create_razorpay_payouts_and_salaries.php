<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * Bank transfers through RazorpayX (withdrawals and staff salaries) + staff list + monthly salary sheets.
 * Only new tables - nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30)->unique();
            $t->boolean('enabled')->default(false);
            $t->string('key_id', 80)->nullable();
            $t->text('key_secret')->nullable();
            $t->text('webhook_secret')->nullable();
            $t->text('account_number')->nullable();
            $t->string('narration', 30)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payout_beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->string('owner_type', 20);
            $t->unsignedBigInteger('owner_id');
            $t->boolean('live')->default(false);
            $t->char('details_hash', 64);
            $t->string('contact_id', 40);
            $t->string('fund_account_id', 40);
            $t->timestamps();
            $t->unique(['owner_type', 'owner_id', 'live', 'details_hash'], 'payout_beneficiaries_unique');
        });

        Schema::create('staff_members', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('designation', 120)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('phone', 20)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('account_holder_name', 120)->nullable();
            $t->text('bank_account_number')->nullable();
            $t->string('ifsc_code', 11)->nullable();
            $t->string('bank_name', 120)->nullable();
            $t->text('pan_number')->nullable();
            $t->decimal('monthly_salary', 12, 2)->default(0);
            $t->date('joined_on')->nullable();
            $t->string('status', 12)->default('active')->index();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('salary_runs', function (Blueprint $t) {
            $t->id();
            $t->char('month', 7)->unique();
            $t->string('status', 12)->default('draft');
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('salary_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salary_run_id')->constrained('salary_runs')->cascadeOnDelete();
            $t->foreignId('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $t->decimal('salary', 12, 2)->default(0);
            $t->decimal('additions', 12, 2)->default(0);
            $t->decimal('deductions', 12, 2)->default(0);
            $t->decimal('net', 12, 2)->default(0);
            $t->string('note', 255)->nullable();
            $t->string('status', 12)->default('unpaid');
            $t->string('utr', 60)->nullable();
            $t->string('paid_how', 10)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('bank_payout_id')->nullable();
            $t->json('snapshot')->nullable();
            $t->timestamps();
            $t->unique(['salary_run_id', 'staff_member_id']);
        });

        Schema::create('bank_payouts', function (Blueprint $t) {
            $t->id();
            $t->string('public_id', 40)->unique();
            $t->string('purpose', 20);
            $t->foreignId('withdrawal_id')->nullable()->constrained('withdrawals')->nullOnDelete();
            $t->foreignId('salary_item_id')->nullable()->constrained('salary_items')->nullOnDelete();
            $t->string('payee_name', 120);
            $t->string('account_hint', 60)->nullable();
            $t->unsignedBigInteger('amount_paise');
            $t->string('mode', 10)->default('NEFT');
            $t->boolean('live')->default(false);
            $t->string('status', 20)->default('creating')->index();
            $t->uuid('idempotency_key')->unique();
            $t->string('razorpay_payout_id', 40)->nullable()->unique();
            $t->string('fund_account_id', 40)->nullable();
            $t->char('details_hash', 64)->nullable();
            $t->json('deduction')->nullable();
            $t->string('utr', 60)->nullable();
            $t->string('failure_reason', 500)->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('payout_requested_at')->nullable();
            $t->timestamp('last_requested_at')->nullable();
            $t->boolean('settled')->default(false);
            $t->timestamp('settled_at')->nullable();
            $t->boolean('reversed_after_paid')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_checked_at')->nullable();
            $t->json('last_response')->nullable();
            $t->timestamps();
            $t->index(['purpose', 'status']);
        });

        Schema::create('razorpay_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 80)->unique();
            $t->string('event', 60);
            $t->string('payout_id', 40)->nullable()->index();
            $t->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_webhook_events');
        Schema::dropIfExists('bank_payouts');
        Schema::dropIfExists('salary_items');
        Schema::dropIfExists('salary_runs');
        Schema::dropIfExists('staff_members');
        Schema::dropIfExists('payout_beneficiaries');
        Schema::dropIfExists('payment_gateway_settings');
    }
};
