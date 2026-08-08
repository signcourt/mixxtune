<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('label_revenue_shares')) {
            return;
        }

        Schema::create(
            'label_revenue_shares',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('master_label_id')
                    ->constrained('labels')
                    ->cascadeOnDelete();

                $table->string(
                    'beneficiary_type',
                    20
                );

                $table->unsignedBigInteger(
                    'beneficiary_id'
                );

                $table->decimal(
                    'revenue_share_percent',
                    7,
                    4
                )->default(100);

                $table->boolean(
                    'show_revenue_share'
                )->default(true);

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->date(
                    'effective_from'
                )->nullable();

                $table->date(
                    'effective_to'
                )->nullable();

                $table->foreignId(
                    'created_by'
                )->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'updated_by'
                )->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'master_label_id',
                    'beneficiary_type',
                    'beneficiary_id',
                ], 'label_revenue_share_lookup');

                $table->index([
                    'is_active',
                    'effective_from',
                    'effective_to',
                ], 'label_revenue_share_active');

                $table->unique([
                    'master_label_id',
                    'beneficiary_type',
                    'beneficiary_id',
                ], 'label_revenue_share_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'label_revenue_shares'
        );
    }
};
