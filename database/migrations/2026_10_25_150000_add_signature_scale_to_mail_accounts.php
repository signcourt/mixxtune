<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W104 MIXXTUNE_SIGNATURE_SIZE: how big the designed signature is shown (percent, 100 = as pasted) */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mail_accounts', 'signature_scale')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->unsignedSmallInteger('signature_scale')->default(100)->after('signature_html');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mail_accounts', 'signature_scale')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('signature_scale');
            });
        }
    }
};
