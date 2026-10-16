<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_email_otps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('otp_hash', 255);

            $table->timestamp('expires_at');

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->timestamp('consumed_at')
                ->nullable();

            $table->timestamp('sent_at');

            $table->string('ip_address', 45)
                ->nullable();

            $table->string('user_agent', 500)
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'expires_at',
            ]);

            $table->index([
                'user_id',
                'consumed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_email_otps');
    }
};
