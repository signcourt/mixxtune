<?php

namespace Tests\Feature\Office;

use App\Models\Office\OfficeTask;
use App\Models\User;
use App\Services\V2\Office\OfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/* W130 MIXXTUNE_OFFICE notifications (generated test data only) */
class OfficeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($t) {
                $t->id();
                $t->string('public_id', 40)->unique();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action', 100);
                $t->string('module', 100)->nullable();
                $t->string('auditable_type')->nullable();
                $t->unsignedBigInteger('auditable_id')->nullable();
                $t->text('description')->nullable();
                $t->json('old_values')->nullable();
                $t->json('new_values')->nullable();
                $t->string('ip_address', 64)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('request_method', 10)->nullable();
                $t->text('request_url')->nullable();
                $t->timestamps();
            });
        }
    }

    public function test_submit_and_block_ring_super_admins(): void
    {
        $boss = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $staff = User::query()->create(['name' => 'Rahul', 'email' => 's@o.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
        $office = app(OfficeService::class);
        $office->clockIn($staff);
        $t1 = $office->createTask($boss, ['assigned_to' => $staff->id, 'title' => 'A', 'checklist' => ['x']]);
        $t2 = $office->createTask($boss, ['assigned_to' => $staff->id, 'title' => 'B']);

        $this->actingAs($staff)->post("/office/tasks/{$t1->id}/submit", ['kind' => 'note', 'note' => 'done'])->assertRedirect();
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $boss->id, 'type' => 'office.task_submitted']);

        $this->actingAs($staff)->post("/office/tasks/{$t2->id}/block", ['why' => 'need audio'])->assertRedirect();
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $boss->id, 'type' => 'office.task_blocked']);
    }

    public function test_clocked_in_staff_with_no_task_alerts_super_admin_once_a_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        $boss = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $staff = User::query()->create(['name' => 'Free Guy', 'email' => 'f@o.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
        app(OfficeService::class)->clockIn($staff); /* clocked in, no task */

        $this->artisan('office:reminders')->assertSuccessful();
        $this->assertSame(1, DB::table('panel_notifications')->where('user_id', $boss->id)->where('type', 'office.no_task')->count());

        /* run again same day -> no duplicate */
        $this->artisan('office:reminders')->assertSuccessful();
        $this->assertSame(1, DB::table('panel_notifications')->where('user_id', $boss->id)->where('type', 'office.no_task')->count());

        /* give a task -> next run does not alert */
        app(OfficeService::class)->createTask($boss, ['assigned_to' => $staff->id, 'title' => 'now busy']);
        DB::table('panel_notifications')->where('type', 'office.no_task')->delete();
        $this->artisan('office:reminders')->assertSuccessful();
        $this->assertSame(0, DB::table('panel_notifications')->where('type', 'office.no_task')->count());
        Carbon::setTestNow();
    }
}
