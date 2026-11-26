<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* W221 - distributors: a root label that runs its own branded panel with sub-labels below it */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('distributors')) {
            return;
        }
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('label_id')->unique();          /* root label of his tree */
            $table->unsignedBigInteger('user_id')->index();            /* owner login */
            $table->string('slug', 60)->unique();                      /* a-z 0-9 - : later slug.mixxtune.com */
            $table->string('host', 190)->nullable()->unique();         /* own URL (later wave) */
            $table->timestamp('host_verified_at')->nullable();
            $table->string('brand_name', 120)->nullable();             /* name shown in the panel */
            $table->string('dashboard_name', 120)->nullable();         /* small line under the name */
            $table->string('logo_path', 255)->nullable();
            $table->string('status', 20)->default('active')->index();  /* active | suspended */
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        /* production data is never dropped by a rollback - the table stays */
    }
};
