<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReleaseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function label(User $creator): Label
    {
        return Label::factory()->create([
            'created_by' => $creator->id,
        ]);
    }

    private function artist(
        User $creator,
        Label $label
    ): Artist {
        return Artist::factory()->create([
            'label_id' => $label->id,
            'created_by' => $creator->id,
            'account_status' => 'active',
        ]);
    }

    private function assignLabel(
        User $admin,
        Label $label,
        User $superAdmin
    ): void {
        DB::table('admin_label_assignments')->insert([
            'user_id' => $admin->id,
            'label_id' => $label->id,
            'assignment_role' => 'manager',
            'can_view' => true,
            'can_edit' => true,
            'can_manage_releases' => true,
            'can_manage_team' => false,
            'can_manage_splits' => false,
            'assigned_by' => $superAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignArtist(
        User $admin,
        Artist $artist,
        User $superAdmin
    ): void {
        DB::table('admin_artist_assignments')->insert([
            'user_id' => $admin->id,
            'artist_id' => $artist->id,
            'assignment_role' => 'manager',
            'can_view' => true,
            'can_edit' => true,
            'can_manage_releases' => true,
            'can_manage_team' => false,
            'can_manage_splits' => false,
            'assigned_by' => $superAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function payload(
        Artist $artist,
        Label $label,
        string $title
    ): array {
        return [
            'catalog_number' =>
                'TEST-'.strtoupper(fake()->bothify('????####')),

            'release_type' => 'single',
            'title' => $title,
            'version' => null,

            'artist_id' => $artist->id,
            'label_id' => $label->id,

            'primary_artists' => [
                [
                    'name' => $artist->stage_name,
                    'artist_id' => $artist->id,
                ],
            ],

            'featuring_artists' => [],

            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',

            'generate_upc' => true,

            'digital_release_date' =>
                now()->addDays(14)->toDateString(),

            'copyright_owner' =>
                'Mixx Tune Entertainment',

            'copyright_year' =>
                (int) now()->format('Y'),

            'phonographic_owner' =>
                'Mixx Tune Entertainment',

            'phonographic_year' =>
                (int) now()->format('Y'),
        ];
    }

    public function test_admin_can_create_for_assigned_label(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Assigned Label Release'
                )
            );

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('releases', [
            'title' => 'Assigned Label Release',
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_create_for_directly_assigned_artist(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $this->assignArtist(
            $admin,
            $artist,
            $super
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Assigned Artist Release'
                )
            );

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('releases', [
            'title' => 'Assigned Artist Release',
            'artist_id' => $artist->id,
            'label_id' => $label->id,
        ]);
    }

    public function test_admin_cannot_create_for_unassigned_catalog(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $response = $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Unauthorized Release'
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('releases', [
            'title' => 'Unauthorized Release',
        ]);
    }

    public function test_admin_cannot_mix_artist_with_unrelated_label(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $labelA = $this->label($super);
        $labelB = $this->label($super);

        $artist = $this->artist(
            $super,
            $labelA
        );

        $this->assignArtist(
            $admin,
            $artist,
            $super
        );

        $this->assignLabel(
            $admin,
            $labelB,
            $super
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $labelB,
                    'Mixed Ownership Release'
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('releases', [
            'title' => 'Mixed Ownership Release',
        ]);
    }

    public function test_admin_can_create_for_assigned_label_without_artist_account(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        /*
         * No artist_id is supplied intentionally.
         * Primary Artist below is metadata only and does
         * not need an Artist login account.
         */
        $payload = [
            'catalog_number' =>
                'TEST-'.strtoupper(fake()->bothify('????####')),

            'release_type' => 'single',
            'title' => 'Label Only Operator Release',

            'artist_id' => null,
            'label_id' => $label->id,

            'primary_artists' => [
                [
                    'name' => 'Independent Primary Artist',
                    'artist_id' => null,
                ],
            ],

            'featuring_artists' => [],

            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',

            'generate_upc' => true,

            'digital_release_date' =>
                now()->addDays(14)->toDateString(),

            'copyright_owner' =>
                'Mixx Tune Entertainment',

            'copyright_year' =>
                (int) now()->format('Y'),

            'phonographic_owner' =>
                'Mixx Tune Entertainment',

            'phonographic_year' =>
                (int) now()->format('Y'),
        ];

        $response = $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $payload
            );

        $response->assertSessionHasNoErrors();

        $release = \App\Models\Distribution\Release::query()
            ->where(
                'title',
                'Label Only Operator Release'
            )
            ->firstOrFail();

        $this->assertNull(
            $release->artist_id,
            'Operator release must allow no Artist account.'
        );

        $this->assertSame(
            (int) $label->id,
            (int) $release->label_id
        );

        $this->assertSame(
            'Independent Primary Artist',
            $release->primary_artist_name
        );

        $this->assertDatabaseHas('releases', [
            'id' => $release->id,
            'artist_id' => null,
            'label_id' => $label->id,
            'created_by' => $admin->id,
        ]);
    }


    public function test_super_admin_can_create_for_any_catalog(): void
    {
        $super = $this->user('super_admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $response = $this
            ->actingAs($super)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Super Admin Release'
                )
            );

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('releases', [
            'title' => 'Super Admin Release',
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'created_by' => $super->id,
        ]);
    }

    public function test_release_creator_can_see_own_draft_in_index(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Creator Private Draft'
                )
            )
            ->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($admin)
            ->get(route('v2.releases.index'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component('V2/Releases/Index')
                        ->has(
                            'releases.data',
                            fn (Assert $releases) =>
                                $releases
                                    ->where(
                                        '0.title',
                                        'Creator Private Draft'
                                    )
                                    ->etc()
                        )
            );
    }


    public function test_label_owner_cannot_see_admin_created_draft_in_index(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $labelUser = $this->user('label');

        $label = Label::factory()->create([
            'created_by' => $labelUser->id,
        ]);

        $artist = $this->artist(
            $labelUser,
            $label
        );

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Admin Draft Hidden From Label'
                )
            )
            ->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($labelUser)
            ->get(route('v2.releases.index'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component('V2/Releases/Index')
                        ->where(
                            'releases.data',
                            fn ($releases) =>
                                ! collect($releases)
                                    ->pluck('title')
                                    ->contains(
                                        'Admin Draft Hidden From Label'
                                    )
                        )
                        ->etc()
            );
    }


    public function test_super_admin_cannot_see_another_users_draft_in_index(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        $this
            ->actingAs($admin)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Admin Draft Hidden From Super'
                )
            )
            ->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($super)
            ->get(route('v2.releases.index'));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component('V2/Releases/Index')
                        ->where(
                            'releases.data',
                            fn ($releases) =>
                                ! collect($releases)
                                    ->pluck('title')
                                    ->contains(
                                        'Admin Draft Hidden From Super'
                                    )
                        )
                        ->etc()
            );
    }


    public function test_review_queue_cannot_be_forced_to_show_drafts(): void
    {
        $super = $this->user('super_admin');

        $label = $this->label($super);
        $artist = $this->artist($super, $label);

        $this
            ->actingAs($super)
            ->post(
                route('v2.releases.store'),
                $this->payload(
                    $artist,
                    $label,
                    'Private Draft Review Guard'
                )
            )
            ->assertSessionHasNoErrors();

        $response = $this
            ->actingAs($super)
            ->get(
                route(
                    'v2.admin.release-reviews.index',
                    ['status' => 'draft']
                )
            );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V2/Admin/ReleaseReviews/Index'
                        )
                        ->where(
                            'filters.status',
                            'submitted'
                        )
                        ->where(
                            'releases.data',
                            fn ($releases) =>
                                ! collect($releases)
                                    ->pluck('title')
                                    ->contains(
                                        'Private Draft Review Guard'
                                    )
                        )
                        ->etc()
            );
    }


}
