<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W127 MIXXTUNE_OFFICE
 * A staff / office system inside Mixx Tune: staff log in, see their tasks, do them, and their
 * attendance (Clock In/Out, break, task timer, time the Mixx Tune tab was active) is recorded.
 * Super Admin gives tasks, reviews the proof, and sees who worked how long.
 * All tables are new (prefix office_). Nothing existing is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('office_functional_roles')) {
            Schema::create('office_functional_roles', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('office_task_templates')) {
            Schema::create('office_task_templates', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('functional_role_id')->nullable()->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->unsignedInteger('sla_minutes')->nullable();
                $table->string('proof_type', 30)->default('none'); /* none | link | file | note */
                $table->json('checklist')->nullable(); /* ["item one","item two"] */
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        /* which functional roles a staff member holds */
        if (!Schema::hasTable('office_staff_roles')) {
            Schema::create('office_staff_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('functional_role_id')->index();
                $table->timestamps();
                $table->unique(['user_id', 'functional_role_id']);
            });
        }

        if (!Schema::hasTable('office_tasks')) {
            Schema::create('office_tasks', function (Blueprint $table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('functional_role_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('release_id')->nullable()->index();
                $table->string('priority', 20)->default('normal'); /* low | normal | high | urgent */
                $table->string('status', 30)->default('assigned'); /* assigned|accepted|in_progress|blocked|submitted|changes_requested|verified|cancelled */
                $table->unsignedTinyInteger('progress')->default(0);
                $table->unsignedInteger('estimated_minutes')->nullable();
                $table->unsignedInteger('worked_seconds')->default(0);
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->text('blocker_note')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('last_reminded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['assigned_to', 'status']);
            });
        }

        if (!Schema::hasTable('office_task_checklist_items')) {
            Schema::create('office_task_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->string('text', 300);
                $table->unsignedInteger('weight')->default(1);
                $table->boolean('is_done')->default(false);
                $table->timestamp('done_at')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('office_task_status_logs')) {
            Schema::create('office_task_status_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('acted_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('office_task_proofs')) {
            Schema::create('office_task_proofs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->string('kind', 20)->default('link'); /* link | file | note */
                $table->string('link', 1000)->nullable();
                $table->string('path', 500)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('office_attendance')) {
            Schema::create('office_attendance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('work_date');
                $table->timestamp('clock_in_at')->nullable();
                $table->timestamp('clock_out_at')->nullable();
                $table->unsignedInteger('active_seconds')->default(0); /* Mixx Tune tab active time */
                $table->unsignedInteger('break_seconds')->default(0);
                $table->unsignedInteger('task_seconds')->default(0);
                $table->unsignedInteger('tasks_done')->default(0);
                $table->unsignedInteger('tasks_submitted')->default(0);
                $table->timestamp('last_ping_at')->nullable();
                $table->boolean('on_break')->default(false);
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'work_date']);
            });
        }

        if (!Schema::hasTable('office_breaks')) {
            Schema::create('office_breaks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_id')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('seconds')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('office_task_timers')) {
            Schema::create('office_task_timers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('seconds')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'office_task_timers', 'office_breaks', 'office_attendance', 'office_task_proofs',
            'office_task_status_logs', 'office_task_checklist_items', 'office_tasks',
            'office_staff_roles', 'office_task_templates', 'office_functional_roles',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
