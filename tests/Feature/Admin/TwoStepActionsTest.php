<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * MIXXTUNE_TWO_STEP_ACTIONS_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class TwoStepActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

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

        foreach ([
            ['company', 'company.legal_name', 'Test Distribution Company', 'string'],
            ['company', 'company.address', 'Test Company Address', 'string'],
            ['invoice', 'invoice.gst_percent', '18', 'decimal'],
            ['invoice', 'invoice.tds_percent', '10', 'decimal'],
        ] as [$group, $key, $value, $type]) {
            DB::table('system_settings')->insert([
                'group' => $group, 'key' => $key, 'value' => $value, 'type' => $type,
                'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function makeUser(string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' Person',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'kyc_status' => 'verified',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(?int $ownerId, string $name, $rate): int
    {
        $row = ['name' => $name, 'user_id' => $ownerId, 'revenue_share_percentage' => $rate, 'created_at' => now(), 'updated_at' => now()];

        foreach (['slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'public_id' => (string) Str::uuid(), 'status' => 'active'] as $column => $value) {
            if (Schema::hasColumn('labels', $column)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $mail) use (&$code) {
            $code = $mail->otp;

            return true;
        });

        return (string) $code;
    }

    private function unlock(User $actor): void
    {
        $this->actingAs($actor)->postJson('/v2/admin/action-otp/unlock', ['otp' => $this->lastCode()])->assertOk();
    }

    private function readyImport(int $labelId): int
    {
        $import = ['public_id' => (string) Str::uuid(), 'original_filename' => 'gen-july.csv', 'stored_path' => 'x', 'created_at' => now(), 'updated_at' => now()];

        foreach (['financial_status' => 'review_pending', 'reporting_month' => '2026-07', 'status' => 'completed'] as $column => $value) {
            if (Schema::hasColumn('report_imports', $column)) {
                $import[$column] = $value;
            }
        }

        $importId = (int) DB::table('report_imports')->insertGetId($import);

        DB::table('report_rows')->insert([
            'row_hash' => Str::random(40), 'report_import_id' => $importId, 'revenue_owner_type' => 'label', 'revenue_owner_id' => $labelId,
            'mapping_status' => 'mapped', 'currency' => 'INR', 'platform' => 'Spotify', 'country_code' => 'IN', 'track_title' => 'Song One',
            'isrc' => 'INX000000001', 'streams' => 1000, 'collected_revenue' => 100, 'earnings' => 80, 'label_rate' => 0.8,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $importId;
    }

    public function test_report_approval_needs_the_emailed_code(): void
    {
        $super = $this->makeUser('super_admin');
        $owner = $this->makeUser('label');
        $importId = $this->readyImport($this->makeLabel($owner->id, 'Two Step Label', 80));
        $payload = ['publish_at' => now()->addDay()->toDateString()];

        $this->actingAs($super)->post("/v2/admin/reports/imports/{$importId}/approve-and-schedule", $payload)
            ->assertSessionHasErrors('action_otp');
        $this->assertSame('review_pending', DB::table('report_imports')->where('id', $importId)->value('financial_status'));

        $this->actingAs($super)->postJson('/v2/admin/action-otp/unlock', ['otp' => '000000'])->assertStatus(422);
        $this->unlock($super);

        $this->actingAs($super)->post("/v2/admin/reports/imports/{$importId}/approve-and-schedule", $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame('approved_scheduled', DB::table('report_imports')->where('id', $importId)->value('financial_status'));
    }

    public function test_label_rate_change_needs_code_but_first_rate_does_not(): void
    {
        $super = $this->makeUser('super_admin');
        $empty = $this->makeLabel(null, 'No Rate Yet', null);
        $set = $this->makeLabel(null, 'Has Rate', 80);

        $this->actingAs($super)->patch("/v2/admin/labels/{$empty}/revenue-rate", ['revenue_share_percentage' => 75])
            ->assertSessionHasNoErrors();
        $this->assertEquals(75, DB::table('labels')->where('id', $empty)->value('revenue_share_percentage'));
        Mail::assertNotSent(ActionOtpMail::class);

        $this->actingAs($super)->patch("/v2/admin/labels/{$set}/revenue-rate", ['revenue_share_percentage' => 70])
            ->assertSessionHasErrors('action_otp');
        $this->assertEquals(80, DB::table('labels')->where('id', $set)->value('revenue_share_percentage'));

        $this->unlock($super);
        $this->actingAs($super)->patch("/v2/admin/labels/{$set}/revenue-rate", ['revenue_share_percentage' => 70])
            ->assertSessionHasNoErrors();
        $this->assertEquals(70, DB::table('labels')->where('id', $set)->value('revenue_share_percentage'));

        // the unlock was used by that save
        $this->actingAs($super)->patch("/v2/admin/labels/{$set}/revenue-rate", ['revenue_share_percentage' => 65])
            ->assertSessionHasErrors('action_otp');
        $this->assertEquals(70, DB::table('labels')->where('id', $set)->value('revenue_share_percentage'));
    }

    public function test_mark_paid_needs_code_for_that_withdrawal_only(): void
    {
        $super = $this->makeUser('super_admin');
        $service = app(WithdrawalService::class);
        $withdrawals = [];

        foreach (['One', 'Two'] as $name) {
            $owner = $this->makeUser('artist');
            PayoutProfile::query()->create([
                'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Owner '.$name,
                'bank_name' => 'Demo Bank', 'bank_account_number' => '111122223333', 'ifsc_code' => 'DEMO0000001',
                'country_code' => 'IN', 'kyc_status' => 'verified', 'verified_at' => now(),
            ]);
            WalletAccount::query()->create([
                'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null,
                'currency' => 'INR', 'available_balance' => 5000, 'pending_balance' => 0,
                'lifetime_credits' => 5000, 'lifetime_debits' => 0, 'status' => 'active',
            ]);
            $withdrawals[] = $service->approve($service->create($owner, 1500, 'bank', 'Demo withdrawal.'), $super);
        }

        [$first, $second] = $withdrawals;

        $this->actingAs($super)->post("/v2/admin/withdrawals/{$first->id}/paid", ['payment_reference' => 'UTR-1'])
            ->assertSessionHasErrors('action_otp');
        $this->assertSame('approved', $first->fresh()->status);

        $this->unlock($super);

        // the code was for withdrawal #1, not #2
        $this->actingAs($super)->post("/v2/admin/withdrawals/{$second->id}/paid", ['payment_reference' => 'UTR-2'])
            ->assertSessionHasErrors('action_otp');
        $this->assertSame('approved', $second->fresh()->status);

        $this->actingAs($super)->post("/v2/admin/withdrawals/{$first->id}/paid", ['payment_reference' => 'UTR-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $first->fresh()->status);
    }
}
