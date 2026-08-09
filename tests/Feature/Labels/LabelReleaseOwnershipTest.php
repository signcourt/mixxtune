<?php

namespace Tests\Feature\Labels;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LabelReleaseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function labelUser(
        string $name = 'Master'
    ): array {
        $user = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'parent_label_id' => null,
            'label_type' => 'master',
        ]);

        return [$user, $label];
    }

    private function child(
        Label $master,
        string $name = 'Child'
    ): Label {
        return Label::factory()->create([
            'user_id' => null,
            'parent_label_id' => $master->id,
            'label_type' => 'sub_label',
            'name' => $name,
        ]);
    }

    private function releaseFor(
        Label $label,
        User $creator,
        string $title,
        string $status = 'submitted'
    ): Release {
        $artist = Artist::factory()->create([
            'label_id' => $label->id,
            'created_by' => $creator->id,
        ]);

        return Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'created_by' => $creator->id,
            'title' => $title,
            'status' => $status,
        ]);
    }

    public function test_master_can_manage_direct_child_release(): void
    {
        [$user, $master] =
            $this->labelUser();

        $child = $this->child($master);

        $release = $this->releaseFor(
            $child,
            $user,
            'Direct Child Release',
            'changes_requested'
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'v2.releases.edit',
                    $release
                )
            )
            ->assertSuccessful();
    }

    public function test_master_cannot_manage_unrelated_label_release(): void
    {
        [$user] =
            $this->labelUser('Master A');

        [$otherUser, $otherMaster] =
            $this->labelUser('Master B');

        $release = $this->releaseFor(
            $otherMaster,
            $otherUser,
            'Foreign Release',
            'changes_requested'
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'v2.releases.edit',
                    $release
                )
            )
            ->assertForbidden();
    }

    public function test_master_cannot_manage_grandchild_release(): void
    {
        [$user, $master] =
            $this->labelUser();

        $child = $this->child(
            $master,
            'Child'
        );

        $grandchild = Label::factory()->create([
            'user_id' => null,
            'parent_label_id' => $child->id,
            'label_type' => 'sub_label',
            'name' => 'Grandchild',
        ]);

        $release = $this->releaseFor(
            $grandchild,
            $user,
            'Grandchild Release',
            'changes_requested'
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'v2.releases.edit',
                    $release
                )
            )
            ->assertForbidden();
    }

    public function test_label_index_excludes_grandchild_catalogue(): void
    {
        [$user, $master] =
            $this->labelUser();

        $child = $this->child(
            $master,
            'Child'
        );

        $grandchild = Label::factory()->create([
            'user_id' => null,
            'parent_label_id' => $child->id,
            'label_type' => 'sub_label',
            'name' => 'Grandchild',
        ]);

        $this->releaseFor(
            $master,
            $user,
            'Master Catalogue',
            'submitted'
        );

        $this->releaseFor(
            $child,
            $user,
            'Child Catalogue',
            'submitted'
        );

        $this->releaseFor(
            $grandchild,
            $user,
            'Forbidden Grandchild Catalogue',
            'submitted'
        );

        $this
            ->actingAs($user)
            ->get(route('v2.releases.index'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->has('releases.data', 2)
                        ->where(
                            'releases.data.0.title',
                            fn ($value) =>
                                in_array(
                                    $value,
                                    [
                                        'Master Catalogue',
                                        'Child Catalogue',
                                    ],
                                    true
                                )
                        )
                        ->where(
                            'releases.data.1.title',
                            fn ($value) =>
                                in_array(
                                    $value,
                                    [
                                        'Master Catalogue',
                                        'Child Catalogue',
                                    ],
                                    true
                                )
                        )
            );
    }
}
