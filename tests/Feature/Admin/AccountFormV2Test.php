<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* W79 MIXXTUNE_ACCOUNT_FORM_V2 */
class AccountFormV2Test extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();

        if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            \Illuminate\Support\Facades\Schema::create('audit_logs', function ($table) {
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

        /* production has this table from the schema dump; the test database has no migration for it */
        if (!\Illuminate\Support\Facades\Schema::hasTable('user_panel_permissions')) {
            \Illuminate\Support\Facades\Schema::create('user_panel_permissions', function ($table) {
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

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Sanatan Records',
            'full_name' => 'Ramesh Kumar',
            'email' => 'sanatan.'.Str::lower(Str::random(5)).'@example.test',
            'role' => 'label',
            'send_invitation' => false,
            'revenue_share_percentage' => 75,
            'country' => 'India',
            'country_code' => 'IN',
            'state_code' => 'DL',
            'state' => 'Delhi',
            'city' => 'Delhi',
            'postal_code' => '110001',
            'address_line_1' => 'Generated Street 1',
            'account_holder_name' => 'Ramesh Kumar',
            'bank_account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'ifsc_code' => 'TEST0000001',
            'pan_number' => 'ABCDE1234F',
        ], $extra);
    }

    public function test_create_saves_account_and_full_name_auto_ids_and_a_rate_per_label(): void
    {
        $super = $this->super();
        $free = Label::factory()->create(['name' => 'Free Master Label', 'user_id' => null, 'parent_label_id' => null, 'revenue_share_percentage' => null]);
        $priced = Label::factory()->create(['name' => 'Priced Free Label', 'user_id' => null, 'parent_label_id' => null, 'revenue_share_percentage' => 90]);

        $payload = $this->payload([
            'label_ids' => [$free->id, $priced->id],
            'label_rates' => [(string) $free->id => 75, (string) $priced->id => 50],
            'new_labels' => ['Sanatan Fresh Label', 'Sanatan Second Label'],
            'new_label_rates' => [70, 65.5],
        ]);

        /* giving existing labels an owner needs the e-mailed code; nothing is saved before */
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/v2/admin/users', $payload))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, User::query()->where('name', 'Sanatan Records')->count());

        $user = User::query()->where('name', 'Sanatan Records')->firstOrFail();
        $this->assertSame('Ramesh Kumar', $user->full_name);
        $this->assertNotEmpty($user->username);
        $this->assertNotEmpty($user->client_id);

        $rates = Label::query()->where('user_id', $user->id)->pluck('revenue_share_percentage', 'name')->map(fn ($r) => (float) $r)->all();
        $this->assertSame(75.0, $rates['Free Master Label']);
        $this->assertSame(70.0, $rates['Sanatan Fresh Label']);
        $this->assertSame(65.5, $rates['Sanatan Second Label']);
        /* a label that already had a % keeps it */
        $this->assertSame(90.0, $rates['Priced Free Label']);
    }

    public function test_edit_shows_full_name_and_new_label_gets_its_own_rate(): void
    {
        $super = $this->super();
        $this->actingAs($super)->post('/v2/admin/users', $this->payload(['new_labels' => ['Edit Base Label'], 'new_label_rates' => [80]]))->assertSessionHasNoErrors();
        $user = User::query()->where('name', 'Sanatan Records')->firstOrFail();
        $base = Label::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($super)->get('/v2/admin/users/'.$user->id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('managedUser.full_name', 'Ramesh Kumar'));

        $resp = $this->actingAs($super)->patch('/v2/admin/users/'.$user->id, [
            'name' => 'Sanatan Records',
            'full_name' => 'Ramesh K. Sharma',
            'email' => $user->email,
            'role' => 'label',
            'account_status' => $user->account_status,
            'kyc_status' => 'pending',
            'label_ids' => [$base->id],
            'label_revenue_rates' => [(string) $base->id => 80],
            'new_labels' => ['Edit Added Label'],
            'new_label_rates' => [55],
            'permissions' => [],
        ]);
        $resp->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('Ramesh K. Sharma', $user->fresh()->full_name);
        $this->assertSame(55.0, (float) Label::query()->where('user_id', $user->id)->where('name', 'Edit Added Label')->value('revenue_share_percentage'));
        $this->assertSame(80.0, (float) $base->fresh()->revenue_share_percentage);
    }

    public function test_create_page_marks_owned_labels(): void
    {
        $super = $this->super();
        $owner = User::factory()->create(['role' => 'label']);
        Label::factory()->create(['name' => 'Taken Label', 'user_id' => $owner->id]);

        $this->actingAs($super)->get('/v2/admin/users/create')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('labels', fn ($labels) => collect($labels)->firstWhere('name', 'Taken Label')['user_id'] === $owner->id));
    }
}
