<?php

namespace Tests\Feature\Accounts;

use App\Models\Core\AccountAgreement;
use App\Models\User;
use App\Services\V2\AccountAgreements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W94 MIXXTUNE_ACCOUNT_AGREEMENTS (generated test data only) */
class AccountAgreementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 23)->setTime(9, 0));

        $this->createProductionOnlyTables();
    }

    private function user(string $role, string $name = 'Test Account'): User
    {
        return User::factory()->create(['role' => $role, 'name' => $name, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_end_date_is_worked_out_from_signed_date_and_term(): void
    {
        $this->assertSame('2029-10-22', AccountAgreements::endsOn('2026-10-23', 36)->toDateString());
        $this->assertSame('2030-10-22', AccountAgreements::endsOn('2026-10-23', 48)->toDateString());
        $this->assertSame('2031-10-22', AccountAgreements::endsOn('2026-10-23', 60)->toDateString());
        $this->assertSame('2027-04-30', AccountAgreements::endsOn('2026-05-01', 12)->toDateString());
        $this->assertSame('2026-02-27', AccountAgreements::endsOn('2026-01-31', 1)->toDateString());
    }

    public function test_super_admin_adds_edits_renews_and_ends_an_agreement(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label', 'Sanatan Records');

        /* add */
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement", [
            'signed_on' => '2026-10-23', 'term_months' => 36, 'notes' => 'exclusive',
            'document' => UploadedFile::fake()->create('agreement.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $a = AccountAgreement::query()->firstOrFail();
        $this->assertSame(['2029-10-22', 'active', 90], [$a->ends_on->toDateString(), $a->status, $a->notice_days]);
        Storage::disk('local')->assertExists($a->document_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agreement.created']);

        /* a second one is refused - use edit / renew */
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 12])->assertSessionHasErrors('agreement');

        /* edit: 5 years */
        $this->actingAs($super)->post("/admin/agreements/{$a->id}", ['signed_on' => '2026-10-23', 'term_months' => 60])->assertSessionHasNoErrors();
        $this->assertSame('2031-10-22', $a->fresh()->ends_on->toDateString());
        Storage::disk('local')->assertExists($a->fresh()->document_path);

        /* signed copy download */
        $this->actingAs($super)->get("/admin/agreements/{$a->id}/document")->assertOk()->assertDownload('agreement.pdf');

        /* edit page shows it */
        $this->actingAs($super)->get("/admin/users/{$owner->id}/edit")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('agreement.current.ends_on', '2031-10-22')
            ->where('agreement.current.term', '5 years')
            ->where('agreement.current.has_document', true));

        /* renew: old one kept as history */
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement/renew", ['signed_on' => '2031-10-23', 'term_months' => 36])->assertSessionHasNoErrors();
        $this->assertSame('renewed', $a->fresh()->status);
        $new = AccountAgreement::query()->where('status', 'active')->firstOrFail();
        $this->assertSame(['2034-10-22', $a->id], [$new->ends_on->toDateString(), (int) $new->renewed_from_id]);

        /* not renewing */
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement/end", [])->assertSessionHasErrors('reason');
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement/end", ['reason' => 'moved to another distributor'])->assertSessionHasNoErrors();
        $this->assertSame('ended', $new->fresh()->status);
        $this->assertSame('active', $owner->fresh()->account_status);
    }

    public function test_only_super_admin_and_only_label_or_artist_accounts(): void
    {
        $manager = $this->user('admin');
        $owner = $this->user('artist');
        $super = $this->user('super_admin');

        $this->actingAs($manager)->post("/admin/users/{$owner->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 36])->assertForbidden();
        $this->actingAs($owner)->post("/admin/users/{$owner->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 36])->assertForbidden();
        $this->actingAs($super)->post("/admin/users/{$manager->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 36])->assertStatus(422);
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 121])->assertSessionHasErrors('term_months');
        $this->actingAs($super)->post("/admin/users/{$owner->id}/agreement", ['signed_on' => '2026-10-23', 'term_months' => 36])->assertSessionHasNoErrors();
        $this->assertSame(1, AccountAgreement::query()->count());
    }

    public function test_dashboard_warns_from_90_days_before_and_pops_up_once_a_day(): void
    {
        $super = $this->user('super_admin');
        $make = function (string $name, string $signed, int $months, string $status = 'active') {
            $u = $this->user('label', $name);
            DB::table('labels')->insert(['public_id' => (string) \Illuminate\Support\Str::ulid(), 'name' => $name.' Music', 'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(), 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

            return AccountAgreement::query()->create(['user_id' => $u->id, 'signed_on' => $signed, 'term_months' => $months,
                'ends_on' => AccountAgreements::endsOn($signed, $months)->toDateString(), 'notice_days' => 90, 'status' => $status]);
        };
        $make('Ends In 80', '2024-01-11', 36);       /* ends 2027-01-10 -> 79 days */
        $make('Ends In 120', '2024-02-21', 36);      /* ends 2027-02-20 -> 120 days, not yet */
        $make('Ended', '2023-10-18', 36);            /* ended 2026-10-17 -> -6 */
        $make('Renewed Old', '2023-10-18', 36, 'renewed');

        $this->actingAs($super)->get('/super-admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('agreements.popup', true)
            ->where('agreements.due', fn ($due) => collect($due)->pluck('account')->all() === ['Ended', 'Ends In 80']
                && collect($due)->pluck('days_left')->all() === [-6, 79]
                && collect($due)->firstWhere('account', 'Ends In 80')['labels'] === ['Ends In 80 Music']));

        /* "remind me tomorrow" - not again today */
        $this->actingAs($super)->postJson('/admin/agreements/popup-seen')->assertOk();
        $this->actingAs($super)->get('/super-admin/dashboard')->assertInertia(fn (Assert $p) => $p->where('agreements.popup', false));

        /* next morning: again, and one day less */
        $this->travel(1)->days();
        $this->actingAs($super)->get('/super-admin/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('agreements.popup', true)
            ->where('agreements.due.1.days_left', 78));

        /* managers do not get it */
        $this->actingAs($this->user('admin'))->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->where('agreements', null));
    }
}
