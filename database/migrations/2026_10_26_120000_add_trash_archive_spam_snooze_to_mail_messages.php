<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W113 MIXXTUNE_MAIL_ACTIONS: Trash / Archive / Spam / Snooze.
 * folder can now also be trash | archive | spam. orig_folder = where it was before (to put it back),
 * server_folder / server_uid = where the mail now is in the mailbox at Hostinger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_messages', 'orig_folder')) {
                $table->string('orig_folder', 20)->nullable()->after('folder');
                $table->timestamp('trashed_at')->nullable()->after('orig_folder');
                $table->timestamp('snooze_until')->nullable()->after('trashed_at');
                $table->timestamp('woke_at')->nullable()->after('snooze_until');
                $table->string('server_folder', 190)->nullable()->after('woke_at');
                $table->unsignedBigInteger('server_uid')->nullable()->after('server_folder');
                $table->index(['mail_account_id', 'folder', 'snooze_until'], 'mail_messages_account_folder_snooze_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (Schema::hasColumn('mail_messages', 'orig_folder')) {
                $table->dropIndex('mail_messages_account_folder_snooze_index');
                $table->dropColumn(['orig_folder', 'trashed_at', 'snooze_until', 'woke_at', 'server_folder', 'server_uid']);
            }
        });
    }
};
