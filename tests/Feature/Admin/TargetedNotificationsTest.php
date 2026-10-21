<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_NOTIFY_TARGETED_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class TargetedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()], $extra));
    }

    private function send(User $super, array $payload)
    {
        return $this->actingAs($super)->post('/v2/admin/notification-management', array_merge([
            'title' => 'Gen notice', 'message' => 'Gen body', 'category' => 'announcement', 'severity' => 'info',
        ], $payload));
    }

    private function inbox(User $user): int
    {
        return DB::table('panel_notifications')->where('user_id', $user->id)->count();
    }

    public function test_targets_reach_exactly_the_right_people(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label', ['name' => 'Gen Owner']);
        $team = $this->user('label');
        $otherOwner = $this->user('label');
        $artistUser = $this->user('artist');
        $manager = $this->user('admin');
        $blocked = $this->user('artist', ['account_status' => 'suspended']);

        $label = Label::factory()->create(['created_by' => $super->id, 'user_id' => $owner->id, 'name' => 'Gen Target Records']);
        Label::factory()->create(['created_by' => $super->id, 'user_id' => $otherOwner->id]);
        DB::table('label_team_members')->insert([
            'label_id' => $label->id, 'user_id' => $team->id, 'created_by' => $super->id, 'permission_level' => 'viewer',
            'scope_level' => 'label', 'status' => 'active', 'permissions' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'user_id' => $artistUser->id]);

        $this->actingAs($super)->get('/v2/admin/notification-management')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Notifications/Index')->where('roleCounts.admin', 1));

        // one label: owner + team only
        $this->actingAs($super)->postJson('/v2/admin/notification-management/preview', ['target_type' => 'label', 'target_value' => (string) $label->id])
            ->assertOk()->assertJsonPath('count', 2);
        $this->send($super, ['target_type' => 'label', 'target_value' => (string) $label->id, 'highlight' => true])->assertSessionHasNoErrors();
        $this->assertSame([1, 1, 0, 0], [$this->inbox($owner), $this->inbox($team), $this->inbox($otherOwner), $this->inbox($artistUser)]);

        // label + its artists
        $this->send($super, ['target_type' => 'label', 'target_value' => (string) $label->id, 'include_label_artists' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inbox($artistUser));

        // chosen people (a manager + a suspended account -> only the active one)
        $this->send($super, ['target_type' => 'users', 'target_users' => [$manager->id, $blocked->id]])->assertSessionHasNoErrors();
        $this->assertSame([1, 0], [$this->inbox($manager), $this->inbox($blocked)]);

        // all managers
        $this->send($super, ['target_type' => 'role', 'target_value' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->inbox($manager));

        // nothing chosen -> clear error, nothing sent
        $before = DB::table('panel_notifications')->count();
        $this->send($super, ['target_type' => 'users', 'target_users' => []])->assertSessionHasErrors('target_value');
        $this->send($super, ['target_type' => 'all', 'action_url' => 'javascript:alert(1)'])->assertSessionHasErrors('action_url');
        $this->assertSame($before, DB::table('panel_notifications')->count());

        // history + who opened
        $this->assertSame(4, DB::table('audit_logs')->where('action', 'notification.broadcast_sent')->count());
        $broadcast = json_decode(DB::table('panel_notifications')->where('user_id', $owner->id)->orderBy('id')->value('data'), true)['broadcast_id'];
        $this->actingAs($super)->getJson('/v2/admin/notification-management/'.$broadcast.'/recipients')
            ->assertOk()->assertJsonCount(2, 'recipients');

        // search
        $this->actingAs($super)->getJson('/v2/admin/notification-management/search-users?q=Gen Own')->assertJsonPath('users.0.id', $owner->id);
        $this->actingAs($super)->getJson('/v2/admin/notification-management/search-labels?q=Target')->assertJsonPath('labels.0.id', $label->id);

        // only super admin
        $this->actingAs($manager)->postJson('/v2/admin/notification-management/preview', ['target_type' => 'all'])->assertForbidden();
        $this->actingAs($manager)->getJson('/v2/admin/notification-management/search-users?q=Gen')->assertForbidden();
    }

    public function test_highlighted_notice_shows_as_banner_until_opened_or_hidden(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');

        $this->send($super, ['target_type' => 'users', 'target_users' => [$owner->id], 'highlight' => true, 'action_url' => '/v2/statements', 'title' => 'Gen banner'])
            ->assertSessionHasNoErrors();

        $id = DB::table('panel_notifications')->where('user_id', $owner->id)->value('id');

        $this->actingAs($owner)->get('/v2/notifications')
            ->assertInertia(fn (Assert $p) => $p->where('highlightNotice.title', 'Gen banner'));

        // Hide: marked read, stays on the page
        $this->actingAs($owner)->patch('/v2/notifications/'.$id.'/read?stay=1')->assertRedirect();
        $this->assertNotNull(DB::table('panel_notifications')->where('id', $id)->value('read_at'));

        $this->actingAs($owner)->get('/v2/notifications')
            ->assertInertia(fn (Assert $p) => $p->where('highlightNotice', null));
    }
}
