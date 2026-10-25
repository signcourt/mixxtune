<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W108 MIXXTUNE_SIGNATURE_DESIGNER: the settings of a signature made in the panel's designer */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mail_accounts', 'signature_design')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->json('signature_design')->nullable()->after('signature_html');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mail_accounts', 'signature_design')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('signature_design');
            });
        }
    }
};
