<?php

namespace Tests\Feature\Legal;

use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W73 MIXXTUNE_LEGAL_OPS_V2 */
class LegalCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function legalCase(User $owner, string $section = 'youtube', array $extra = [])
    {
        return $this->actingAs($owner)->post('/v2/support', array_merge([
            'subject' => 'Video blocked',
            'category' => 'general',
            'priority' => 'high',
            'message' => '[Legal Operations: YouTube Operations] details here',
            'legal_section' => $section,
            'legal_issue_type' => 'Content ID Claim',
            'content_url' => 'https://www.youtube.com/watch?v=abc',
        ], $extra));
    }

    public function test_legal_case_gets_section_subject_and_category_from_server(): void
    {
        $owner = $this->user('artist');

        $this->legalCase($owner, 'youtube', ['category' => 'wallet'])->assertRedirect()->assertSessionHas('success');

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame('Legal · YouTube Operations: Video blocked', $ticket->subject);
        $this->assertSame('copyright', $ticket->category);
    }

    public function test_legal_case_needs_issue_type_and_real_links(): void
    {
        $owner = $this->user('label');

        $this->legalCase($owner, 'spotify', ['legal_issue_type' => '', 'content_url' => 'not a link'])
            ->assertSessionHasErrors(['legal_issue_type', 'content_url']);
        $this->legalCase($owner, 'nonsense')->assertSessionHasErrors(['legal_section']);
        $this->assertSame(0, SupportTicket::query()->count());

        /* a normal ticket still works without any legal fields */
        $this->actingAs($owner)->post('/v2/support', ['subject' => 'Payout late', 'category' => 'withdrawal', 'priority' => 'normal', 'message' => 'Where is it'])
            ->assertRedirect();
        $this->assertSame('Payout late', SupportTicket::query()->value('subject'));
    }

    public function test_case_history_and_hub_only_show_own_legal_cases(): void
    {
        $owner = $this->user('artist');
        $other = $this->user('artist');

        $this->legalCase($owner, 'youtube');
        $this->legalCase($owner, 'rights', ['legal_issue_type' => 'Rights Transfer']);
        $this->legalCase($other, 'youtube');
        $this->actingAs($owner)->post('/v2/support', ['subject' => 'Login issue', 'category' => 'technical', 'priority' => 'normal', 'message' => 'Cannot log in']);

        /* an old-style case (before W73) also counts */
        SupportTicket::query()->create([
            'public_id' => (string) Str::ulid(), 'ticket_number' => 'MT-OLD-1', 'user_id' => $owner->id,
            'subject' => 'Copyright & Claims — Copyright / Claim Issue', 'category' => 'copyright', 'priority' => 'normal',
            'status' => 'closed', 'last_reply_at' => now(),
        ]);

        $this->actingAs($owner)->get('/v2/support?legal_section=history&tab=all')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Support/Index')
            ->where('legal', true)
            ->where('counts.all', 3)
            ->where('counts.closed', 1)
            ->where('tickets.data', fn ($rows) => collect($rows)->pluck('legal_section')->sort()->values()->all() === ['copyright', 'rights', 'youtube']));

        $this->actingAs($owner)->get('/v2/support?tab=all')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('legal', false)
            ->where('counts.all', 4));

        $this->actingAs($owner)->get('/v2/legal-operations')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/LegalOperations/Index')
            ->where('counts.open', 2)
            ->where('counts.closed', 1)
            ->where('counts.sections.youtube', 1)
            ->where('counts.sections.rights', 1)
            ->where('counts.sections.copyright', 0)
            ->has('recent', 3));
    }

    public function test_create_form_offers_only_own_releases(): void
    {
        $owner = $this->user('artist');
        $other = $this->user('artist');
        $mk = fn (User $u) => DB::table('artists')->insertGetId([
            'public_id' => 'ART-'.strtoupper(substr((string) Str::ulid(), -12)), 'user_id' => $u->id,
            'stage_name' => 'A'.$u->id, 'legal_name' => 'A'.$u->id, 'slug' => 'a-'.$u->id.'-'.Str::lower(Str::random(4)),
            'email' => 'a'.$u->id.Str::lower(Str::random(4)).'@example.test', 'country' => 'India', 'timezone' => 'Asia/Kolkata',
            'currency' => 'INR', 'account_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mine = $mk($owner);
        $theirs = $mk($other);
        \App\Models\Distribution\Release::factory()->create(['artist_id' => $mine, 'title' => 'My Own Song']);
        \App\Models\Distribution\Release::factory()->create(['artist_id' => $theirs, 'title' => 'Not Mine']);

        $this->actingAs($owner)->get('/v2/support/create?legal_section=copyright')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('legalSection', 'copyright')
            ->where('catalogue', fn ($rows) => collect($rows)->pluck('title')->all() === ['My Own Song']));

        $this->actingAs($owner)->get('/v2/support/create?legal_section=bogus')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('legalSection', null)
            ->where('catalogue', []));
    }

    public function test_staff_desk_can_filter_legal_cases(): void
    {
        $owner = $this->user('label');
        $super = $this->user('super_admin');
        $this->legalCase($owner, 'dsp', ['legal_issue_type' => 'Delivery Issue']);
        $this->actingAs($owner)->post('/v2/support', ['subject' => 'Payout late', 'category' => 'withdrawal', 'priority' => 'normal', 'message' => 'Where is it']);

        $this->actingAs($super)->get('/v2/admin/support?status=all&category=legal')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('tickets.total', 1)
            ->where('tickets.data.0.subject', 'Legal · Other DSP Operations: Video blocked'));
    }
}
