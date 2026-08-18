<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'revenue_mapping_rules',
            function (Blueprint $table) {
                if (
                    !Schema::hasColumn(
                        'revenue_mapping_rules',
                        'catalogue_label_id'
                    )
                ) {
                    $table
                        ->unsignedBigInteger(
                            'catalogue_label_id'
                        )
                        ->nullable()
                        ->after('owner_id')
                        ->index();
                }

                if (
                    !Schema::hasColumn(
                        'revenue_mapping_rules',
                        'artist_id'
                    )
                ) {
                    $table
                        ->unsignedBigInteger(
                            'artist_id'
                        )
                        ->nullable()
                        ->after(
                            'catalogue_label_id'
                        )
                        ->index();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'revenue_mapping_rules',
            function (Blueprint $table) {
                if (
                    Schema::hasColumn(
                        'revenue_mapping_rules',
                        'artist_id'
                    )
                ) {
                    $table->dropColumn(
                        'artist_id'
                    );
                }

                if (
                    Schema::hasColumn(
                        'revenue_mapping_rules',
                        'catalogue_label_id'
                    )
                ) {
                    $table->dropColumn(
                        'catalogue_label_id'
                    );
                }
            }
        );
    }
};
