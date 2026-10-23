<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_USER_REQUIRED_FIELDS_V1 */
class UserRequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function account(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function labelPayload(array $without = []): array
    {
        $data = [
            'name' => 'Form Test Owner',
            'email' => 'form.owner@example.test',
            'role' => 'label',
            'send_invitation' => false,
            'revenue_share_percentage' => 80,
            'country' => 'India',
            'country_code' => 'IN',
            'state_code' => 'DL',
            'state' => 'Delhi',
            'city' => 'Delhi',
            'postal_code' => '110001',
            'address_line_1' => 'Generated Street 1',
            'account_holder_name' => 'Form Test Owner',
            'bank_account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'ifsc_code' => 'TEST0000001',
            'pan_number' => 'ABCDE1234F',
            'new_labels' => ['Required Fields Test Label'],
        ];

        foreach ($without as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    private function saveFields(User $admin, array $fields)
    {
        return $this->actingAs($admin)->postJson('/admin/user-form/required-fields', ['fields' => $fields]);
    }

    public function test_only_super_admin_can_change_required_fields(): void
    {
        $this->saveFields($this->account('admin'), ['label' => ['gst_number' => true]])
            ->assertForbidden();

        $this->saveFields($this->account('super_admin'), ['label' => ['gst_number' => true, 'name' => false, 'made_up' => true]])
            ->assertOk()
            ->assertJsonPath('requiredFields.label.gst_number', true)
            ->assertJsonPath('requiredFields.label.pan_number', true)
            ->assertJsonMissingPath('requiredFields.label.name')
            ->assertJsonMissingPath('requiredFields.label.made_up');
    }

    public function test_default_settings_keep_pan_required_for_label(): void
    {
        $this->actingAs($this->account('super_admin'))
            ->post('/admin/users', $this->labelPayload(['pan_number']))
            ->assertSessionHasErrors('pan_number');
    }

    public function test_field_switched_off_is_no_longer_demanded(): void
    {
        $admin = $this->account('super_admin');
        $this->saveFields($admin, ['label' => ['pan_number' => false]])->assertOk();

        $this->actingAs($admin)
            ->post('/admin/users', $this->labelPayload(['pan_number']))
            ->assertSessionDoesntHaveErrors('pan_number');
    }

    public function test_field_switched_on_is_enforced_only_for_that_role(): void
    {
        $admin = $this->account('super_admin');
        $this->saveFields($admin, ['label' => ['gst_number' => true]])->assertOk();

        $this->actingAs($admin)
            ->post('/admin/users', $this->labelPayload())
            ->assertSessionHasErrors('gst_number');

        $artist = $this->labelPayload();
        $artist['role'] = 'artist';
        unset($artist['new_labels']);

        $this->actingAs($admin)
            ->post('/admin/users', $artist)
            ->assertSessionDoesntHaveErrors('gst_number');
    }

    public function test_create_page_receives_required_field_settings(): void
    {
        $this->actingAs($this->account('super_admin'))
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Admin/Users/Create')
                ->where('requiredFields.label.pan_number', true)
                ->where('requiredFields.label.gst_number', false)
                ->where('canEditRequiredFields', true)
                ->has('requiredFieldOptions.fields.label'));
    }

    public function test_manager_form_opens_only_from_managers_link(): void
    {
        $admin = $this->account('super_admin');

        $this->actingAs($admin)
            ->get('/admin/users/create?type=manager')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('accountType', 'manager'));

        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('accountType', 'user'));
    }
}
