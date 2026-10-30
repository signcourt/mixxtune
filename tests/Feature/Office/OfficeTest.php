<?php

namespace Tests\Feature\Office;

use App\Models\Office\FunctionalRole;
use App\Models\Office\OfficeTask;
use App\Models\User;
use App\Services\V2\Office\OfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W127 MIXXTUNE_OFFICE (generated test data only) */
class OfficeTest extends TestCase
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

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_full_office_flow_add_staff_assign_work_submit_verify_and_time(): void
    {
        $boss = $this->super();
        $role = FunctionalRole::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'Designer', 'slug' => 'designer-'.Str::random(4)]);

        /* only a Super Admin can add staff */
        $label = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($label)->post('/office/admin/staff', ['name' => 'X', 'email' => 'x@o.test'])->assertForbidden();

        $this->actingAs($boss)->post('/office/admin/staff', ['name' => 'Rahul', 'email' => 'rahul@office.test', 'roles' => [$role->id]])->assertRedirect();
        $staff = User::query()->where('email', 'rahul@office.test')->firstOrFail();
        $this->assertSame('staff', $staff->role);
        $this->assertDatabaseHas('office_staff_roles', ['user_id' => $staff->id, 'functional_role_id' => $role->id]);

        /* give a task with a checklist */
        $this->actingAs($boss)->post('/office/admin/assign', [
            'assigned_to' => $staff->id, 'title' => '3 thumbnails', 'priority' => 'high',
            'checklist' => ['make 3 options', 'add text', 'export 1280x720'],
        ])->assertRedirect();
        $task = OfficeTask::query()->where('assigned_to', $staff->id)->firstOrFail();
        $this->assertCount(3, $task->items);
        $this->assertSame('assigned', $task->status);

        /* staff cannot open the Super Admin office */
        $this->actingAs($staff)->get('/office/admin')->assertForbidden();

        /* the staff My Office page opens and shows the task */
        $this->actingAs($staff)->get('/office')->assertOk()->assertInertia(fn (Assert $p) => $p->component('V2/Office/MyOffice')->has('tasks', 1));

        /* staff day: clock in, accept, timer, checklist, submit */
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        $this->actingAs($staff)->post('/office/clock-in')->assertRedirect();
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/accept")->assertRedirect();
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/timer-start")->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $item = $task->items()->first();
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/items/{$item->id}", ['done' => true])->assertRedirect();
        $this->assertSame(33, $task->fresh()->progress);

        /* 25 minutes pass, then pause -> worked time recorded */
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:25:00', OfficeService::TZ));
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/timer-pause")->assertRedirect();
        $this->assertSame(1500, $task->fresh()->worked_seconds);

        /* another staff member cannot touch this task */
        $other = User::query()->create(['name' => 'Y', 'email' => 'y@office.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
        $this->actingAs($other)->post("/office/tasks/{$task->id}/timer-start")->assertForbidden();

        /* submit with a link */
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/submit", ['kind' => 'link', 'link' => 'https://drive.example/abc'])->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);
        $this->assertDatabaseHas('office_task_proofs', ['task_id' => $task->id, 'link' => 'https://drive.example/abc']);

        /* it shows in the Super Admin review queue; approve it */
        $this->actingAs($boss)->get('/office/admin')->assertInertia(fn (Assert $p) => $p->component('V2/Office/Index')->where('counts.review', 1));
        $this->actingAs($boss)->post("/office/admin/tasks/{$task->id}/verify")->assertRedirect();
        $this->assertSame('verified', $task->fresh()->status);

        /* logout summary + clock out: no logout without a clock-in */
        Carbon::setTestNow(Carbon::parse('2026-10-31 18:40:00', OfficeService::TZ));
        $summary = $this->actingAs($staff)->getJson('/office/summary')->assertOk()->json();
        $this->assertGreaterThan(0, $summary['work_seconds']);
        $this->assertSame(1, $summary['tasks_done']);

        $this->actingAs($staff)->post('/office/clock-out')->assertRedirect();
        $a = DB::table('office_attendance')->where('user_id', $staff->id)->first();
        $this->assertNotNull($a->confirmed_at);
        $this->assertNotNull($a->clock_out_at);

        $noClock = User::query()->create(['name' => 'Z', 'email' => 'z@office.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
        $this->actingAs($noClock)->post('/office/clock-out')->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_break_pauses_active_time_and_reminders_fire_for_overdue(): void
    {
        $boss = $this->super();
        $staff = User::query()->create(['name' => 'R', 'email' => 'r2@office.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
        $office = app(OfficeService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        $office->clockIn($staff);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:01:00', OfficeService::TZ));
        $office->ping($staff); /* +60s active */
        $this->assertSame(60, $office->today($staff)->active_seconds);
        $office->startBreak($staff);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:16:00', OfficeService::TZ));
        $office->ping($staff); /* on break: no active time added */
        $this->assertSame(60, $office->today($staff)->active_seconds);
        $office->endBreak($staff);
        $this->assertSame(900, $office->today($staff)->break_seconds);

        /* an overdue task -> reminder bell + last_reminded_at set */
        $task = $office->createTask($boss, ['assigned_to' => $staff->id, 'title' => 'late one', 'due_at' => Carbon::parse('2026-10-31 09:00:00', OfficeService::TZ)]);
        $this->artisan('office:reminders')->assertSuccessful();
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $staff->id, 'type' => 'office.task_overdue']);
        $this->assertNotNull($task->fresh()->last_reminded_at);
        Carbon::setTestNow();
    }
}
