<?php

namespace Tests\Feature\Support;

use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_SUPPORT_DESK_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class SupportDeskTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()], $extra));
    }

    private function notices(User $user, string $type): int
    {
        return DB::table('panel_notifications')->where('user_id', $user->id)->where('type', $type)->count();
    }

    public function test_full_ticket_flow_with_private_files_and_internal_notes(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->user('label', ['name' => 'Gen Desk Owner']);
        $stranger = $this->user('label');
        $manager = $this->user('admin');
        $super = $this->user('super_admin');

        // new ticket with a file -> every manager / super admin is told
        $this->actingAs($owner)->post('/v2/support', [
            'subject' => 'Gen payout missing', 'category' => 'withdrawal', 'priority' => 'high', 'message' => 'Gen where is my money',
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf')],
        ])->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame('withdrawal', $ticket->category);
        $this->assertSame(1, $this->notices($manager, 'support.new_ticket'));
        $this->assertSame(1, $this->notices($super, 'support.new_ticket'));

        $first = $ticket->messages()->first();
        Storage::disk('local')->assertExists($first->attachments[0]['path']);
        Storage::disk('public')->assertMissing($first->attachments[0]['path']);

        $url = '/v2/support/'.$ticket->id.'/files/'.$first->id.'/0';
        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($manager)->get($url)->assertOk();
        $this->actingAs($stranger)->get($url)->assertForbidden();

        // staff list: needs reply + search + unassigned
        $this->actingAs($manager)->get('/v2/admin/support?status=needs_reply&search=payout')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('tickets.total', 1)->where('counts.unassigned', 1));

        // internal note: never sent to the owner's browser
        $this->actingAs($manager)->post('/v2/admin/support/'.$ticket->id.'/reply', ['message' => 'Gen secret note', 'is_internal' => true])->assertRedirect();
        $this->actingAs($owner)->get('/v2/support/'.$ticket->id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('messages', 1)->where('messages.0.message', 'Gen where is my money'));

        // manager reply with a file -> owner told, ticket given to that manager
        $this->actingAs($manager)->post('/v2/admin/support/'.$ticket->id.'/reply', [
            'message' => 'Gen sent today', 'status' => 'waiting', 'attachments' => [UploadedFile::fake()->create('utr.png', 10, 'image/png')],
        ])->assertRedirect();
        $ticket->refresh();
        $this->assertSame('waiting', $ticket->status);
        $this->assertSame($manager->id, (int) $ticket->assigned_admin_id);
        $this->assertNotEmpty($ticket->messages()->latest('id')->first()->attachments);
        $this->assertSame(1, $this->notices($owner, 'support.admin_reply'));

        // owner replies -> only the assigned manager is told
        $this->actingAs($owner)->post('/v2/support/'.$ticket->id.'/reply', ['message' => 'Gen got it thanks'])->assertRedirect();
        $this->assertSame(1, $this->notices($manager, 'support.customer_reply'));
        $this->assertSame(0, $this->notices($super, 'support.customer_reply'));

        // owner closes, then reopens
        $this->actingAs($owner)->post('/v2/support/'.$ticket->id.'/close')->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->actingAs($owner)->post('/v2/support/'.$ticket->id.'/reopen')->assertRedirect();
        $this->assertSame('customer_reply', $ticket->fresh()->status);

        // a stranger cannot touch it
        $this->actingAs($stranger)->post('/v2/support/'.$ticket->id.'/close')->assertForbidden();
        $this->actingAs($stranger)->get('/v2/admin/support')->assertForbidden();
    }

    public function test_staff_can_open_a_ticket_for_a_user_and_reassign(): void
    {
        $owner = $this->user('artist', ['name' => 'Gen Artist Person']);
        $manager = $this->user('admin');
        $other = $this->user('admin');

        $this->actingAs($manager)->getJson('/v2/admin/support/search-users?q=Gen Artist')->assertJsonPath('users.0.id', $owner->id);

        $this->actingAs($manager)->post('/v2/admin/support', [
            'user_id' => $owner->id, 'subject' => 'Gen wrong ISRC', 'category' => 'release', 'priority' => 'normal', 'message' => 'Gen please confirm',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame([$owner->id, 'waiting'], [(int) $ticket->user_id, $ticket->status]);
        $this->assertSame(1, $this->notices($owner, 'support.admin_opened'));

        $this->actingAs($manager)->patch('/v2/admin/support/'.$ticket->id, [
            'assigned_admin_id' => $other->id, 'priority' => 'urgent', 'status' => 'waiting', 'category' => 'release',
        ])->assertRedirect();

        $this->assertSame($other->id, (int) $ticket->fresh()->assigned_admin_id);
        $this->assertSame(1, $this->notices($other, 'support.assigned'));
        $this->assertTrue($ticket->messages()->where('is_internal', true)->where('message', 'like', '%priority → urgent%')->exists());

        // only staff can be given a ticket
        $this->actingAs($manager)->patch('/v2/admin/support/'.$ticket->id, [
            'assigned_admin_id' => $owner->id, 'priority' => 'urgent', 'status' => 'waiting',
        ])->assertStatus(422);
    }
}
