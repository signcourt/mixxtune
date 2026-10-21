<?php

namespace Tests\Feature\Accounts;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_MANAGERS_V2 (W60) */
class ManagersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role, array $x = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()], $x));
    }

    private function assign(User $m, Label $l, bool $edit = true): void
    {
        DB::table('admin_label_assignments')->insert([
            'user_id' => $m->id, 'label_id' => $l->id, 'assignment_role' => 'manager', 'can_view' => true, 'can_edit' => $edit,
            'can_manage_releases' => true, 'can_manage_team' => false, 'can_manage_splits' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_list_has_search_counts_and_labels_without_manager(): void
    {
        $super = $this->user('super_admin');
        $kapil = $this->user('admin', ['name' => 'Kapil Koli']);
        $this->user('admin', ['name' => 'Test Manager']);
        $a = Label::factory()->create();
        Label::factory()->create();
        $this->assign($kapil, $a);

        $this->actingAs($super)->get('/v2/admin/admins?q=kapil')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Admins/Index')
            ->has('admins.data', 1)
            ->where('admins.data.0.assigned_labels_count', 1)
            ->where('totals.managers', 2)
            ->where('totals.labels_without_manager', 1));
    }

    public function test_manage_page_shows_other_managers_and_save_keeps_flags(): void
    {
        $super = $this->user('super_admin');
        $m1 = $this->user('admin');
        $m2 = $this->user('admin', ['name' => 'Other One']);
        $a = Label::factory()->create();
        $b = Label::factory()->create();
        $this->assign($m1, $a, false);
        $this->assign($m2, $b);

        $this->actingAs($super)->get('/v2/admin/admins/'.$m1->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('assignedLabelIds', [$a->id])
            ->where('labels', fn ($rows) => collect($rows)->firstWhere('id', $b->id)['others'][0] === 'Other One'));

        $this->actingAs($super)->patch('/v2/admin/admins/'.$m1->id.'/assignments', ['label_ids' => [$a->id, $b->id], 'artist_ids' => []])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'labels +1 / -0'));

        $this->assertSame(0, (int) DB::table('admin_label_assignments')->where('user_id', $m1->id)->where('label_id', $b->id)->value('can_edit'));
        $this->assertSame(2, DB::table('admin_label_assignments')->where('user_id', $m1->id)->count());
    }
}
