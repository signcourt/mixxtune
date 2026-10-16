<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rights_royalty_allocations')) {
            return;
        }

        Schema::create(
            'rights_royalty_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->ulid('public_id')
                    ->unique();

                /*
                 * Immutable source row.
                 *
                 * One Rights source row may produce:
                 * - one direct-owner allocation, or
                 * - child + master allocations.
                 */
                $table->unsignedBigInteger(
                    'rights_royalty_row_id'
                );

                /*
                 * Snapshot of mapped catalogue identity.
                 */
                $table->unsignedBigInteger('release_id')
                    ->nullable();

                $table->unsignedBigInteger('track_id')
                    ->nullable();

                /*
                 * Financial beneficiary snapshot.
                 */
                $table->string(
                    'beneficiary_type',
                    20
                );

                $table->unsignedBigInteger(
                    'beneficiary_id'
                );

                $table->unsignedBigInteger('user_id')
                    ->nullable();

                $table->unsignedBigInteger('label_id')
                    ->nullable();

                $table->unsignedBigInteger('artist_id')
                    ->nullable();

                /*
                 * Root/master label when this allocation
                 * belongs to a two-tier agreement.
                 *
                 * Null for a standalone/direct owner.
                 */
                $table->unsignedBigInteger(
                    'master_label_id'
                )->nullable();

                /*
                 * Allocation classification:
                 *
                 * direct
                 * child
                 * master_retained
                 */
                $table->string(
                    'allocation_type',
                    30
                );

                /*
                 * Contract snapshot.
                 *
                 * Never recalculate historical allocations
                 * from the current account/share settings.
                 */
                $table->decimal(
                    'share_percentage',
                    8,
                    4
                );

                $table->decimal(
                    'gross_amount',
                    20,
                    8
                );

                $table->decimal(
                    'allocated_amount',
                    20,
                    8
                );

                $table->string(
                    'currency',
                    3
                );

                /*
                 * Date-effective agreement snapshot.
                 * Null for direct allocations.
                 */
                $table->unsignedBigInteger(
                    'revenue_share_id'
                )->nullable();

                $table->date(
                    'share_effective_from'
                )->nullable();

                $table->date(
                    'share_effective_to'
                )->nullable();

                /*
                 * Deterministic posting identity.
                 *
                 * Later royalty ledger publication will use
                 * this allocation as its canonical source.
                 */
                $table->string(
                    'source_key',
                    191
                )->unique();

                /*
                 * mapped
                 * posted
                 * cancelled
                 */
                $table->string(
                    'status',
                    30
                )->default('mapped');

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->foreign(
                    'rights_royalty_row_id',
                    'rr_alloc_row_fk'
                )
                    ->references('id')
                    ->on('rights_royalty_rows')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'rights_royalty_row_id',
                        'status',
                    ],
                    'rr_alloc_row_status_idx'
                );

                $table->index(
                    [
                        'beneficiary_type',
                        'beneficiary_id',
                    ],
                    'rr_alloc_beneficiary_idx'
                );

                $table->index(
                    'label_id',
                    'rr_alloc_label_idx'
                );

                $table->index(
                    'artist_id',
                    'rr_alloc_artist_idx'
                );

                $table->index(
                    'master_label_id',
                    'rr_alloc_master_idx'
                );

                /*
                 * A source row must never create the same
                 * beneficiary allocation twice.
                 */
                $table->unique(
                    [
                        'rights_royalty_row_id',
                        'beneficiary_type',
                        'beneficiary_id',
                        'allocation_type',
                    ],
                    'rr_alloc_row_beneficiary_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'rights_royalty_allocations'
        );
    }
};
