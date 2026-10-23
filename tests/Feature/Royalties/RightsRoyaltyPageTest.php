<?php

namespace Tests\Feature\Royalties;

use App\Mail\ActionOtpMail;
use App\Models\Finance\RightsRoyaltyImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_RIGHTS_ROYALTIES_V2 (W54) */
class RightsRoyaltyPageTest extends TestCase
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

    private function import(User $by, string $status, string $file = 'iprs.csv'): RightsRoyaltyImport
    {
        return RightsRoyaltyImport::create([
            'public_id' => (string) Str::ulid(), 'source' => 'IPRS', 'statement_month' => '2026-09',
            'currency' => 'INR', 'original_filename' => $file, 'uploaded_by' => $by->id,
            'total_rows' => 10, 'mapped_rows' => 8, 'unmapped_rows' => 2,
            'gross_amount' => 1000, 'mapped_amount' => 800, 'unmapped_amount' => 200, 'status' => $status,
        ]);
    }

    public function test_super_admin_sees_every_uploaded_rights_report(): void
    {
        $super = $this->user('super_admin');
        $this->import($super, 'published', 'old.csv');
        $this->import($super, 'review_required', 'new.csv');

        $this->actingAs($super)->get('/admin/royalties')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Royalties/Index')
            ->has('rightsImports', 2)
            ->where('rightsImports.0.file', 'new.csv')
            ->where('rightsImports.0.status', 'review_required')
            ->where('rightsImports.0.unmapped_rows', 2)
            ->where('rightsImports.1.status', 'published')
            ->where('statements.data', []));
    }

    public function test_approve_and_publish_ask_for_the_emailed_code(): void
    {
        Mail::fake();
        $super = $this->user('super_admin');
        $import = $this->import($super, 'review_required');

        $this->actingAs($super)->postJson('/admin/royalties/rights/imports/'.$import->public_id.'/approve')
            ->assertStatus(422)->assertJsonValidationErrors('action_otp');
        Mail::assertSent(ActionOtpMail::class);
        $this->assertSame('review_required', $import->fresh()->status);

        /* publish before approval: plain message, no code */
        Mail::fake();
        $this->actingAs($super)->postJson('/admin/royalties/rights/imports/'.$import->public_id.'/publish')
            ->assertStatus(422)->assertJsonPath('message', 'Approve the report first, then publish it.');
        Mail::assertNothingSent();

        $done = $this->import($super, 'published');
        $this->actingAs($super)->postJson('/admin/royalties/rights/imports/'.$done->public_id.'/approve')
            ->assertStatus(422)->assertJsonPath('message', 'This report is already published.');
        Mail::assertNothingSent();
    }

    public function test_admin_page_does_not_load_statements(): void
    {
        $super = $this->user('super_admin');
        DB::enableQueryLog();
        $this->actingAs($super)->get('/admin/royalties')->assertOk();
        $statementQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'royalty_statements'));
        $this->assertCount(0, $statementQueries);
    }
}
