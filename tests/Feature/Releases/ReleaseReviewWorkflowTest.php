<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\DistributionStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createArtistContext(
        bool $complete = true
    ): array {
        $user = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $user->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $user->id,
            'label_id' => $label->id,
            'created_by' => $user->id,
        ]);

        $store = DistributionStore::query()->create([
            'name' => 'Review Store '.uniqid(),
            'slug' => 'review-store-'.uniqid(),
            'is_active' => true,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,

            'catalog_number' =>
                'MXT-REV-'.uniqid(),

            'release_type' => 'single',
            'title' => 'Review Workflow Release',

            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,

            'primary_artists' => [
                [
                    'name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,
                ],
            ],

            'language' => 'Hindi',
            'primary_genre' => 'Devotional',

            'digital_release_date' =>
                now()->addDays(14)->toDateString(),

            'artwork_path' =>
                $complete
                    ? 'releases/artwork/review-cover.jpg'
                    : null,

            'stores' =>
                $complete
                    ? [$store->id]
                    : [],

            'worldwide' => true,
            'territories' => [],
            'release_timezone' => 'Asia/Kolkata',
            'pre_order' => false,

            'status' => 'draft',
            'wizard_step' => 4,
            'completion_percentage' => 90,

            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        if ($complete) {
            Track::factory()
                ->withAudio()
                ->create([
                    'release_id' => $release->id,

                    'title' =>
                        'Review Workflow Track',

                    'primary_artist_name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,

                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
        }

        return [
            $user,
            $label,
            $artist,
            $release,
            $store,
        ];
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function checklistUrl(
        Release $release
    ): string {
        return route(
            'v2.releases.submission-checklist',
            $release
        );
    }

    private function submitUrl(
        Release $release
    ): string {
        return route(
            'v2.releases.submit',
            $release
        );
    }

    private function approveUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.release-reviews.approve',
            $release
        );
    }

    private function rejectUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.release-reviews.reject',
            $release
        );
    }

    private function requestChangesUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.release-reviews.request-changes',
            $release
        );
    }

    private function startProcessingUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.release-reviews.start-processing',
            $release
        );
    }

    private function submitRelease(
        User $user,
        Release $release
    ): Release {
        $this
            ->actingAs($user)
            ->post(
                $this->submitUrl($release)
            )
            ->assertRedirect(
                route('v2.releases.index')
            );

        return $release->fresh();
    }

    public function test_ready_release_checklist_returns_true(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->checklistUrl($release)
            );

        $response
            ->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath(
                'checks.metadata',
                true
            )
            ->assertJsonPath(
                'checks.artists',
                true
            )
            ->assertJsonPath(
                'checks.tracks',
                true
            )
            ->assertJsonPath(
                'checks.distribution',
                true
            );

        $this->assertSame(
            [],
            $response->json('errors')
        );
    }

    public function test_incomplete_release_checklist_returns_errors(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createArtistContext(false);

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->checklistUrl($release)
            );

        $response
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath(
                'checks.metadata',
                false
            )
            ->assertJsonPath(
                'checks.tracks',
                false
            );

        $this->assertArrayHasKey(
            'release.artwork',
            $response->json('errors')
        );

        $this->assertArrayHasKey(
            'tracks.empty',
            $response->json('errors')
        );
    }

    public function test_incomplete_release_cannot_be_submitted(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createArtistContext(false);

        $response = $this
            ->actingAs($user)
            ->postJson(
                $this->submitUrl($release)
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'release.artwork',
                'tracks.empty',
            ]);

        $release->refresh();

        $this->assertSame(
            'draft',
            $release->status
        );

        $this->assertNull(
            $release->submitted_at
        );
    }

    public function test_artist_can_submit_complete_release(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $response = $this
            ->actingAs($user)
            ->post(
                $this->submitUrl($release)
            );

        $response->assertRedirect(
            route('v2.releases.index')
        );

        $release->refresh();

        $this->assertSame(
            'submitted',
            $release->status
        );

        $this->assertNotNull(
            $release->submitted_at
        );

        $this->assertSame(
            5,
            $release->wizard_step
        );

        $this->assertSame(
            100,
            $release->completion_percentage
        );

        $this->assertDatabaseHas(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'draft',
                'new_status' => 'submitted',
                'changed_by' => $user->id,
            ]
        );
    }

    public function test_other_artist_cannot_submit_release(): void
    {
        [
            ,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        [
            $otherUser,
        ] = $this->createArtistContext();

        $response = $this
            ->actingAs($otherUser)
            ->postJson(
                $this->submitUrl($release)
            );

        $response->assertForbidden();

        $this->assertSame(
            'draft',
            $release->fresh()->status
        );
    }

    public function test_artist_cannot_edit_after_submission(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $user,
            $release
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    'v2.releases.update',
                    $release
                ),
                []
            );

        $response->assertForbidden();

        $this->assertSame(
            'submitted',
            $release->fresh()->status
        );
    }

    public function test_super_admin_can_approve_submitted_release(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->approveUrl($release),
                [
                    'remarks' =>
                        'Metadata and audio approved.',
                ]
            );

        $response
            ->assertRedirect(route('v2.admin.release-reviews.index'))
            ->assertSessionHas(
                'success',
                'Release approved successfully.'
            );

        $release->refresh();

        $this->assertSame(
            'approved',
            $release->status
        );


        $release->refresh();

        $this->assertSame(
            'approved',
            $release->status
        );

        $this->assertNotNull(
            $release->approved_at
        );

        $this->assertSame(
            $admin->id,
            $release->approved_by
        );

        $this->assertDatabaseHas(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'submitted',
                'new_status' => 'approved',
                'changed_by' => $admin->id,
            ]
        );
    }

    public function test_super_admin_can_reject_submitted_release(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $reason =
            'Artwork text is too close to the edge.';

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->rejectUrl($release),
                [
                    'reason' => $reason,
                ]
            );

        $response
            ->assertRedirect(route('v2.admin.release-reviews.index'))
            ->assertSessionHas(
                'success',
                'Release rejected successfully.'
            );

        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );


        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );

        $this->assertSame(
            $reason,
            $release->rejection_reason
        );

        $this->assertNotNull(
            $release->rejected_at
        );

        $this->assertSame(
            $admin->id,
            $release->rejected_by
        );
    }

    public function test_rejection_reason_is_required(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->rejectUrl($release),
                [
                    'reason' => '',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'reason'
            );

        $this->assertSame(
            'submitted',
            $release->fresh()->status
        );
    }

    public function test_super_admin_can_request_changes(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $notes =
            'Please correct the primary genre and resubmit.';

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->requestChangesUrl($release),
                [
                    'notes' => $notes,
                ]
            );

        $response
            ->assertRedirect(route('v2.admin.release-reviews.index'))
            ->assertSessionHas(
                'success',
                'Changes requested successfully.'
            );

        $release->refresh();

        $this->assertSame(
            'changes_requested',
            $release->status
        );


        $release->refresh();

        $this->assertSame(
            'changes_requested',
            $release->status
        );

        $this->assertSame(
            $notes,
            $release->review_notes
        );

        $this->assertDatabaseHas(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'submitted',
                'new_status' =>
                    'changes_requested',
                'changed_by' => $admin->id,
            ]
        );
    }

    public function test_artist_can_edit_and_resubmit_changes_requested_release(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->requestChangesUrl($release),
                [
                    'notes' =>
                        'Please correct the release metadata.',
                ]
            )
            ->assertRedirect(
                route('v2.admin.release-reviews.index')
            );

        $release->refresh();

        $this->assertSame(
            'changes_requested',
            $release->status
        );

        $updatedTitle =
            'Review Workflow Release Corrected';

        $payload = [
            'catalog_number' =>
                $release->catalog_number,
            'release_type' =>
                $release->release_type,
            'title' =>
                $updatedTitle,
            'version' =>
                $release->version,
            'artist_id' =>
                $release->artist_id,
            'label_id' =>
                $release->label_id,
            'primary_artist_name' =>
                $release->primary_artist_name,
            'primary_artists' =>
                $release->primary_artists,
            'featuring_artists' =>
                $release->featuring_artists ?? [],
            'language' =>
                $release->language,
            'primary_genre' =>
                $release->primary_genre,
            'sub_genre' =>
                $release->sub_genre ?? 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' =>
                $release->digital_release_date,
            'copyright_owner' =>
                $release->copyright_owner,
            'copyright_year' =>
                $release->copyright_year,
            'phonographic_owner' =>
                $release->phonographic_owner,
            'phonographic_year' =>
                $release->phonographic_year,
        ];

        $this
            ->actingAs($artistUser)
            ->patch(
                route(
                    'v2.releases.update',
                    $release
                ),
                $payload
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'v2.releases.edit',
                    $release,
                    absolute: false
                )
            );

        $release->refresh();

        $this->assertSame(
            'changes_requested',
            $release->status
        );

        $this->assertSame(
            $updatedTitle,
            $release->title
        );

        $this
            ->actingAs($artistUser)
            ->post(
                $this->submitUrl($release)
            )
            ->assertRedirect(
                route('v2.releases.index')
            );

        $release->refresh();

        $this->assertSame(
            'submitted',
            $release->status
        );

        $this->assertNotNull(
            $release->submitted_at
        );

        $this->assertDatabaseHas(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'changes_requested',
                'new_status' => 'submitted',
                'changed_by' => $artistUser->id,
            ]
        );
    }

    public function test_artist_cannot_edit_rejected_release(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $reason =
            'Release does not meet delivery requirements.';

        $this
            ->actingAs($admin)
            ->postJson(
                $this->rejectUrl($release),
                [
                    'reason' => $reason,
                ]
            )
            ->assertRedirect(
                route('v2.admin.release-reviews.index')
            );

        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );

        $originalTitle = $release->title;

        $this
            ->actingAs($artistUser)
            ->patchJson(
                route(
                    'v2.releases.update',
                    $release
                ),
                [
                    'title' =>
                        'Rejected Release Must Stay Locked',
                ]
            )
            ->assertForbidden();

        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );

        $this->assertSame(
            $originalTitle,
            $release->title
        );

        $this->assertSame(
            $reason,
            $release->rejection_reason
        );
    }

    public function test_artist_cannot_resubmit_rejected_release(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->rejectUrl($release),
                [
                    'reason' =>
                        'Release rejected as a final review decision.',
                ]
            )
            ->assertRedirect(
                route('v2.admin.release-reviews.index')
            );

        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );

        $this
            ->actingAs($artistUser)
            ->postJson(
                $this->submitUrl($release)
            )
            ->assertForbidden();

        $release->refresh();

        $this->assertSame(
            'rejected',
            $release->status
        );

        $this->assertNull(
            $release->approved_at
        );

        $this->assertDatabaseMissing(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'rejected',
                'new_status' => 'submitted',
            ]
        );
    }

    public function test_super_admin_can_start_processing_after_approval(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $admin = $this->createSuperAdmin();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->approveUrl($release),
                [
                    'remarks' =>
                        'Approved for distribution.',
                ]
            )
            ->assertRedirect(
                route('v2.admin.release-reviews.index')
            )
            ->assertSessionHas(
                'success',
                'Release approved successfully.'
            );

        $release->refresh();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->startProcessingUrl(
                    $release
                ),
                [
                    'remarks' =>
                        'DSP delivery processing started.',
                ]
            );

        $response
            ->assertRedirect(route('v2.admin.release-reviews.index'))
            ->assertSessionHas(
                'success',
                'Release processing started.'
            );

        $release->refresh();

        $this->assertSame(
            'processing',
            $release->status
        );


        $release->refresh();

        $this->assertSame(
            'processing',
            $release->status
        );

        $this->assertDatabaseHas(
            'release_status_logs',
            [
                'release_id' => $release->id,
                'old_status' => 'approved',
                'new_status' => 'processing',
                'changed_by' => $admin->id,
            ]
        );
    }

    public function test_draft_release_cannot_enter_processing(): void
    {
        [
            ,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $admin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->startProcessingUrl(
                    $release
                )
            );

        $response->assertStatus(422);

        $this->assertSame(
            'draft',
            $release->fresh()->status
        );
    }

    public function test_artist_cannot_perform_admin_review_actions(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        $release = $this->submitRelease(
            $artistUser,
            $release
        );

        $response = $this
            ->actingAs($artistUser)
            ->postJson(
                $this->approveUrl($release)
            );

        $response->assertForbidden();

        $this->assertSame(
            'submitted',
            $release->fresh()->status
        );
    }
}
