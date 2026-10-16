<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'wordpress_legacy_migration_runs',
            function (Blueprint $table) {
                $table->id();

                $table->string('source_key', 100);
                $table->string('status', 30)
                    ->default('pending');

                $table->boolean('dry_run')
                    ->default(true);

                $table->json('source_snapshot')
                    ->nullable();

                $table->json('summary')
                    ->nullable();

                $table->text('error_message')
                    ->nullable();

                $table->timestamp('started_at')
                    ->nullable();

                $table->timestamp('completed_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['source_key', 'status'],
                    'wp_legacy_run_status_idx'
                );
            }
        );

        Schema::create(
            'wordpress_legacy_id_maps',
            function (Blueprint $table) {
                $table->id();

                $table->string('source_key', 100);

                $table->string(
                    'source_entity_type',
                    50
                );

                /*
                 * String instead of bigint intentionally.
                 * Allows WP IDs plus compound legacy keys.
                 */
                $table->string(
                    'source_entity_id',
                    191
                );

                $table->string(
                    'target_entity_type',
                    100
                );

                $table->unsignedBigInteger(
                    'target_entity_id'
                )->nullable();

                $table->string(
                    'migration_status',
                    30
                )->default('mapped');

                /*
                 * Preserve enough original information
                 * for audit and deterministic reruns.
                 */
                $table->json('source_snapshot')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                /*
                 * Fundamental idempotency guarantee:
                 * one old WordPress entity can have only
                 * one migration mapping per source.
                 */
                $table->unique(
                    [
                        'source_key',
                        'source_entity_type',
                        'source_entity_id',
                    ],
                    'wp_legacy_source_unique'
                );

                $table->index(
                    [
                        'target_entity_type',
                        'target_entity_id',
                    ],
                    'wp_legacy_target_idx'
                );

                $table->index(
                    [
                        'source_entity_type',
                        'migration_status',
                    ],
                    'wp_legacy_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'wordpress_legacy_id_maps'
        );

        Schema::dropIfExists(
            'wordpress_legacy_migration_runs'
        );
    }
};
