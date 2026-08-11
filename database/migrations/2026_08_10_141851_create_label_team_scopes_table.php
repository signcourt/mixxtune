<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_team_scopes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('label_team_member_id')
                ->constrained('label_team_members')
                ->cascadeOnDelete();

            /*
             * artist | label
             *
             * No recursive hierarchy is created here.
             * Scope must remain inside owner's allowed label boundary.
             */
            $table->string('scope_type', 30);

            $table->unsignedBigInteger('scope_id');

            $table->timestamps();

            $table->unique(
                [
                    'label_team_member_id',
                    'scope_type',
                    'scope_id'
                ],
                'label_team_scope_unique'
            );

            $table->index(
                ['scope_type', 'scope_id'],
                'label_team_scope_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_team_scopes');
    }
};
