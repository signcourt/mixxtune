<?php

namespace Tests\Feature\Office;

use App\Models\Office\OfficeTask;
use App\Models\User;
use App\Services\V2\Office\OfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W129 MIXXTUNE_OFFICE_LOGIN (generated test data only) */
class OfficeLoginRulesTest extends TestCase
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

    private function staff(): User
    {
        return User::query()->create(['name' => 'S', 'email' => 's@o.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
    }

    public function test_staff_can_only_use_my_office(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get('/office')->assertOk();
        /* any other panel page bounces to My Office */
        $this->actingAs($staff)->get('/super-admin/dashboard')->assertRedirect('/office');
        $this->actingAs($staff)->get('/releases')->assertRedirect('/office');
        $this->actingAs($staff)->get('/mail')->assertRedirect('/office');
    }

    public function test_work_needs_clock_in(): void
    {
        $boss = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $staff = $this->staff();
        $task = app(OfficeService::class)->createTask($boss, ['assigned_to' => $staff->id, 'title' => 'x', 'checklist' => ['a']]);

        /* not clocked in -> task actions are refused */
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/timer-start")->assertStatus(422);
        $item = $task->items()->first();
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/items/{$item->id}", ['done' => true])->assertStatus(422);

        /* clock in -> now it works */
        app(OfficeService::class)->clockIn($staff);
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/timer-start")->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        /* clock out -> work is blocked again */
        app(OfficeService::class)->clockOut($staff);
        $this->actingAs($staff)->post("/office/tasks/{$task->id}/timer-start")->assertStatus(422);
    }

    public function test_absent_shows_when_no_login_today(): void
    {
        $boss = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $a = $this->staff();
        $b = User::query()->create(['name' => 'B', 'email' => 'b@o.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        app(OfficeService::class)->clockIn($a); /* only A logs in */

        $this->actingAs($boss)->get('/office/admin')->assertInertia(fn (Assert $p) => $p->component('V2/Office/Index')->where('counts.absent', 1));
        Carbon::setTestNow();
    }
}
