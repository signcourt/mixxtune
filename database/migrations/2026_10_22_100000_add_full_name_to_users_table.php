<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W79 MIXXTUNE_ACCOUNT_FULL_NAME
 * users.name      = account name (shown everywhere, e.g. "Sanatan Records")
 * users.full_name = the person the account is created for
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'full_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('full_name', 150)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'full_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('full_name');
            });
        }
    }
};
