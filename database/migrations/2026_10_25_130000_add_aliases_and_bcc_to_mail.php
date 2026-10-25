<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W101 MIXXTUNE_MAIL_BCC_ALIASES
 * mail_accounts.aliases: the other addresses of a mailbox (Hostinger aliases) a mail can be sent from;
 * mail_messages.bcc: who got a blind copy of a mail sent from the panel (kept in the panel only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_accounts') && !Schema::hasColumn('mail_accounts', 'aliases')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->json('aliases')->nullable()->after('display_name');
            });
        }
        if (Schema::hasTable('mail_messages') && !Schema::hasColumn('mail_messages', 'bcc')) {
            Schema::table('mail_messages', function (Blueprint $table) {
                $table->json('bcc')->nullable()->after('cc');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_messages') && Schema::hasColumn('mail_messages', 'bcc')) {
            Schema::table('mail_messages', function (Blueprint $table) {
                $table->dropColumn('bcc');
            });
        }
        if (Schema::hasTable('mail_accounts') && Schema::hasColumn('mail_accounts', 'aliases')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('aliases');
            });
        }
    }
};
