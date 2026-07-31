<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_status_logs', function (Blueprint $table): void {
            $table->string('public_id', 26)
                ->unique()
                ->after('id');

            $table->foreignId('release_id')
                ->after('public_id')
                ->constrained('releases')
                ->cascadeOnDelete();

            $table->string('old_status', 40)
                ->nullable()
                ->after('release_id');

            $table->string('new_status', 40)
                ->after('old_status');

            $table->string('action', 50)
                ->after('new_status');

            $table->text('remarks')
                ->nullable()
                ->after('action');

            $table->string('ip_address', 45)
                ->nullable()
                ->after('remarks');

            $table->text('user_agent')
                ->nullable()
                ->after('ip_address');

            $table->foreignId('changed_by')
                ->nullable()
                ->after('user_agent')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['release_id', 'created_at']);
            $table->index(['release_id', 'new_status']);
        });
    }

    public function down(): void
    {
        Schema::table('release_status_logs', function (Blueprint $table): void {
            $table->dropForeign(['release_id']);
            $table->dropForeign(['changed_by']);

            $table->dropIndex([
                'release_id',
                'created_at',
            ]);

            $table->dropIndex([
                'release_id',
                'new_status',
            ]);

            $table->dropUnique([
                'public_id',
            ]);

            $table->dropColumn([
                'public_id',
                'release_id',
                'old_status',
                'new_status',
                'action',
                'remarks',
                'ip_address',
                'user_agent',
                'changed_by',
            ]);
        });
    }
};
