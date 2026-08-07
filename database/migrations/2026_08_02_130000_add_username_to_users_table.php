<?php

use App\Services\V2\UsernameService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table
                    ->string('username', 40)
                    ->nullable()
                    ->after('name');

                $table->unique(
                    'username',
                    'users_username_unique'
                );
            });
        }

        $service = app(UsernameService::class);

        DB::table('users')
            ->where(function ($query) {
                $query
                    ->whereNull('username')
                    ->orWhere('username', '');
            })
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'email',
            ])
            ->each(function ($user) use ($service) {
                $username = $service->generate(
                    (string) $user->name,
                    (string) $user->email,
                    (int) $user->id
                );

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'username' => $username,
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(
                    'users_username_unique'
                );

                $table->dropColumn(
                    'username'
                );
            });
        }
    }
};
