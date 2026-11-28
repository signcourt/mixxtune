<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W241 MIXXTUNE_PROMOTION: promotion packages (written by a Super Admin) and the requests labels send. Creates tables only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_packages')) {
            Schema::create('promotion_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->text('details')->nullable();
                $table->string('price_text', 80)->nullable();
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promotion_requests')) {
            Schema::create('promotion_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('release_id')->nullable()->index();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->string('package_name', 120)->nullable();
                $table->text('message');
                $table->string('budget', 100)->nullable();
                $table->string('contact', 100)->nullable();
                $table->string('status', 20)->default('new')->index(); /* new | in_talk | accepted | done | declined */
                $table->text('staff_reply')->nullable();
                $table->unsignedBigInteger('replied_by')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        /* never dropped by a rollback: requests are data */
    }
};
