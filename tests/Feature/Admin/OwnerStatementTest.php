<?php

namespace Tests\Feature\Admin;

use App\Mail\OwnerStatementMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * MIXXTUNE_OWNER_STATEMENT_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class OwnerStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

    private function makeUser(string $role, ?string $email = null): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' Person',
            'email' => $email ?? Str::lower(Str::random(10)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(?int $ownerId, string $name): int
    {
        $row = ['name' => $name, 'user_id' => $ownerId, 'revenue_share_percentage' => 80, 'created_at' => now(), 'updated_at' => now()];

        foreach (['slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'public_id' => (string) Str::uuid(), 'status' => 'active'] as $column => $value) {
            if (Schema::hasColumn('labels', $column)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    /* A published July statement for $labelId with three report lines. */
    private function makeStatement(int $labelId, float $net = 160.0): int
    {
        $import = ['public_id' => (string) Str::uuid(), 'original_filename' => 'gen-july.csv', 'stored_path' => 'x', 'created_at' => now(), 'updated_at' => now()];

        foreach (['financial_status' => 'published', 'reporting_month' => '2026-07', 'status' => 'completed'] as $column => $value) {
            if (Schema::hasColumn('report_imports', $column)) {
                $import[$column] = $value;
            }
        }

        $importId = (int) DB::table('report_imports')->insertGetId($import);

        $statementId = (int) DB::table('royalty_statements')->insertGetId([
            'public_id' => (string) Str::ulid(), 'label_id' => $labelId, 'statement_month' => '2026-07', 'currency' => 'INR',
            'gross_earnings' => $net, 'commission_amount' => 0, 'tax_amount' => 0, 'other_deductions' => 0,
            'net_payable' => $net, 'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('report_import_publications')->insertGetId(['report_import_id' => $importId, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('report_import_postings')->insert([
            'publication_id' => $publicationId, 'report_import_id' => $importId, 'owner_type' => 'label', 'owner_id' => $labelId,
            'currency' => 'INR', 'posting_key' => Str::random(20), 'status' => 'posted', 'royalty_statement_id' => $statementId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['Spotify', 'IN', 'Song One', 'INX000000001', 1000, 100, 80], ['YouTube', 'IN', 'Song Two', 'INX000000002', 500, 75, 60], ['Spotify', 'US', 'Song One', 'INX000000001', 200, 25, 20]] as [$platform, $country, $title, $isrc, $streams, $collected, $earned]) {
            DB::table('report_rows')->insert([
                'row_hash' => Str::random(40), 'report_import_id' => $importId, 'revenue_owner_type' => 'label', 'revenue_owner_id' => $labelId,
                'platform' => $platform, 'country_code' => $country, 'track_title' => $title, 'isrc' => $isrc, 'streams' => $streams,
                'collected_revenue' => $collected, 'earnings' => $earned, 'label_rate' => 0.8, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $statementId;
    }

    public function test_owner_and_super_admin_download_readable_pdf(): void
    {
        $owner = $this->makeUser('label');
        $label = $this->makeLabel($owner->id, 'Statement Label A');
        $statement = $this->makeStatement($label);

        foreach ([$owner, $this->makeUser('super_admin')] as $user) {
            $response = $this->actingAs($user)->get("/statements/{$statement}/download");
            $response->assertOk();
            $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', (string) $response->getContent());
            $this->assertStringContainsString('Statement-2026-07-Statement-Label-A.pdf', (string) $response->headers->get('Content-Disposition'));
        }

        $other = $this->makeUser('label');
        $this->makeLabel($other->id, 'Other Label');
        $this->actingAs($other)->get("/statements/{$statement}/download")->assertForbidden();
    }

    public function test_statement_data_is_summed_from_report_lines(): void
    {
        $owner = $this->makeUser('label');
        $label = $this->makeLabel($owner->id, 'Statement Label B');
        $statement = \App\Models\Finance\RoyaltyStatement::query()->findOrFail($this->makeStatement($label));

        $data = app(\App\Services\V2\OwnerStatementService::class)->data($statement);

        $this->assertEquals(200, $data['summary']['collected']);
        $this->assertEquals(160, $data['summary']['earnings']);
        $this->assertSame('80%', $data['summary']['rate']);
        $this->assertSame('July 2026', $data['month_label']);
        $this->assertSame('Spotify', $data['by_store'][0]->name);
        $this->assertEquals(100, $data['by_store'][0]->earnings);
        $this->assertSame('Song One', $data['top_tracks'][0]->title);
    }

    public function test_preview_pdf_from_pending_report_is_super_admin_only_and_saves_nothing(): void
    {
        $owner = $this->makeUser('label');
        $label = $this->makeLabel($owner->id, 'Preview Label');
        $import = ['public_id' => (string) Str::uuid(), 'original_filename' => 'pending.csv', 'stored_path' => 'x', 'created_at' => now(), 'updated_at' => now()];

        foreach (['financial_status' => 'review_pending', 'reporting_month' => '2026-08', 'status' => 'completed'] as $column => $value) {
            if (Schema::hasColumn('report_imports', $column)) {
                $import[$column] = $value;
            }
        }

        $importId = (int) DB::table('report_imports')->insertGetId($import);
        DB::table('report_rows')->insert([
            'row_hash' => Str::random(40), 'report_import_id' => $importId, 'revenue_owner_type' => 'label', 'revenue_owner_id' => $label,
            'platform' => 'Spotify', 'track_title' => 'Pending Song', 'streams' => 10, 'collected_revenue' => 10, 'earnings' => 8,
            'label_rate' => 0.8, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $url = "/admin/statement-emails/preview?label_id={$label}&import_id={$importId}";
        $this->actingAs($owner)->get($url)->assertForbidden();

        $super = $this->makeUser('super_admin');
        $response = $this->actingAs($super)->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame(0, DB::table('royalty_statements')->count());

        $this->actingAs($super)->getJson('/admin/statement-emails')
            ->assertOk()->assertJsonPath('pending_imports.0.id', $importId)->assertJsonPath('months', []);
    }

    public function test_only_super_admin_sees_and_sends(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->getJson('/admin/statement-emails')->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/statement-emails/send', ['month' => '2026-07'])->assertForbidden();
    }

    public function test_send_once_with_pdf_then_skip_unless_resend(): void
    {
        Mail::fake();
        $super = $this->makeUser('super_admin');
        $owner = $this->makeUser('label', 'owner.statement@example.test');
        $withOwner = $this->makeStatement($this->makeLabel($owner->id, 'Statement Label C'), 500);
        $noOwner = $this->makeStatement($this->makeLabel(null, 'Statement Label D'), 50);

        $this->actingAs($super)->getJson('/admin/statement-emails?month=2026-07')
            ->assertOk()
            ->assertJsonPath('counts.total', 2)
            ->assertJsonPath('counts.with_email', 1)
            ->assertJsonPath('counts.to_send', 1);

        $first = $this->actingAs($super)->postJson('/admin/statement-emails/send', ['month' => '2026-07'])->assertOk();
        $first->assertJsonPath('remaining', 0);
        $byId = collect($first->json('results'))->keyBy('id');
        $this->assertSame('sent', $byId[$withOwner]['status']);
        $this->assertSame('no_email', $byId[$noOwner]['status']);

        Mail::assertSent(OwnerStatementMail::class, function (OwnerStatementMail $mail) {
            return $mail->hasTo('owner.statement@example.test')
                && str_starts_with($mail->pdfBytes, '%PDF')
                && str_ends_with($mail->pdfName, '.pdf');
        });
        Mail::assertSent(OwnerStatementMail::class, 1);

        /* Second run: nothing new to send. */
        $this->actingAs($super)->postJson('/admin/statement-emails/send', ['month' => '2026-07'])->assertOk();
        Mail::assertSent(OwnerStatementMail::class, 1);

        $this->actingAs($super)->getJson('/admin/statement-emails?month=2026-07')->assertJsonPath('counts.sent', 1);

        /* Re-send needs explicit statement ids. */
        $this->actingAs($super)->postJson('/admin/statement-emails/send', ['month' => '2026-07', 'force' => true])->assertStatus(422);
        $this->actingAs($super)->postJson('/admin/statement-emails/send', ['month' => '2026-07', 'statement_ids' => [$withOwner], 'force' => true])
            ->assertOk()->assertJsonPath('results.0.status', 'sent');
        Mail::assertSent(OwnerStatementMail::class, 2);

        $this->assertSame(2, DB::table('audit_logs')->where('action', 'statement.emailed')->where('auditable_id', $withOwner)->count());
    }
}
