<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_team_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * standard:
             * operational user with restricted critical actions.
             *
             * advanced:
             * may receive advanced permissions including release submit.
             */
            $table->string('permission_level', 30)
                ->default('standard');

            /*
             * entire_label:
             * all catalogue belonging to this label's permitted boundary.
             *
             * selected:
             * only explicitly assigned artists / child labels.
             */
            $table->string('scope_level', 30)
                ->default('entire_label');

            $table->string('status', 30)
                ->default('active');

            $table->json('permissions')
                ->nullable();

            $table->timestamp('last_access_at')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['label_id', 'user_id'],
                'label_team_member_unique'
            );

            $table->index(
                ['label_id', 'status'],
                'label_team_member_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_team_members');
    }
};
