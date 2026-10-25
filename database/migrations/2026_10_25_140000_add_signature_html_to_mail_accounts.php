<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W102 MIXXTUNE_HTML_SIGNATURE: a designed signature pasted as HTML source code */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mail_accounts', 'signature_html')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->mediumText('signature_html')->nullable()->after('signature');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mail_accounts', 'signature_html')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('signature_html');
            });
        }
    }
};
