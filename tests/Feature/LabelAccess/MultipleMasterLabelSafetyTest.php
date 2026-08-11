<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleMasterLabelSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_label_is_deterministic_for_legacy_multiple_rows(): void
    {
        $user = User::factory()->create([
            'role' => 'label',
        ]);

        $first = Label::factory()->create([
            'user_id' => $user->id,
            'parent_label_id' => null,
        ]);

        Label::factory()->create([
            'user_id' => $user->id,
            'parent_label_id' => null,
        ]);

        $service = app(
            LabelTeamAccessService::class
        );

        $resolved = $service->ownedLabel($user);

        $this->assertNotNull($resolved);
        $this->assertSame(
            $first->id,
            $resolved->id
        );
    }

    public function test_child_label_owner_resolves_own_child_label(): void
    {
        $masterOwner = User::factory()->create([
            'role' => 'label',
        ]);

        $master = Label::factory()->create([
            'user_id' => $masterOwner->id,
            'parent_label_id' => null,
        ]);

        $childOwner = User::factory()->create([
            'role' => 'label',
        ]);

        $child = Label::factory()->create([
            'user_id' => $childOwner->id,
            'parent_label_id' => $master->id,
        ]);

        $service = app(
            LabelTeamAccessService::class
        );

        $resolved = $service->ownedLabel(
            $childOwner
        );

        $this->assertNotNull($resolved);
        $this->assertSame(
            $child->id,
            $resolved->id
        );

        $this->assertSame(
            $master->id,
            $resolved->parent_label_id
        );
    }

    public function test_child_label_owner_cannot_pass_master_owner_assertion(): void
    {
        $masterOwner = User::factory()->create([
            'role' => 'label',
        ]);

        $master = Label::factory()->create([
            'user_id' => $masterOwner->id,
            'parent_label_id' => null,
        ]);

        $childOwner = User::factory()->create([
            'role' => 'label',
        ]);

        Label::factory()->create([
            'user_id' => $childOwner->id,
            'parent_label_id' => $master->id,
        ]);

        $service = app(
            LabelTeamAccessService::class
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->assertOwner(
            $childOwner
        );
    }
}
