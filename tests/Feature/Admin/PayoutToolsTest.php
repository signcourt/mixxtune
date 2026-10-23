<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use App\Services\V2\PayoutDeductionService;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/*
 * MIXXTUNE_TDS_V1 / MIXXTUNE_PAYOUT_FILES_V1 / MIXXTUNE_INVITATION_REMINDERS_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class PayoutToolsTest extends TestCase
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

        $this->setting('company', 'company.legal_name', 'Test Distribution Company', 'string');
        $this->setting('company', 'company.address', 'Test Company Address', 'string');
        $this->setting('invoice', 'invoice.gst_percent', '0', 'decimal');
        $this->setting('invoice', 'invoice.tds_percent', '10', 'decimal');
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], [
            'group' => $group, 'value' => $value, 'type' => $type,
            'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget("system_setting:{$key}");
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => ucfirst($role).' '.Str::random(4),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'kyc_status' => 'verified',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));

        return User::query()->findOrFail($id);
    }

    private function owner(?string $pan = 'ABCDE1234F', string $kyc = 'verified', float $wallet = 10000): User
    {
        $owner = $this->makeUser('artist');
        PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Payee '.$owner->id,
            'bank_name' => 'Demo Bank', 'bank_account_number' => '0011122223333', 'ifsc_code' => 'DEMO0000001',
            'pan_number' => $pan, 'country_code' => 'IN', 'kyc_status' => $kyc, 'verified_at' => now(),
        ]);
        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null,
            'currency' => 'INR', 'available_balance' => $wallet, 'pending_balance' => 0,
            'lifetime_credits' => $wallet, 'lifetime_debits' => 0, 'status' => 'active',
        ]);

        return $owner;
    }

    private function approved(User $owner, User $super, float $amount)
    {
        $service = app(WithdrawalService::class);

        return $service->approve($service->create($owner, $amount, 'bank', 'Generated test.'), $super);
    }

    private function pay(User $owner, User $super, float $amount)
    {
        $withdrawal = $this->approved($owner, $super, $amount);

        return app(WithdrawalService::class)->markPaid($withdrawal, $super, 'UTR'.Str::random(6));
    }

    public function test_tds_rate_limit_and_no_pan_rate(): void
    {
        $super = $this->makeUser('super_admin');

        // plain 10% on every payout
        $first = $this->pay($this->owner(), $super, 1500);
        $invoice = DB::table('invoices')->where('withdrawal_id', $first->id)->first();
        $this->assertEquals(150, (float) $invoice->tds_amount);
        $this->assertEquals(1350, (float) $invoice->net_payable);
        $this->assertSame('ABCDE1234F', $invoice->pan);

        // yearly limit 2000: first 1500 is free, the payout that crosses the limit is taxed
        $this->setting('invoice', 'invoice.tds_threshold', '2000', 'decimal');
        $owner = $this->owner();
        $a = $this->pay($owner, $super, 1500);
        $this->assertEquals(0, (float) DB::table('invoices')->where('withdrawal_id', $a->id)->value('tds_amount'));
        $b = $this->pay($owner, $super, 1500);
        $this->assertEquals(150, (float) DB::table('invoices')->where('withdrawal_id', $b->id)->value('tds_amount'));

        // no PAN -> higher rate
        $this->setting('invoice', 'invoice.tds_threshold', '0', 'decimal');
        $this->setting('invoice', 'invoice.tds_no_pan_percent', '20', 'decimal');
        $c = $this->pay($this->owner(null), $super, 1500);
        $row = DB::table('invoices')->where('withdrawal_id', $c->id)->first();
        $this->assertEquals(20, (float) $row->tds_percentage);
        $this->assertEquals(300, (float) $row->tds_amount);
        $this->assertEquals(1200, (float) $row->net_payable);

        // rate 0 -> nothing cut
        $this->setting('invoice', 'invoice.tds_percent', '0', 'decimal');
        $this->setting('invoice', 'invoice.tds_no_pan_percent', '0', 'decimal');
        $d = $this->pay($this->owner(), $super, 1500);
        $this->assertEquals(0, (float) DB::table('invoices')->where('withdrawal_id', $d->id)->value('tds_amount'));
    }

    public function test_financial_year_runs_april_to_march(): void
    {
        $this->assertSame('2026-27', PayoutDeductionService::financialYear(Carbon::create(2026, 4, 1))['label']);
        $this->assertSame('2025-26', PayoutDeductionService::financialYear(Carbon::create(2026, 3, 31))['label']);
    }

    public function test_axis_file_has_only_ready_approved_requests_with_net_amount(): void
    {
        $super = $this->makeUser('super_admin');
        $this->setting('payout', 'payout.debit_account_number', '9120 0001 2345', 'string');

        $ready = $this->owner();
        $readyWd = $this->approved($ready, $super, 1500);
        $notVerified = $this->owner();
        $blockedWd = $this->approved($notVerified, $super, 2000);
        PayoutProfile::query()->where('user_id', $notVerified->id)->update(['kyc_status' => 'submitted']);
        $this->pay($this->owner(), $super, 1200); // paid: never in the file

        $csv = $this->actingAs($super)->get('/admin/payouts/axis-file?format=csv');
        $csv->assertOk();
        $lines = array_values(array_filter(explode("\n", $csv->streamedContent())));
        $this->assertCount(2, $lines, 'header + one ready payment');
        $this->assertSame(['Payment Mode', 'Amount', 'Value Date', 'Debit Account No'], array_slice(str_getcsv($lines[0]), 0, 4));
        $row = str_getcsv($lines[1]);
        $this->assertSame(['NEFT', '1350.00'], [$row[0], $row[1]]);
        $this->assertSame('912000012345', $row[3]);
        $this->assertSame('0011122223333', $row[5]);
        $this->assertSame('DEMO0000001', $row[6]);
        $this->assertSame($readyWd->withdrawal_number, $row[8]);

        $xlsx = $this->actingAs($super)->get('/admin/payouts/axis-file');
        $xlsx->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'axis').'.xlsx';
        file_put_contents($path, $xlsx->streamedContent());
        $book = IOFactory::load($path);
        unlink($path);
        $this->assertSame('0011122223333', $book->getSheetByName('Axis Upload')->getCell('F2')->getValue());
        $check = $book->getSheetByName('Check List')->toArray();
        $this->assertCount(3, $check);
        $blockedRow = collect($check)->firstWhere(0, $blockedWd->withdrawal_number);
        $this->assertStringContainsString('NOT IN FILE: KYC not verified', $blockedRow[9]);

        $this->assertSame(2, DB::table('audit_logs')->where('action', 'payout.bank_file_downloaded')->count());
        $this->assertSame('approved', $readyWd->fresh()->status, 'the file never marks anything paid');

        $manager = $this->makeUser('admin');
        $this->actingAs($manager)->get('/admin/payouts/axis-file')->assertForbidden();
    }

    public function test_tds_report_lists_paid_payouts_of_the_year(): void
    {
        $super = $this->makeUser('super_admin');
        $paid = $this->pay($this->owner(), $super, 1500);

        $response = $this->actingAs($super)->get('/admin/payouts/tds-report?fy='.PayoutDeductionService::financialYear()['label']);
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tds').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getSheetByName('Payouts')->toArray();
        unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame($paid->withdrawal_number, $rows[1][3]);
        $this->assertSame('ABCDE1234F', $rows[1][6]);
        $this->assertEquals(150, $rows[1][10]);
        $this->assertEquals(1350, $rows[1][11]);

        $this->actingAs($this->makeUser('label'))->get('/admin/payouts/tds-report')->assertForbidden();
    }

    public function test_admin_page_shows_tds_and_net_to_transfer(): void
    {
        $super = $this->makeUser('super_admin');
        $this->approved($this->owner(), $super, 1500);

        $this->actingAs($super)->get('/admin/withdrawals?status=approved')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('withdrawals.data.0.deduction.tds_amount', 150)
                ->where('withdrawals.data.0.deduction.net', 1350)
                ->where('payoutTools.can_download', true));
    }

    private function invited(int $sentDaysAgo, int $count = 1, string $status = 'sent'): User
    {
        return $this->makeUser('label', [
            'account_status' => 'pending',
            'email_verified_at' => null,
            'invitation_status' => $status,
            'invitation_token' => hash('sha256', Str::random(20)),
            'invitation_sent_at' => now()->subDays($sentDaysAgo),
            'invitation_expires_at' => now()->subDays($sentDaysAgo)->addDays(7),
            'invitation_count' => $count,
        ]);
    }

    public function test_invitation_reminders_go_only_to_due_people(): void
    {
        Notification::fake();

        $due = $this->invited(4);
        $expired = $this->invited(10, 2, 'expired');
        $tooSoon = $this->invited(1);
        $tooOld = $this->invited(40);
        $enough = $this->invited(4, 4);
        $accepted = $this->makeUser('label', ['invitation_status' => 'accepted', 'invitation_sent_at' => now()->subDays(5), 'invitation_count' => 1]);

        $this->artisan('invitations:send-reminders --dry-run')->expectsOutputToContain('2 reminder(s) due')->assertSuccessful();
        Notification::assertNothingSent();

        $this->artisan('invitations:send-reminders')->assertSuccessful();

        Notification::assertSentTo($due, UserInvitationNotification::class, fn ($n) => $n->reminder === true);
        Notification::assertSentTo($expired, UserInvitationNotification::class);
        foreach ([$tooSoon, $tooOld, $enough, $accepted] as $user) {
            Notification::assertNotSentTo($user, UserInvitationNotification::class);
        }

        $fresh = $due->fresh();
        $this->assertSame(2, (int) $fresh->invitation_count);
        $this->assertSame('sent', $fresh->invitation_status);
        $this->assertTrue($fresh->invitation_expires_at->isFuture());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'user.invitation_reminder_sent')->count());

        // the next run the same day sends nothing again
        $this->artisan('invitations:send-reminders')->expectsOutputToContain('Reminders sent: 0')->assertSuccessful();
    }

    public function test_reminders_switch_and_button(): void
    {
        Notification::fake();
        $super = $this->makeUser('super_admin');
        $due = $this->invited(5);

        $this->setting('invitation', 'invitation.reminders_enabled', '0', 'boolean');
        $this->artisan('invitations:send-reminders')->expectsOutputToContain('switched off')->assertSuccessful();
        Notification::assertNothingSent();

        // the button sends due reminders even when the daily run is off
        $this->actingAs($this->makeUser('admin'))->post('/admin/users/invitation-reminders')->assertForbidden();
        $this->actingAs($super)->post('/admin/users/invitation-reminders')->assertSessionHas('success');
        Notification::assertSentTo($due, UserInvitationNotification::class);
    }

    public function test_settings_save_keeps_new_fields_when_old_form_does_not_send_them(): void
    {
        $super = $this->makeUser('super_admin');
        $this->setting('invoice', 'invoice.tds_threshold', '30000', 'decimal');

        $payload = [
            'branding' => ['name' => 'MIXX TUNE', 'logo_url' => '/x.svg', 'login_logo_url' => '/x.svg'],
            'company' => ['name' => 'Mixx Tune'],
            'release' => ['minimum_days_ahead' => 7, 'allow_explicit' => true, 'require_artwork' => true, 'require_wav' => true],
            'upload' => ['audio_max_mb' => 300, 'artwork_max_mb' => 20, 'report_max_mb' => 500],
            'maintenance' => ['enabled' => false],
            'finance' => ['default_currency' => 'INR', 'minimum_withdrawal' => 1000, 'default_commission_percent' => 0],
            'invoice' => ['prefix' => 'INV', 'gst_percent' => 0, 'tds_percent' => 10],
        ];

        $this->actingAs($super)->patch('/admin/settings', $payload)->assertSessionHasNoErrors();

        $this->assertEquals(30000, (float) DB::table('system_settings')->where('key', 'invoice.tds_threshold')->value('value'));
        $this->assertSame('1', DB::table('system_settings')->where('key', 'invitation.reminders_enabled')->value('value'));
        $this->assertSame('3', DB::table('system_settings')->where('key', 'invitation.reminder_every_days')->value('value'));

        $payload['payout'] = ['debit_account_number' => '9120001234'];
        $payload['invitation'] = ['reminders_enabled' => false, 'reminder_every_days' => 5, 'reminder_max' => 2];
        $this->actingAs($super)->patch('/admin/settings', $payload)->assertSessionHasNoErrors();
        $this->assertSame('9120001234', DB::table('system_settings')->where('key', 'payout.debit_account_number')->value('value'));
        $this->assertSame('0', DB::table('system_settings')->where('key', 'invitation.reminders_enabled')->value('value'));

        $payload['payout'] = ['debit_account_number' => 'abc-123'];
        $this->actingAs($super)->patch('/admin/settings', $payload)->assertSessionHasErrors('payout.debit_account_number');
    }
}
