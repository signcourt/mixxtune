<?php

namespace Tests\Feature\Admin;

use App\Mail\LabelOwnershipOtpMail;
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
 * MIXXTUNE_LABEL_RATE_HISTORY_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class LabelRateHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        /* Same columns as production (database/schema/mysql-schema.sql). */
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

        if (!Schema::hasTable('user_panel_permissions')) {
            Schema::create('user_panel_permissions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                foreach (['can_view_catalogue', 'can_create_releases', 'can_manage_releases', 'can_view_reports', 'can_view_royalties', 'can_manage_wallet', 'can_manage_withdrawals', 'can_manage_users', 'can_manage_support', 'can_manage_settings', 'can_manage_delivery', 'can_manage_identifiers'] as $column) {
                    $table->boolean($column)->default(false);
                }
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function makeUser(string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' '.Str::random(5),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(string $name, $rate): int
    {
        $row = ['name' => $name, 'user_id' => null, 'revenue_share_percentage' => $rate, 'created_at' => now(), 'updated_at' => now()];

        foreach (['slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'public_id' => (string) Str::uuid(), 'status' => 'active'] as $column => $value) {
            if (Schema::hasColumn('labels', $column)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function entries(int $labelId)
    {
        return DB::table('audit_logs')->where('action', 'label.rate_changed')->where('auditable_id', $labelId)->orderBy('id')->get();
    }

    public function test_rate_change_from_labels_page_is_recorded_once(): void
    {
        $super = $this->makeUser('super_admin');
        $label = $this->makeLabel('History Label A', 80);

        $this->actingAs($super)->patch("/v2/admin/labels/{$label}/revenue-rate", ['revenue_share_percentage' => 85])->assertRedirect();
        $this->actingAs($super)->patch("/v2/admin/labels/{$label}/revenue-rate", ['revenue_share_percentage' => 85])->assertRedirect();

        $entries = $this->entries($label);
        $this->assertCount(1, $entries);
        $this->assertEquals(80, json_decode($entries[0]->old_values, true)['revenue_share_percentage']);
        $new = json_decode($entries[0]->new_values, true);
        $this->assertEquals(85, $new['revenue_share_percentage']);
        $this->assertSame('v2.admin.labels.revenue-rate', $new['source']);
        $this->assertSame($super->id, (int) $entries[0]->user_id);
    }

    public function test_history_endpoint_is_super_admin_only(): void
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');
        $label = $this->makeLabel('History Label B', null);

        $this->actingAs($super)->patch("/v2/admin/labels/{$label}/revenue-rate", ['revenue_share_percentage' => 90])->assertRedirect();

        $this->actingAs($admin)->getJson('/v2/admin/labels/rate-history?label_id='.$label)->assertForbidden();

        $this->actingAs($super)->getJson('/v2/admin/labels/rate-history?label_id='.$label)
            ->assertOk()
            ->assertJsonPath('label.current', 90)
            ->assertJsonPath('changes.0.old', null)
            ->assertJsonPath('changes.0.new', 90)
            ->assertJsonPath('changes.0.label_id', $label);

        $this->actingAs($super)->getJson('/v2/admin/labels/rate-history')
            ->assertOk()
            ->assertJsonPath('label', null)
            ->assertJsonPath('changes.0.label_name', 'History Label B');
    }

    public function test_bulk_set_rate_is_recorded_per_label(): void
    {
        $super = $this->makeUser('super_admin');
        $a = $this->makeLabel('History Label C', 70);
        $b = $this->makeLabel('History Label D', 75);

        $this->actingAs($super)->post('/v2/admin/labels/bulk-action', [
            'label_ids' => [$a, $b], 'action' => 'set_rate', 'revenue_share_percentage' => 88,
        ])->assertRedirect();

        $this->assertCount(1, $this->entries($a));
        $this->assertCount(1, $this->entries($b));
        $this->assertSame('v2.admin.labels.bulk-action', json_decode($this->entries($a)[0]->new_values, true)['source']);
    }

    public function test_new_owner_keeps_rates_and_logs_no_noise(): void
    {
        $super = $this->makeUser('super_admin');
        $withRate = $this->makeLabel('History Label E', 90);
        $noRate = $this->makeLabel('History Label F', null);

        $payload = [
            'label_ids' => [$withRate, $noRate],
            'mode' => 'new',
            'new_user' => [
                'name' => 'History Owner', 'email' => 'history.owner@example.test', 'phone' => '9000000000',
                'country' => 'India', 'country_code' => 'IN', 'state_code' => 'DL', 'state' => 'Delhi',
                'address_line_1' => 'Street 1', 'city' => 'New Delhi', 'postal_code' => '110001', 'revenue_share_percentage' => '80',
                'account_holder_name' => 'History Owner', 'bank_account_number' => '000111222333',
                'bank_name' => 'Test Bank', 'ifsc_code' => 'TEST0000001', 'pan_number' => 'ABCDE1234F',
            ],
        ];

        Mail::fake();
        $this->actingAs($super)->postJson('/v2/admin/labels/ownership/otp', $payload)->assertOk();
        $otp = null;
        Mail::assertSent(LabelOwnershipOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->actingAs($super)->postJson('/v2/admin/labels/ownership/apply', $payload + ['otp' => $otp])
            ->assertOk()->assertJsonPath('created', true);

        $this->assertEquals(90, (float) DB::table('labels')->where('id', $withRate)->value('revenue_share_percentage'));
        $this->assertNull(DB::table('labels')->where('id', $noRate)->value('revenue_share_percentage'));

        /* Nothing changed in the end, so nothing is logged (no 90 -> 80 -> 90 noise). */
        $this->assertCount(0, $this->entries($withRate));
        $this->assertCount(0, $this->entries($noRate));
    }

    public function test_history_shows_rate_used_in_each_report(): void
    {
        $super = $this->makeUser('super_admin');
        $label = $this->makeLabel('History Label G', 90);

        $import = function (string $status, string $month) {
            $row = ['public_id' => (string) Str::uuid(), 'original_filename' => "gen-{$month}.csv", 'stored_path' => 'x', 'created_at' => now(), 'updated_at' => now()];

            foreach (['financial_status' => $status, 'reporting_month' => $month.'-01', 'status' => 'completed'] as $column => $value) {
                if (Schema::hasColumn('report_imports', $column)) {
                    $row[$column] = $value;
                }
            }

            return (int) DB::table('report_imports')->insertGetId($row);
        };

        $approved = $import('published', '2026-06');
        $pending = $import('review_pending', '2026-07');

        foreach ([[$approved, 0.80, 100, 80], [$approved, 0.80, 50, 40], [$pending, 0.90, 200, 180]] as [$importId, $rate, $collected, $earned]) {
            DB::table('report_rows')->insert([
                'row_hash' => Str::random(40), 'report_import_id' => $importId,
                'revenue_owner_type' => 'label', 'revenue_owner_id' => $label,
                'label_rate' => $rate, 'collected_revenue' => $collected, 'earnings' => $earned,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($super)->getJson('/v2/admin/labels/rate-history?label_id='.$label)->assertOk();
        $response->assertJsonPath('label.pending_import_ids', [$pending]);

        $used = collect($response->json('rates_used'))->keyBy('import_id');
        $this->assertEquals(80, $used[$approved]['rate']);
        $this->assertEquals(150, $used[$approved]['collected']);
        $this->assertEquals(120, $used[$approved]['earnings']);
        $this->assertEquals(90, $used[$pending]['rate']);
        $this->assertSame('2026-06', $used[$approved]['month']);
    }
}
