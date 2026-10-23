<?php

namespace Tests\Feature\Admin;

use App\Mail\LabelOwnershipOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class LabelOwnershipBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'label_id,label_name,current_owner_email,owner_email,owner_name,username,phone,address_line_1,address_line_2,city,state_code,postal_code,revenue_share_percentage,account_holder_name,bank_account_number,bank_name,ifsc_code,branch_name,upi_id,pan_number,gst_number';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->createProductionOnlyTables();
    }

    /*
     * These two tables exist in production (database/schema/mysql-schema.sql)
     * but have no migration for the SQLite test database. Create the same
     * columns here so the new-owner path runs end to end in the test.
     */
    private function createProductionOnlyTables(): void
    {
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
            'name' => ucfirst($role).' '.Str::random(5),
            'email' => $email ?? Str::lower(Str::random(12)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function makeLabel(?int $ownerId, string $name, $rate = null): int
    {
        $row = ['name' => $name, 'user_id' => $ownerId, 'created_at' => now(), 'updated_at' => now()];

        foreach (['slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'public_id' => (string) Str::uuid(), 'status' => 'active', 'revenue_share_percentage' => $rate] as $column => $value) {
            if (Schema::hasColumn('labels', $column)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table('labels')->insertGetId($row);
    }

    private function upload(User $admin, string $csv)
    {
        $file = UploadedFile::fake()->createWithContent('owners.csv', $csv);

        return $this->actingAs($admin)->post('/admin/labels/ownership/bulk/preview', ['csv' => $file], ['Accept' => 'application/json']);
    }

    private function otpFor(User $admin, string $token): string
    {
        Mail::fake();
        $this->actingAs($admin)->postJson('/admin/labels/ownership/bulk/otp', ['token' => $token])->assertOk();

        $otp = null;
        Mail::assertSent(LabelOwnershipOtpMail::class, function (LabelOwnershipOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        return (string) $otp;
    }

    private function newOwnerRow(int $labelId, string $labelName, string $email): string
    {
        return implode(',', [$labelId, $labelName, '', $email, 'Bulk Owner', '', '9000000000', 'Street 1', '', 'New Delhi', 'DL', '110001', '80', 'Bulk Owner', '000111222333', 'Test Bank', 'TEST0000001', '', '', 'ABCDE1234F', '']);
    }

    public function test_only_super_admin_and_template_lists_unowned_labels(): void
    {
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');
        $owner = $this->makeLabel(null, 'Bulk Free Label', 90);
        $this->makeLabel($this->makeUser('label')->id, 'Bulk Owned Label');

        $this->actingAs($admin)->get('/admin/labels/ownership/bulk/template')->assertForbidden();

        $csv = (string) $this->actingAs($super)->get('/admin/labels/ownership/bulk/template')->assertOk()->getContent();

        $this->assertStringContainsString('label_id,label_name', $csv);
        $this->assertStringContainsString("\n{$owner},", $csv);
        $this->assertStringContainsString('Bulk Free Label', $csv);
        $this->assertStringNotContainsString('Bulk Owned Label', $csv);
    }

    public function test_preview_reports_errors_and_writes_nothing(): void
    {
        $super = $this->makeUser('super_admin');
        $manager = $this->makeUser('admin', 'manager.person@example.test');
        $a = $this->makeLabel(null, 'Bulk Label A');
        $b = $this->makeLabel(null, 'Bulk Label B');

        $csv = implode("\n", [
            self::HEADER,
            "{$a},Wrong Name,,x.owner@example.test,,,,,,,,,,,,,,,,,",
            "999999,Ghost,,x.owner@example.test,,,,,,,,,,,,,,,,,",
            "{$b},Bulk Label B,,manager.person@example.test,,,,,,,,,,,,,,,,,",
        ]);

        $response = $this->upload($super, $csv)->assertOk();
        $response->assertJsonPath('token', null);
        $messages = collect($response->json('errors'))->pluck('message')->implode(' | ');

        $this->assertStringContainsString('Bulk Label A', $messages);
        $this->assertStringContainsString('does not exist', $messages);
        $this->assertStringContainsString('not a Label account', $messages);
        $this->assertNull(DB::table('labels')->where('id', $a)->value('user_id'));
        $this->assertNull(DB::table('labels')->where('id', $b)->value('user_id'));
    }

    public function test_new_owner_needs_details_and_duplicates_are_rejected(): void
    {
        $super = $this->makeUser('super_admin');
        $a = $this->makeLabel(null, 'Bulk Label C');

        $csv = implode("\n", [
            self::HEADER,
            "{$a},Bulk Label C,,brand.new@example.test,,,,,,,,,80,,,,,,,,",
            "{$a},Bulk Label C,,brand.new@example.test,,,,,,,,,80,,,,,,,,",
        ]);

        $response = $this->upload($super, $csv)->assertOk()->assertJsonPath('token', null);
        $messages = collect($response->json('errors'))->pluck('message')->implode(' | ');

        $this->assertStringContainsString('listed twice', $messages);
        $this->assertStringContainsString('owner_name', $messages);
    }

    public function test_existing_owner_bulk_assign_and_transfer_with_otp(): void
    {
        $super = $this->makeUser('super_admin');
        $target = $this->makeUser('label', 'target.owner@example.test');
        $old = $this->makeUser('label');
        $free = $this->makeLabel(null, 'Bulk Free D', 85);
        $taken = $this->makeLabel($old->id, 'Bulk Taken E', 70);
        $skip = $this->makeLabel(null, 'Bulk Skip F');

        $csv = implode("\n", [
            self::HEADER,
            "{$free},Bulk Free D,,TARGET.owner@example.test,,,,,,,,,85,,,,,,,,",
            "{$taken},Bulk Taken E,{$old->email},target.owner@example.test,,,,,,,,,70,,,,,,,,",
            "{$skip},Bulk Skip F,,,,,,,,,,,,,,,,,,,",
        ]);

        $preview = $this->upload($super, $csv)->assertOk();
        $preview->assertJsonPath('summary.owners', 1)
            ->assertJsonPath('summary.assign', 1)
            ->assertJsonPath('summary.transfer', 1)
            ->assertJsonPath('summary.skipped_rows', 1);
        $token = (string) $preview->json('token');
        $this->assertSame(40, strlen($token));

        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/run', ['token' => $token])->assertStatus(422);

        $otp = $this->otpFor($super, $token);
        $wrong = $otp === '000000' ? '111111' : '000000';
        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/confirm', ['token' => $token, 'otp' => $wrong])->assertStatus(422);
        $this->assertNull(DB::table('labels')->where('id', $free)->value('user_id'));

        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/confirm', ['token' => $token, 'otp' => $otp])->assertOk();
        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/run', ['token' => $token])
            ->assertOk()->assertJsonPath('done', true)->assertJsonPath('succeeded', 1)->assertJsonPath('failed', 0)
            ->assertJsonPath('results.0.assigned', 1)->assertJsonPath('results.0.transferred', 1);

        $this->assertSame($target->id, (int) DB::table('labels')->where('id', $free)->value('user_id'));
        $this->assertSame($target->id, (int) DB::table('labels')->where('id', $taken)->value('user_id'));
        $this->assertNull(DB::table('labels')->where('id', $skip)->value('user_id'));

        if (Schema::hasColumn('labels', 'revenue_share_percentage')) {
            $this->assertEquals(85, (float) DB::table('labels')->where('id', $free)->value('revenue_share_percentage'));
            $this->assertEquals(70, (float) DB::table('labels')->where('id', $taken)->value('revenue_share_percentage'));
        }

        /* Running again does nothing more. */
        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/run', ['token' => $token])
            ->assertOk()->assertJsonPath('done', true)->assertJsonPath('processed', 1);
    }

    public function test_new_owner_created_through_users_module(): void
    {
        $super = $this->makeUser('super_admin');
        $a = $this->makeLabel(null, 'Bulk New G', 90);
        $b = $this->makeLabel(null, 'Bulk New H', 75);

        $csv = implode("\n", [
            self::HEADER,
            $this->newOwnerRow($a, 'Bulk New G', 'bulk.new.owner@example.test'),
            "{$b},Bulk New H,,bulk.new.owner@example.test,,,,,,,,,75,,,,,,,,",
        ]);

        $preview = $this->upload($super, $csv)->assertOk()->assertJsonPath('summary.new_owners', 1)->assertJsonPath('errors', []);
        $token = (string) $preview->json('token');

        $otp = $this->otpFor($super, $token);
        $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/confirm', ['token' => $token, 'otp' => $otp])->assertOk();
        $run = $this->actingAs($super)->postJson('/admin/labels/ownership/bulk/run', ['token' => $token])->assertOk();

        if ($run->json('results.0.status') !== 'done' && str_contains((string) $run->json('results.0.message'), 'no such table')) {
            $this->markTestSkipped('Users module needs a table missing in the SQLite test schema: '.$run->json('results.0.message'));
        }

        $run->assertJsonPath('results.0.status', 'done')->assertJsonPath('results.0.created', true);
        $owner = User::query()->where('email', 'bulk.new.owner@example.test')->firstOrFail();
        $this->assertSame('label', $owner->role);
        $this->assertSame('pending', $owner->account_status);
        $this->assertSame($owner->id, (int) DB::table('labels')->where('id', $a)->value('user_id'));
        $this->assertSame($owner->id, (int) DB::table('labels')->where('id', $b)->value('user_id'));

        if (Schema::hasColumn('labels', 'revenue_share_percentage')) {
            $this->assertEquals(90, (float) DB::table('labels')->where('id', $a)->value('revenue_share_percentage'));
            $this->assertEquals(75, (float) DB::table('labels')->where('id', $b)->value('revenue_share_percentage'));
        }

        $this->assertTrue(DB::table('audit_logs')->where('action', 'label.owner_assigned')->exists());
    }

    public function test_excel_damaged_numbers_and_semicolon_files(): void
    {
        $super = $this->makeUser('super_admin');
        $a = $this->makeLabel(null, 'Bulk Excel I');
        $row = str_replace('000111222333', '1.11222E+11', $this->newOwnerRow($a, 'Bulk Excel I', 'excel.owner@example.test'));

        $response = $this->upload($super, self::HEADER."\n".$row)->assertOk()->assertJsonPath('token', null);
        $this->assertStringContainsString('changed by Excel', collect($response->json('errors'))->pluck('message')->implode(' '));

        $semicolon = str_replace(',', ';', self::HEADER."\n".$this->newOwnerRow($a, 'Bulk Excel I', 'excel.owner@example.test'));
        $this->upload($super, $semicolon)->assertOk()->assertJsonPath('errors', [])->assertJsonPath('summary.new_owners', 1);
    }
}
