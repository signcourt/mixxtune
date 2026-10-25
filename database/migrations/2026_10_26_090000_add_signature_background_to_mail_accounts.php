<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * W106 MIXXTUNE_SIGNATURE_CARD: the background behind the designed signature.
 * box = a white (or chosen colour) card only as big as the signature, the rest see-through;
 * none = no background at all; pasted = as in the pasted code.
 * Every mailbox starts with "box" (owner: for all signatures), see-through ones keep "none".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mail_accounts', 'signature_bg')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->string('signature_bg', 12)->default('box')->after('signature_transparent');
                $table->string('signature_bg_color', 7)->default('#ffffff')->after('signature_bg');
                $table->unsignedTinyInteger('signature_bg_padding')->default(12)->after('signature_bg_color');
                $table->unsignedTinyInteger('signature_bg_radius')->default(8)->after('signature_bg_padding');
            });
            DB::table('mail_accounts')->where('signature_transparent', true)->update(['signature_bg' => 'none']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mail_accounts', 'signature_bg')) {
            Schema::table('mail_accounts', function (Blueprint $table) {
                $table->dropColumn(['signature_bg', 'signature_bg_color', 'signature_bg_padding', 'signature_bg_radius']);
            });
        }
    }
};
