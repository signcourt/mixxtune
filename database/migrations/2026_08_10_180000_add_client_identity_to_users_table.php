<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'state_code')) {
                $table->string('state_code', 2)
                    ->nullable()
                    ->after('country');
            }

            if (! Schema::hasColumn('users', 'client_id')) {
                $table->string('client_id', 32)
                    ->nullable()
                    ->unique()
                    ->after('state_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'client_id')) {
                $table->dropUnique(['client_id']);
                $table->dropColumn('client_id');
            }

            if (Schema::hasColumn('users', 'state_code')) {
                $table->dropColumn('state_code');
            }
        });
    }
};
