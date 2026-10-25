<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W110 MIXXTUNE_MAIL_STAR_DRAFTS: starred mails, and drafts kept in the panel (folder = drafts) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_messages', 'starred')) {
                $table->boolean('starred')->default(false)->after('seen');
                $table->index(['mail_account_id', 'starred'], 'mail_messages_account_starred_index');
            }
            if (!Schema::hasColumn('mail_messages', 'draft')) {
                $table->json('draft')->nullable()->after('starred');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (Schema::hasColumn('mail_messages', 'draft')) {
                $table->dropColumn('draft');
            }
            if (Schema::hasColumn('mail_messages', 'starred')) {
                $table->dropIndex('mail_messages_account_starred_index');
                $table->dropColumn('starred');
            }
        });
    }
};
