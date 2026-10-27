<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W121 MIXXTUNE_WEBSITE_JOIN
 * "Join" requests sent from the homepage (mixxtune.com) and the follow-up
 * notes the Super Admin keeps while calling the person. The homepage text and
 * the Join form questions themselves are kept in system_settings
 * (key website.homepage), so no table is needed for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_join_requests')) {
            Schema::create('website_join_requests', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->string('name', 190);
                $table->string('phone', 40);
                $table->string('email', 190)->nullable();
                $table->string('role', 120)->nullable();
                $table->string('brand', 190)->nullable();
                $table->string('city', 190)->nullable();
                $table->text('message')->nullable();
                $table->json('answers')->nullable(); /* every question as it was asked: [{id, label, value}] */
                $table->string('status', 20)->default('new'); /* new | called | discussion | joined | not_interested | spam */
                $table->timestamp('next_follow_up_at')->nullable();
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->unsignedBigInteger('account_user_id')->nullable();
                $table->string('source', 50)->default('homepage');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index('next_follow_up_at');
                $table->index('phone');
            });
        }

        if (!Schema::hasTable('website_join_request_notes')) {
            Schema::create('website_join_request_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('join_request_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('kind', 20)->default('note'); /* note | status | system */
                $table->text('body');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_join_request_notes');
        Schema::dropIfExists('website_join_requests');
    }
};
