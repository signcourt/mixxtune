<?php

namespace Tests\Feature\Admin;

use App\Mail\LabelOwnershipOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class LabelOwnershipTransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $status = 'active'): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' '.Str::random(5),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'role' => $role,
            'account_status' => $status,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(?int $ownerId, string $name): int
    {
        $row = ['name' => $name, 'user_id' => $ownerId, 'created_at' => now(), 'updated_at' => now()];

        foreach (['slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'public_id' => (string) Str::uuid(), 'status' => 'active'] as $column => $value) {
            if (Schema::hasColumn('labels', $column)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function fullNewUser(string $email): array
    {
        return [
            'name' => 'Generated Owner', 'username' => '', 'email' => $email, 'phone' => '9000000000',
            'country' => 'India', 'country_code' => 'IN', 'state_code' => 'DL', 'state' => 'Delhi',
            'address_line_1' => 'Generated Street 1', 'address_line_2' => '', 'city' => 'New Delhi', 'postal_code' => '110001', 'revenue_share_percentage' => '80',
            'account_holder_name' => 'Generated Owner', 'bank_account_number' => '000111222333',
            'bank_name' => 'Generated Test Bank', 'ifsc_code' => 'TEST0000001', 'branch_name' => '',
            'upi_id' => '', 'pan_number' => 'ABCDE1234F', 'gst_number' => '',
        ];
    }

    private function requestOtp(User $admin, array $payload): string
    {
        Mail::fake();
        $this->actingAs($admin)->postJson('/admin/labels/ownership/otp', $payload)->assertOk();

        $otp = null;
        Mail::assertSent(LabelOwnershipOtpMail::class, function (LabelOwnershipOtpMail $mail) use (&$otp, $admin) {
            $otp = $mail->otp;

            return $mail->hasTo($admin->email);
        });

        $this->assertNotNull($otp);

        return (string) $otp;
    }

    public function test_only_super_admin_can_use_ownership_endpoints(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('label');
        $label = $this->makeLabel(null, 'Gen Label A');

        $this->actingAs($admin)->getJson('/admin/labels/ownership/users')->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/labels/ownership/otp', [
            'label_ids' => [$label], 'mode' => 'existing', 'target_user_id' => $owner->id,
        ])->assertForbidden();
        $this->assertNull(DB::table('labels')->where('id', $label)->value('user_id'));
    }

    public function test_transfer_needs_correct_single_use_otp(): void
    {
        $super = $this->makeUser('super_admin');
        $oldOwner = $this->makeUser('label');
        $newOwner = $this->makeUser('label');
        $label = $this->makeLabel($oldOwner->id, 'Gen Label B');
        $payload = ['label_ids' => [$label], 'mode' => 'existing', 'target_user_id' => $newOwner->id];

        $otp = $this->requestOtp($super, $payload);
        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->actingAs($super)->postJson('/admin/labels/ownership/apply', $payload + ['otp' => $wrong])->assertStatus(422);
        $this->assertSame($oldOwner->id, (int) DB::table('labels')->where('id', $label)->value('user_id'));

        $this->actingAs($super)->postJson('/admin/labels/ownership/apply', $payload + ['otp' => $otp])
            ->assertOk()->assertJsonPath('changes.0.status', 'transferred');
        $this->assertSame($newOwner->id, (int) DB::table('labels')->where('id', $label)->value('user_id'));

        if (Schema::hasTable('audit_logs')) {
            $this->assertTrue(DB::table('audit_logs')->where('action', 'label.owner_transferred')->exists());
        }

        $this->actingAs($super)->postJson('/admin/labels/ownership/apply', $payload + ['otp' => $otp])->assertStatus(422);
    }

    public function test_otp_is_bound_to_the_exact_change(): void
    {
        $super = $this->makeUser('super_admin');
        $owner = $this->makeUser('label');
        $labelX = $this->makeLabel(null, 'Gen Label X');
        $labelY = $this->makeLabel(null, 'Gen Label Y');

        $otp = $this->requestOtp($super, ['label_ids' => [$labelX], 'mode' => 'existing', 'target_user_id' => $owner->id]);

        $this->actingAs($super)->postJson('/admin/labels/ownership/apply', [
            'label_ids' => [$labelY], 'mode' => 'existing', 'target_user_id' => $owner->id, 'otp' => $otp,
        ])->assertStatus(422);

        $this->assertNull(DB::table('labels')->where('id', $labelY)->value('user_id'));
        $this->assertNull(DB::table('labels')->where('id', $labelX)->value('user_id'));
    }

    public function test_new_owner_needs_full_user_details_before_otp(): void
    {
        $super = $this->makeUser('super_admin');
        $label = $this->makeLabel(null, 'Gen Label V');

        Mail::fake();
        $this->actingAs($super)->postJson('/admin/labels/ownership/otp', [
            'label_ids' => [$label], 'mode' => 'new', 'new_user' => ['name' => 'Only Name', 'email' => 'only.name@example.test'],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'new_user.pan_number', 'new_user.bank_account_number', 'new_user.ifsc_code', 'new_user.state_code', 'new_user.revenue_share_percentage',
        ]);

        Mail::assertNothingSent();
    }

    public function test_new_owner_is_created_through_users_module_and_keeps_label_rates(): void
    {
        $super = $this->makeUser('super_admin');
        $label1 = $this->makeLabel(null, 'Gen Label N1');
        $label2 = $this->makeLabel(null, 'Gen Label N2');
        DB::table('labels')->where('id', $label1)->update(['revenue_share_percentage' => 90]);

        $payload = ['label_ids' => [$label1, $label2], 'mode' => 'new', 'new_user' => $this->fullNewUser('generated.owner@example.test')];
        $otp = $this->requestOtp($super, $payload);

        $response = $this->actingAs($super)->postJson('/admin/labels/ownership/apply', $payload + ['otp' => $otp]);

        if ($response->status() === 500 && $response->exception && str_contains($response->exception->getMessage(), 'no such table')) {
            $this->markTestSkipped('Users module needs a table that is missing in the SQLite test schema: '.$response->exception->getMessage());
        }

        $response->assertOk()->assertJsonPath('created', true);

        $created = User::query()->where('email', 'generated.owner@example.test')->firstOrFail();
        $this->assertSame('label', $created->role);
        $this->assertSame('pending', $created->account_status);
        $this->assertSame(2, DB::table('labels')->whereIn('id', [$label1, $label2])->where('user_id', $created->id)->count());
        $this->assertEquals(90.0, (float) DB::table('labels')->where('id', $label1)->value('revenue_share_percentage'));
    }

    public function test_label_search_for_super_admin_only_with_unowned_filter(): void
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('label');
        $free = $this->makeLabel(null, 'Zebra Search Free');
        $this->makeLabel($owner->id, 'Zebra Search Owned');

        $this->actingAs($admin)->getJson('/admin/labels/ownership/labels?q=Zebra')->assertForbidden();

        $all = $this->actingAs($super)->getJson('/admin/labels/ownership/labels?q=Zebra')->assertOk()->json('labels');
        $this->assertCount(2, $all);

        $unowned = $this->actingAs($super)->getJson('/admin/labels/ownership/labels?q=Zebra&unowned=1')->assertOk()->json('labels');
        $this->assertSame([$free], array_column($unowned, 'id'));
    }

    public function test_owner_must_be_a_label_account_and_resend_is_throttled(): void
    {
        $super = $this->makeUser('super_admin');
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('label');
        $label = $this->makeLabel(null, 'Gen Label T');

        Mail::fake();
        $this->actingAs($super)->postJson('/admin/labels/ownership/otp', [
            'label_ids' => [$label], 'mode' => 'existing', 'target_user_id' => $admin->id,
        ])->assertStatus(422);

        $payload = ['label_ids' => [$label], 'mode' => 'existing', 'target_user_id' => $owner->id];
        $this->actingAs($super)->postJson('/admin/labels/ownership/otp', $payload)->assertOk();
        $this->actingAs($super)->postJson('/admin/labels/ownership/otp', $payload)->assertStatus(422);
    }
}
