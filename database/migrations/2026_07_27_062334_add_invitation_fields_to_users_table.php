<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_status')
                ->default('not_invited')
                ->after('last_login_at');

            $table->string('invitation_token', 64)
                ->nullable()
                ->unique()
                ->after('invitation_status');

            $table->timestamp('invitation_sent_at')
                ->nullable()
                ->after('invitation_token');

            $table->timestamp('invitation_expires_at')
                ->nullable()
                ->after('invitation_sent_at');

            $table->timestamp('invitation_accepted_at')
                ->nullable()
                ->after('invitation_expires_at');

            $table->unsignedInteger('invitation_count')
                ->default(0)
                ->after('invitation_accepted_at');

            $table->text('invitation_error')
                ->nullable()
                ->after('invitation_count');

            $table->timestamp('password_set_at')
                ->nullable()
                ->after('invitation_error');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['invitation_token']);

            $table->dropColumn([
                'invitation_status',
                'invitation_token',
                'invitation_sent_at',
                'invitation_expires_at',
                'invitation_accepted_at',
                'invitation_count',
                'invitation_error',
                'password_set_at',
            ]);
        });
    }
};
