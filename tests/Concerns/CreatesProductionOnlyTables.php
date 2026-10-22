<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Schema;

/*
 * W80: tables that production has from database/schema/mysql-schema.sql but
 * no migration creates (the test database is built from migrations only).
 */
trait CreatesProductionOnlyTables
{
    protected function createProductionOnlyTables(): void
    {
        if (!Schema::hasTable('user_panel_permissions')) {
            Schema::create('user_panel_permissions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                foreach (['can_view_catalogue', 'can_create_releases', 'can_manage_releases', 'can_view_reports', 'can_view_royalties', 'can_manage_wallet', 'can_manage_withdrawals', 'can_manage_users', 'can_manage_support', 'can_manage_settings', 'can_manage_delivery', 'can_manage_identifiers'] as $column) {
                    $table->boolean($column)->default(false);
                }
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
            });
        }
    }
}
