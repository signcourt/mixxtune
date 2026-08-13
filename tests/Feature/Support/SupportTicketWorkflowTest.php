<?php

namespace Tests\Feature\Support;

use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'artist'): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createTicket(
        User $user,
        string $subject = 'Royalty report issue'
    ): SupportTicket {
        $this->actingAs($user)
            ->post(route('v2.support.store'), [
                'subject' => $subject,
                'category' => 'royalty',
                'priority' => 'high',
                'message' => 'Please check my royalty report.',
            ])
            ->assertRedirect();

        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('subject', $subject)
            ->firstOrFail();
    }

    public function test_artist_can_create_support_ticket(): void
    {
        $artist = $this->user('artist');

        $ticket = $this->createTicket($artist);

        $this->assertSame('open', $ticket->status);
        $this->assertSame('royalty', $ticket->category);
        $this->assertSame('high', $ticket->priority);

        $this->assertStringStartsWith(
            'TKT-',
            $ticket->ticket_number
        );

        $this->assertDatabaseHas(
            'support_ticket_messages',
            [
                'support_ticket_id' => $ticket->id,
                'user_id' => $artist->id,
                'message' =>
                    'Please check my royalty report.',
            ]
        );
    }

    public function test_label_can_create_support_ticket(): void
    {
        $label = $this->user('label');

        $ticket = $this->createTicket(
            $label,
            'Label catalogue issue'
        );

        $this->assertSame(
            $label->id,
            $ticket->user_id
        );

        $this->assertSame(
            'open',
            $ticket->status
        );
    }

    public function test_user_can_view_own_ticket_but_not_another_users_ticket(): void
    {
        $owner = $this->user('artist');
        $other = $this->user('artist');

        $ticket = $this->createTicket($owner);

        $this->actingAs($owner)
            ->get(
                route(
                    'v2.support.show',
                    $ticket
                )
            )
            ->assertOk();

        $this->actingAs($other)
            ->get(
                route(
                    'v2.support.show',
                    $ticket
                )
            )
            ->assertForbidden();
    }

    public function test_customer_reply_sets_customer_reply_status(): void
    {
        $artist = $this->user('artist');
        $ticket = $this->createTicket($artist);

        $this->actingAs($artist)
            ->post(
                route(
                    'v2.support.reply',
                    $ticket
                ),
                [
                    'message' =>
                        'Here is additional information.',
                ]
            )
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(
            'customer_reply',
            $ticket->status
        );

        $this->assertSame(
            $artist->id,
            $ticket->last_reply_by
        );

        $this->assertDatabaseHas(
            'support_ticket_messages',
            [
                'support_ticket_id' =>
                    $ticket->id,

                'user_id' =>
                    $artist->id,

                'message' =>
                    'Here is additional information.',
            ]
        );
    }

    public function test_customer_cannot_reply_to_closed_ticket(): void
    {
        $artist = $this->user('artist');
        $ticket = $this->createTicket($artist);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->actingAs($artist)
            ->post(
                route(
                    'v2.support.reply',
                    $ticket
                ),
                [
                    'message' =>
                        'Reply after closure.',
                ]
            )
            ->assertStatus(422);

        $this->assertDatabaseMissing(
            'support_ticket_messages',
            [
                'support_ticket_id' =>
                    $ticket->id,

                'message' =>
                    'Reply after closure.',
            ]
        );
    }

    public function test_admin_can_reply_to_ticket_and_customer_is_notified(): void
    {
        $artist = $this->user('artist');
        $admin = $this->user('admin');

        $ticket = $this->createTicket($artist);

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.support.reply',
                    $ticket
                ),
                [
                    'message' =>
                        'We are checking your report.',
                ]
            )
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(
            'admin_reply',
            $ticket->status
        );

        $this->assertSame(
            $admin->id,
            $ticket->last_reply_by
        );

        $this->assertDatabaseHas(
            'support_ticket_messages',
            [
                'support_ticket_id' =>
                    $ticket->id,

                'user_id' =>
                    $admin->id,

                'message' =>
                    'We are checking your report.',

                'is_internal' =>
                    0,
            ]
        );

        $this->assertDatabaseHas(
            'panel_notifications',
            [
                'user_id' => $artist->id,
                'type' => 'support.admin_reply',
            ]
        );
    }

    public function test_super_admin_can_manage_support_ticket(): void
    {
        $artist = $this->user('artist');
        $superAdmin = $this->user('super_admin');

        $ticket = $this->createTicket($artist);

        $this->actingAs($superAdmin)
            ->patch(
                route(
                    'v2.admin.support.update',
                    $ticket
                ),
                [
                    'assigned_admin_id' =>
                        $superAdmin->id,

                    'priority' =>
                        'urgent',

                    'status' =>
                        'waiting',
                ]
            )
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(
            $superAdmin->id,
            $ticket->assigned_admin_id
        );

        $this->assertSame(
            'urgent',
            $ticket->priority
        );

        $this->assertSame(
            'waiting',
            $ticket->status
        );

        $this->assertDatabaseHas(
            'panel_notifications',
            [
                'user_id' => $artist->id,
                'type' => 'support.status_updated',
            ]
        );
    }

    public function test_internal_admin_note_does_not_notify_customer(): void
    {
        $artist = $this->user('artist');
        $admin = $this->user('admin');

        $ticket = $this->createTicket($artist);

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.support.reply',
                    $ticket
                ),
                [
                    'message' =>
                        'Internal investigation note.',

                    'is_internal' =>
                        true,
                ]
            )
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(
            'open',
            $ticket->status
        );

        $this->assertDatabaseHas(
            'support_ticket_messages',
            [
                'support_ticket_id' =>
                    $ticket->id,

                'user_id' =>
                    $admin->id,

                'message' =>
                    'Internal investigation note.',

                'is_internal' =>
                    1,
            ]
        );

        $this->assertDatabaseMissing(
            'panel_notifications',
            [
                'user_id' => $artist->id,
                'type' => 'support.admin_reply',
            ]
        );
    }

    public function test_admin_can_close_ticket(): void
    {
        $artist = $this->user('artist');
        $admin = $this->user('admin');

        $ticket = $this->createTicket($artist);

        $this->actingAs($admin)
            ->patch(
                route(
                    'v2.admin.support.update',
                    $ticket
                ),
                [
                    'assigned_admin_id' =>
                        $admin->id,

                    'priority' =>
                        'normal',

                    'status' =>
                        'closed',
                ]
            )
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(
            'closed',
            $ticket->status
        );

        $this->assertNotNull(
            $ticket->closed_at
        );

        $this->assertSame(
            $admin->id,
            $ticket->closed_by
        );
    }

    public function test_artist_cannot_use_admin_support_actions(): void
    {
        $owner = $this->user('artist');
        $otherArtist = $this->user('artist');

        $ticket = $this->createTicket($owner);

        $this->actingAs($otherArtist)
            ->post(
                route(
                    'v2.admin.support.reply',
                    $ticket
                ),
                [
                    'message' =>
                        'Unauthorized admin reply.',
                ]
            )
            ->assertForbidden();

        $this->actingAs($otherArtist)
            ->patch(
                route(
                    'v2.admin.support.update',
                    $ticket
                ),
                [
                    'assigned_admin_id' => null,
                    'priority' => 'low',
                    'status' => 'closed',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'open',
            $ticket->fresh()->status
        );
    }
}
