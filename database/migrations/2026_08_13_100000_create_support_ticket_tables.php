<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            Schema::create(
                'support_tickets',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->string('public_id', 40)
                        ->unique();

                    $table
                        ->string('ticket_number', 50)
                        ->unique();

                    $table
                        ->foreignId('user_id')
                        ->constrained('users')
                        ->cascadeOnDelete();

                    $table
                        ->unsignedBigInteger('assigned_admin_id')
                        ->nullable();

                    $table->string('subject');

                    $table
                        ->string('category', 50)
                        ->default('general');

                    $table
                        ->string('priority', 30)
                        ->default('normal');

                    $table
                        ->string('status', 30)
                        ->default('open');

                    $table
                        ->timestamp('last_reply_at')
                        ->nullable();

                    $table
                        ->unsignedBigInteger('last_reply_by')
                        ->nullable();

                    $table
                        ->timestamp('closed_at')
                        ->nullable();

                    $table
                        ->unsignedBigInteger('closed_by')
                        ->nullable();

                    $table->timestamps();

                    $table->index(
                        [
                            'assigned_admin_id',
                            'status',
                        ]
                    );

                    $table->index(
                        [
                            'status',
                            'priority',
                        ]
                    );
                }
            );
        }

        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create(
                'support_ticket_messages',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->foreignId('support_ticket_id')
                        ->constrained('support_tickets')
                        ->cascadeOnDelete();

                    $table
                        ->foreignId('user_id')
                        ->constrained('users')
                        ->cascadeOnDelete();

                    $table->longText('message');

                    $table
                        ->boolean('is_internal')
                        ->default(false);

                    $table
                        ->json('attachments')
                        ->nullable();

                    $table->timestamps();

                    $table->index(
                        [
                            'support_ticket_id',
                            'created_at',
                        ]
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'support_ticket_messages'
        );

        Schema::dropIfExists(
            'support_tickets'
        );
    }
};
