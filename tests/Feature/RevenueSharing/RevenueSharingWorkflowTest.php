<?php

namespace Tests\Feature\RevenueSharing;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueSharingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function labelUser(string $name = 'Master User'): User
    {
        return User::factory()->create([
            'name' => $name,
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function masterLabel(
        User $user,
        string $name = 'Master Label'
    ): Label {
        return Label::factory()->create([
            'user_id' => $user->id,
            'parent_label_id' => null,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function childLabel(
        Label $master,
        ?User $user = null,
        string $name = 'Child Label'
    ): Label {
        return Label::factory()->create([
            'user_id' => $user?->id,
            'parent_label_id' => $master->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function artist(
        Label $label,
        string $name = 'Direct Artist'
    ): Artist {
        return Artist::factory()->create([
            'label_id' => $label->id,
            'stage_name' => $name,
            'account_status' => 'active',
        ]);
    }

    public function test_master_label_can_open_revenue_sharing_page(): void
    {
        $user = $this->labelUser();
        $this->masterLabel($user);

        $this->actingAs($user)
            ->get('/v2/label/revenue-sharing')
            ->assertOk()
            ->assertInertia(
                fn ($page) =>
                    $page
                        ->component('V2/Label/RevenueSharing/Index')
                        ->has('master')
                        ->has('beneficiaries')
            );
    }

    public function test_page_contains_only_direct_sub_labels_and_direct_artists(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);

        $directChild = $this->childLabel(
            $master,
            null,
            'Direct Child'
        );

        $directArtist = $this->artist(
            $master,
            'Direct Artist'
        );

        $nestedArtist = $this->artist(
            $directChild,
            'Nested Artist'
        );

        $otherUser = $this->labelUser('Other Master User');
        $otherMaster = $this->masterLabel(
            $otherUser,
            'Other Master'
        );

        $otherChild = $this->childLabel(
            $otherMaster,
            null,
            'Other Child'
        );

        $response = $this->actingAs($user)
            ->get('/v2/label/revenue-sharing');

        $response->assertOk();

        $beneficiaries = collect(
            $response->viewData('page')['props']['beneficiaries']
        );

        $this->assertTrue(
            $beneficiaries->contains(
                fn ($item) =>
                    $item['type'] === 'label'
                    && $item['id'] === $directChild->id
            )
        );

        $this->assertTrue(
            $beneficiaries->contains(
                fn ($item) =>
                    $item['type'] === 'artist'
                    && $item['id'] === $directArtist->id
            )
        );

        $this->assertFalse(
            $beneficiaries->contains(
                fn ($item) =>
                    $item['type'] === 'artist'
                    && $item['id'] === $nestedArtist->id
            )
        );

        $this->assertFalse(
            $beneficiaries->contains(
                fn ($item) =>
                    $item['type'] === 'label'
                    && $item['id'] === $otherChild->id
            )
        );
    }

    public function test_master_can_save_direct_child_label_share(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);
        $child = $this->childLabel($master);

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/label/{$child->id}",
                [
                    'revenue_share_percent' => 80,
                    'show_revenue_share' => true,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(
            'label_revenue_shares',
            [
                'master_label_id' => $master->id,
                'beneficiary_type' => 'label',
                'beneficiary_id' => $child->id,
                'revenue_share_percent' => 80,
                'show_revenue_share' => 1,
                'is_active' => 1,
            ]
        );
    }

    public function test_master_can_save_direct_artist_share(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);
        $artist = $this->artist($master);

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/artist/{$artist->id}",
                [
                    'revenue_share_percent' => 75,
                    'show_revenue_share' => false,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(
            'label_revenue_shares',
            [
                'master_label_id' => $master->id,
                'beneficiary_type' => 'artist',
                'beneficiary_id' => $artist->id,
                'revenue_share_percent' => 75,
                'show_revenue_share' => 0,
                'is_active' => 1,
            ]
        );
    }

    public function test_master_cannot_assign_share_to_another_masters_child_label(): void
    {
        $user = $this->labelUser();
        $this->masterLabel($user);

        $otherUser = $this->labelUser('Other User');
        $otherMaster = $this->masterLabel(
            $otherUser,
            'Other Master'
        );

        $otherChild = $this->childLabel(
            $otherMaster,
            null,
            'Other Child'
        );

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/label/{$otherChild->id}",
                [
                    'revenue_share_percent' => 80,
                    'show_revenue_share' => true,
                ]
            )
            ->assertSessionHasErrors('label');

        $this->assertDatabaseMissing(
            'label_revenue_shares',
            [
                'beneficiary_type' => 'label',
                'beneficiary_id' => $otherChild->id,
            ]
        );
    }

    public function test_master_cannot_assign_share_to_another_masters_artist(): void
    {
        $user = $this->labelUser();
        $this->masterLabel($user);

        $otherUser = $this->labelUser('Other User');
        $otherMaster = $this->masterLabel(
            $otherUser,
            'Other Master'
        );

        $otherArtist = $this->artist(
            $otherMaster,
            'Other Artist'
        );

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/artist/{$otherArtist->id}",
                [
                    'revenue_share_percent' => 70,
                    'show_revenue_share' => true,
                ]
            )
            ->assertSessionHasErrors('artist');

        $this->assertDatabaseMissing(
            'label_revenue_shares',
            [
                'beneficiary_type' => 'artist',
                'beneficiary_id' => $otherArtist->id,
            ]
        );
    }

    public function test_sub_label_cannot_open_revenue_sharing_page(): void
    {
        $masterUser = $this->labelUser('Master User');
        $master = $this->masterLabel($masterUser);

        $childUser = $this->labelUser('Child User');

        $this->childLabel(
            $master,
            $childUser,
            'Child Label'
        );

        $this->actingAs($childUser)
            ->get('/v2/label/revenue-sharing')
            ->assertForbidden();
    }

    public function test_sub_label_cannot_update_revenue_share_directly(): void
    {
        $masterUser = $this->labelUser('Master User');
        $master = $this->masterLabel($masterUser);

        $childUser = $this->labelUser('Child User');

        $child = $this->childLabel(
            $master,
            $childUser,
            'Child Label'
        );

        $artist = $this->artist(
            $child,
            'Child Artist'
        );

        $this->actingAs($childUser)
            ->patch(
                "/v2/label/revenue-sharing/artist/{$artist->id}",
                [
                    'revenue_share_percent' => 90,
                    'show_revenue_share' => true,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'label_revenue_shares',
            [
                'master_label_id' => $child->id,
                'beneficiary_type' => 'artist',
                'beneficiary_id' => $artist->id,
            ]
        );
    }

    public function test_master_cannot_toggle_another_masters_share(): void
    {
        $user = $this->labelUser();
        $this->masterLabel($user);

        $otherUser = $this->labelUser('Other User');
        $otherMaster = $this->masterLabel(
            $otherUser,
            'Other Master'
        );

        $otherChild = $this->childLabel($otherMaster);

        $share = LabelRevenueShare::query()->create([
            'master_label_id' => $otherMaster->id,
            'beneficiary_type' => 'label',
            'beneficiary_id' => $otherChild->id,
            'revenue_share_percent' => 80,
            'show_revenue_share' => true,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/{$share->id}/toggle"
            )
            ->assertForbidden();

        $this->assertTrue(
            (bool) $share->fresh()->is_active
        );
    }

    public function test_revenue_share_percentage_must_be_between_zero_and_one_hundred(): void
    {
        $user = $this->labelUser();
        $master = $this->masterLabel($user);
        $child = $this->childLabel($master);

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/label/{$child->id}",
                [
                    'revenue_share_percent' => 101,
                    'show_revenue_share' => true,
                ]
            )
            ->assertSessionHasErrors(
                'revenue_share_percent'
            );

        $this->actingAs($user)
            ->patch(
                "/v2/label/revenue-sharing/label/{$child->id}",
                [
                    'revenue_share_percent' => -1,
                    'show_revenue_share' => true,
                ]
            )
            ->assertSessionHasErrors(
                'revenue_share_percent'
            );

        $this->assertDatabaseMissing(
            'label_revenue_shares',
            [
                'master_label_id' => $master->id,
                'beneficiary_type' => 'label',
                'beneficiary_id' => $child->id,
            ]
        );
    }
}
