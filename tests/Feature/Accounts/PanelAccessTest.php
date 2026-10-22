<?php

namespace Tests\Feature\Accounts;

use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W81 MIXXTUNE_PANEL_ACCESS */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createProductionOnlyTables();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function perms(User $user): array
    {
        app()->forgetScopedInstances();

        return app(PermissionService::class)->permissions($user->fresh());
    }

    private function legacy(User $user, array $values): void
    {
        $fields = ['can_view_catalogue', 'can_create_releases', 'can_manage_releases', 'can_view_reports', 'can_view_royalties', 'can_manage_wallet',
            'can_manage_withdrawals', 'can_manage_users', 'can_manage_support', 'can_manage_settings', 'can_manage_delivery', 'can_manage_identifiers'];
        UserPanelPermission::query()->create(array_merge(array_fill_keys($fields, false), ['user_id' => $user->id], $values));
    }

    public function test_standard_access_matches_the_old_role_access_plus_kyc_tab(): void
    {
        $label = $this->user('label');
        $p = $this->perms($label);
        foreach (['releases.view', 'releases.create', 'catalogue.view', 'wallet.view', 'royalties.view', 'reports.view', 'support.create', 'artists.manage', 'team.manage', 'revenue_sharing.manage', 'legal.operations.view', 'profile.view'] as $need) {
            $this->assertContains($need, $p, $need);
        }
        $this->assertNotContains('support.manage', $p);

        $artist = $this->perms($this->user('artist'));
        $this->assertNotContains('team.manage', $artist);
        $this->assertContains('royalties.view', $artist);

        $manager = $this->perms($this->user('admin'));
        $this->assertContains('releases.review', $manager);
        $this->assertContains('identifiers.assign', $manager);
        $this->assertNotContains('wallet.view', $manager);
        $this->assertNotContains('settings.view', $manager);

        $this->assertSame(['*'], $this->perms($this->user('super_admin')));
    }

    public function test_old_unticked_boxes_no_longer_hide_tabs_but_old_extras_stay(): void
    {
        $label = $this->user('label');
        $this->legacy($label, []);
        $this->assertContains('royalties.view', $this->perms($label));
        $this->actingAs($label)->get('/v2/royalties')->assertOk();

        $manager = $this->user('admin');
        $this->legacy($manager, ['can_manage_wallet' => true]);
        $p = $this->perms($manager);
        $this->assertContains('wallet.view', $p);
        $this->assertContains('catalogue.view', $p);
    }

    public function test_role_default_off_blocks_every_account_and_one_account_can_be_allowed(): void
    {
        $super = $this->user('super_admin');
        $a = $this->user('artist');
        $b = $this->user('artist');

        $this->actingAs($a)->get('/v2/panel-access-probe-not-a-route')->assertNotFound();
        $this->actingAs($this->user('label'))->put('/v2/admin/panel-access', ['defaults' => ['artist' => ['royalties' => false]]])->assertForbidden();

        $this->actingAs($super)->put('/v2/admin/panel-access', ['defaults' => ['artist' => ['royalties' => false], 'bogus' => ['x' => true]]])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('panel_role_defaults')->where('role', 'artist')->where('tab', 'royalties')->value('allowed'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'panel.role_defaults_changed']);

        $this->assertNotContains('royalties.view', $this->perms($a));
        $this->actingAs($a)->get('/v2/royalties')->assertForbidden();

        /* one account allowed again from Edit User */
        $this->actingAs($super)->get('/v2/admin/users/'.$b->id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('panelTabs', fn ($rows) => collect($rows)->firstWhere('key', 'royalties')['role_default'] === false
                && collect($rows)->firstWhere('key', 'royalties')['override'] === 'default'));
        $this->assertDatabaseMissing('user_panel_permissions', ['user_id' => $b->id]);

        $this->actingAs($super)->patch('/v2/admin/users/'.$b->id, [
            'name' => $b->name, 'email' => $b->email, 'role' => 'artist', 'account_status' => 'active', 'kyc_status' => 'pending', 'revenue_share_percentage' => 80,
            'tab_overrides' => ['royalties' => 'allow', 'catalogue' => 'block', 'not_a_tab' => 'allow'],
            'tab_overrides_touched' => true,
        ])->assertSessionHasNoErrors();

        $this->assertContains('royalties.view', $this->perms($b));
        $this->assertNotContains('catalogue.view', $this->perms($b));
        $this->actingAs($b)->get('/v2/royalties')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'panel.user_access_changed']);
        $this->assertSame(2, DB::table('panel_access_overrides')->where('user_id', $b->id)->count());
        $this->assertDatabaseMissing('user_panel_permissions', ['user_id' => $b->id]);

        /* back to default */
        $this->actingAs($super)->patch('/v2/admin/users/'.$b->id, [
            'name' => $b->name, 'email' => $b->email, 'role' => 'artist', 'account_status' => 'active', 'kyc_status' => 'pending', 'revenue_share_percentage' => 80,
            'tab_overrides' => ['royalties' => 'default', 'catalogue' => 'default'], 'tab_overrides_touched' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('panel_access_overrides')->where('user_id', $b->id)->count());
        $this->assertNotContains('royalties.view', $this->perms($b));
    }

    public function test_manager_tab_block_stops_the_page_and_page_lists_roles(): void
    {
        $super = $this->user('super_admin');
        $manager = $this->user('admin');
        DB::table('panel_access_overrides')->insert(['user_id' => $manager->id, 'tab' => 'catalogue', 'allowed' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNotContains('catalogue.view', $this->perms($manager));
        $this->actingAs($manager)->get('/v2/catalogue')->assertForbidden();

        $this->actingAs($super)->get('/v2/admin/panel-access')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/PanelAccess/Index')
            ->where('roles.0.role', 'label')
            ->where('roles.2.role', 'admin')
            ->where('roles.2.own_settings', 1)
            ->where('roles.1.tabs', fn ($tabs) => !collect($tabs)->contains('key', 'team')));
        $this->actingAs($manager)->get('/v2/admin/panel-access')->assertForbidden();
    }
}
