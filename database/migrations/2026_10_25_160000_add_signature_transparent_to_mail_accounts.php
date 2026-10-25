<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W105 MIXXTUNE_SIGNATURE_SEE_THROUGH: remove the white background of the designed signature */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mail_accounts', 'signature_transparent')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->boolean('signature_transparent')->default(false)->after('signature_scale');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mail_accounts', 'signature_transparent')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn('signature_transparent');
            });
        }
    }
};
