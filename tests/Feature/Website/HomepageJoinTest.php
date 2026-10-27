<?php

namespace Tests\Feature\Website;

use App\Mail\StaffAlertMail;
use App\Models\User;
use App\Services\V2\Website\HomepageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W121 MIXXTUNE_WEBSITE_HOMEPAGE + MIXXTUNE_WEBSITE_JOIN (generated test data only) */
class HomepageJoinTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createProductionOnlyTables();
        Mail::fake();
        $this->travelTo(now('UTC')->setDate(2026, 10, 27)->setTime(6, 0));
    }

    private function user(string $role, string $name = 'Test'): User
    {
        return User::factory()->create(['role' => $role, 'name' => $name, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function answers(array $extra = []): array
    {
        return ['answers' => array_merge([
            'name' => 'Aman Verma',
            'phone' => '98765 43210',
            'email' => 'aman@example.test',
            'role' => 'Artist',
            'brand' => 'Aman Verma Music',
            'city' => 'Jaipur',
            'message' => '12 songs ready',
        ], $extra)];
    }

    public function test_homepage_shows_the_galaxy_page_with_the_standard_words(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Public/Home')
            ->where('content.hero.title', "Deliver your music\ninto every orbit.")
            ->where('content.join.fields.0.id', 'name')
            ->where('content.join.fields.1.id', 'phone')
            ->where('content.sections.royalty', true)
            ->where('content.contact.email', ''));
    }

    public function test_join_form_saves_the_request_and_tells_every_super_admin(): void
    {
        $boss = $this->user('super_admin', 'Boss');
        $this->user('label', 'Some Label');

        $this->from('/')->post('/join', $this->answers())->assertRedirect('/')->assertSessionHas('success');

        $row = DB::table('website_join_requests')->first();
        $this->assertSame(['Aman Verma', '98765 43210', 'Artist', 'Jaipur', 'new'], [$row->name, $row->phone, $row->role, $row->city, $row->status]);
        $answers = json_decode($row->answers, true);
        $this->assertSame('Full name', $answers[0]['label']);
        $this->assertSame('12 songs ready', collect($answers)->firstWhere('id', 'message')['value']);
        $this->assertSame(1, DB::table('website_join_request_notes')->where('join_request_id', $row->id)->count());

        $this->assertSame(1, DB::table('panel_notifications')->count());
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $boss->id, 'type' => 'join.request', 'action_url' => '/admin/join-requests?open='.$row->id]);
        Mail::assertSent(StaffAlertMail::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'join_request.created']);

        /* the same phone again a minute later (double click) is not saved twice */
        $this->travel(1)->minutes();
        $this->from('/')->post('/join', $this->answers())->assertSessionHas('success');
        $this->assertSame(1, DB::table('website_join_requests')->count());
    }

    public function test_join_form_checks_the_answers_and_ignores_robots(): void
    {
        $this->from('/')->post('/join', ['answers' => ['name' => '', 'phone' => 'abc', 'role' => 'Astronaut']])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['answers.name', 'answers.phone', 'answers.role']);
        $this->assertSame(0, DB::table('website_join_requests')->count());

        /* the hidden box was filled in: looks fine to the robot, nothing is kept */
        $this->from('/')->post('/join', $this->answers() + ['website' => 'http://spam.test'])->assertSessionHas('success');
        $this->assertSame(0, DB::table('website_join_requests')->count());
        $this->assertSame(0, DB::table('panel_notifications')->count());
    }

    public function test_too_many_tries_are_told_in_the_form_and_odd_input_does_not_break(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->from('/')->post('/join', ['answers' => ['name' => '']])->assertSessionHasErrors('answers.name');
        }
        $this->from('/')->post('/join', $this->answers())->assertRedirect('/')->assertSessionHasErrors('join');
        $this->assertSame(0, DB::table('website_join_requests')->count());

        $this->travel(2)->minutes();
        $this->from('/')->post('/join', ['website' => ['x']])->assertSessionHas('success');
        $this->from('/')->post('/join', ['answers' => 'x'])->assertSessionHasErrors('join');
        $this->from('/')->post('/join', $this->answers(['phone' => '9876543210']))->assertSessionHasNoErrors();
        /* the same number typed with a space and +91 a minute later is the same person */
        $this->travel(1)->minutes();
        $this->from('/')->post('/join', $this->answers(['phone' => '+91 98765 43210']))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('website_join_requests')->count());

        $boss = $this->user('super_admin');
        $this->actingAs($boss)->get('/admin/join-requests?tab[]=x&q[]=y&open[]=1')->assertOk()->assertInertia(fn (Assert $p) => $p->where('tab', 'new')->where('q', ''));
    }

    public function test_super_admin_customises_the_join_form_and_the_homepage(): void
    {
        $boss = $this->user('super_admin', 'Boss');

        $content = HomepageContent::defaults();
        $content['hero']['title'] = 'Your music, everywhere';
        $content['contact']['email'] = 'hello@mixxtune.test';
        $content['sections']['royalty'] = false;
        $content['join']['notify_email'] = false;
        /* name removed (it comes back), email hidden, a new required dropdown */
        $content['join']['fields'] = [
            ['id' => 'phone', 'label' => 'Mobile', 'type' => 'text', 'required' => false, 'show' => false, 'options' => [], 'hint' => ''],
            ['id' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'show' => false, 'options' => [], 'hint' => ''],
            ['id' => 'q_songs', 'label' => 'How many songs are ready?', 'type' => 'select', 'required' => true, 'show' => true, 'options' => ['1-5', '6-20', 'More than 20'], 'hint' => ''],
        ];

        $this->actingAs($boss)->post('/admin/website', ['content' => $content])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['action' => 'website.homepage_published']);

        $saved = app(HomepageContent::class)->get();
        $this->assertSame(['name', 'phone', 'email', 'q_songs'], array_column($saved['join']['fields'], 'id'));
        $this->assertSame(['phone', true, true], [$saved['join']['fields'][1]['type'], $saved['join']['fields'][1]['required'], $saved['join']['fields'][1]['show']]);
        $this->assertFalse($saved['sections']['royalty']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('content.hero.title', 'Your music, everywhere')
            ->where('content.contact.email', 'hello@mixxtune.test')
            ->where('content.join.fields.3.label', 'How many songs are ready?'));

        /* the new question is now required on the website */
        auth()->logout();
        $this->from('/')->post('/join', ['answers' => ['name' => 'Kiran', 'phone' => '+91 99887 76655']])->assertSessionHasErrors('answers.q_songs');
        $this->from('/')->post('/join', ['answers' => ['name' => 'Kiran', 'phone' => '+91 99887 76655', 'q_songs' => '6-20', 'email' => 'not shown']])->assertSessionHasNoErrors();

        $row = DB::table('website_join_requests')->first();
        $this->assertNull($row->email);
        $this->assertSame('6-20', collect(json_decode($row->answers, true))->firstWhere('id', 'q_songs')['value']);
        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('panel_notifications')->count());
    }

    public function test_super_admin_follows_up_a_request(): void
    {
        $boss = $this->user('super_admin', 'Boss');
        $this->from('/')->post('/join', $this->answers());
        $id = DB::table('website_join_requests')->value('id');

        $this->actingAs($boss)->get('/admin/join-requests?open='.$id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Website/JoinRequests')
            ->where('rows.0.name', 'Aman Verma')
            ->where('open.whatsapp', 'https://wa.me/919876543210')
            ->where('open.tel', '9876543210')
            ->where('tabs.1.key', 'new')
            ->where('tabs.1.count', 1));

        /* called, follow up tomorrow 11:00 India time */
        $this->actingAs($boss)->post("/admin/join-requests/{$id}", ['status' => 'called', 'next_follow_up_at' => '2026-10-28T11:00', 'handled_by' => $boss->id])
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Saved.');
        $row = DB::table('website_join_requests')->find($id);
        $this->assertSame(['called', '2026-10-28 05:30:00', $boss->id], [$row->status, \Illuminate\Support\Carbon::parse($row->next_follow_up_at)->format('Y-m-d H:i:s'), (int) $row->handled_by]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'join_request.updated']);

        $this->actingAs($boss)->post("/admin/join-requests/{$id}/notes", ['body' => 'Will send the song list tomorrow'])->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('website_join_request_notes')->where('join_request_id', $id)->count());

        /* tomorrow it is in "Follow-up today" */
        $this->travelTo(now('UTC')->setDate(2026, 10, 28)->setTime(4, 0));
        $this->actingAs($boss)->get('/admin/join-requests?tab=today')->assertInertia(fn (Assert $p) => $p
            ->where('tab', 'today')
            ->where('rows.0.id', $id)
            ->where('rows.0.follow_due', true));

        $this->actingAs($boss)->post("/admin/join-requests/{$id}", ['status' => 'astronaut'])->assertSessionHasErrors('status');
    }

    public function test_only_a_super_admin_can_open_the_website_pages(): void
    {
        foreach (['admin', 'label', 'artist'] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get('/admin/website')->assertForbidden();
            $this->actingAs($u)->get('/admin/join-requests')->assertForbidden();
            $this->actingAs($u)->post('/admin/website', ['content' => HomepageContent::defaults()])->assertForbidden();
        }
        auth()->logout();
        $this->get('/admin/join-requests')->assertRedirect();

        $boss = $this->user('super_admin');
        $this->actingAs($boss)->get('/admin/website')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Website/Homepage')
            ->where('locked', ['name', 'phone']));
    }
}
