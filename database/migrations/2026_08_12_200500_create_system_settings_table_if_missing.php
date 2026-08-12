<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Production already contains this canonical table.
         *
         * Older environments and SQLite test databases may not,
         * because the original production table was created outside
         * the repository migration history.
         */
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create(
            'system_settings',
            function (Blueprint $table) {
                $table->id();

                $table
                    ->string('group', 100)
                    ->default('general');

                $table
                    ->string('key', 190)
                    ->unique(
                        'system_settings_key_unique'
                    );

                $table
                    ->longText('value')
                    ->nullable();

                $table
                    ->string('type', 30)
                    ->default('string');

                $table
                    ->boolean('is_public')
                    ->default(false);

                $table
                    ->text('description')
                    ->nullable();

                /*
                 * Production currently stores the administrator ID
                 * without an FK constraint, so keep schema identical.
                 */
                $table
                    ->unsignedBigInteger('updated_by')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['group', 'key'],
                    'system_settings_group_key_index'
                );
            }
        );
    }

    public function down(): void
    {
        /*
         * Intentionally retained.
         *
         * System configuration is application state and should not
         * be destroyed by an ordinary rollback.
         */
    }
};
