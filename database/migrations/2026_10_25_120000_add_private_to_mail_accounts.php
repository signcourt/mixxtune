<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W100 MIXXTUNE_MY_MAILBOX
 * A private mailbox (for example the Super Admin's own) is opened only by the
 * people it is given to - other Super Admins do not see it. Existing
 * mailboxes stay shared (0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_accounts') && !Schema::hasColumn('mail_accounts', 'is_private')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->boolean('is_private')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_accounts') && Schema::hasColumn('mail_accounts', 'is_private')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('is_private');
            });
        }
    }
};
