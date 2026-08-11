<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\Finance\WalletAccount;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelTeamWalletAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'label'): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    private function label(User $owner): Label
    {
        return Label::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'name' => 'Wallet Context Label',
            'slug' => 'wallet-context-' . Str::lower(
                Str::random(8)
            ),
            'status' => 'active',
        ]);
    }

    private function teamMember(
        Label $label,
        User $owner,
        User $teamUser,
        array $permissions,
        string $level = 'advanced'
    ): LabelTeamMember {
        return LabelTeamMember::query()->create([
            'label_id' => $label->id,
            'user_id' => $teamUser->id,
            'created_by' => $owner->id,
            'permission_level' => $level,
            'scope_level' => 'entire_label',
            'status' => 'active',
            'permissions' => $permissions,
        ]);
    }

    public function test_label_owner_wallet_still_works(): void
    {
        $owner = $this->user();
        $this->label($owner);

        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'currency' => 'INR',
            'available_balance' => 12500,
            'pending_balance' => 2500,
            'lifetime_credits' => 15000,
            'lifetime_debits' => 0,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get('/v2/wallet');

        $response->assertOk();

        $this->assertDatabaseCount(
            'wallets',
            1
        );
    }

    public function test_authorized_team_user_uses_owner_wallet(): void
    {
        $owner = $this->user();
        $label = $this->label($owner);

        $teamUser = $this->user('artist');

        $this->teamMember(
            $label,
            $owner,
            $teamUser,
            ['wallet.view']
        );

        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'currency' => 'INR',
            'available_balance' => 50000,
            'pending_balance' => 5000,
            'lifetime_credits' => 55000,
            'lifetime_debits' => 0,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($teamUser)
            ->get('/v2/wallet');

        $response->assertOk();

        /*
         * Critical invariant:
         * viewing the label wallet must not create
         * a separate wallet for the team actor.
         */
        $this->assertDatabaseCount(
            'wallets',
            1
        );

        $this->assertDatabaseHas(
            'wallets',
            [
                'user_id' => $owner->id,
                'available_balance' => 50000,
            ]
        );

        $this->assertDatabaseMissing(
            'wallets',
            [
                'user_id' => $teamUser->id,
            ]
        );
    }

    public function test_team_user_without_wallet_permission_is_denied(): void
    {
        $owner = $this->user();
        $label = $this->label($owner);

        $teamUser = $this->user('artist');

        $this->teamMember(
            $label,
            $owner,
            $teamUser,
            []
        );

        $response = $this
            ->actingAs($teamUser)
            ->get('/v2/wallet');

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'wallets',
            [
                'user_id' => $teamUser->id,
            ]
        );
    }

    public function test_suspended_team_user_cannot_use_label_wallet(): void
    {
        $owner = $this->user();
        $label = $this->label($owner);

        $teamUser = $this->user('artist');

        $member = $this->teamMember(
            $label,
            $owner,
            $teamUser,
            ['wallet.view']
        );

        $member->update([
            'status' => 'suspended',
        ]);

        $response = $this
            ->actingAs($teamUser)
            ->get('/v2/wallet');

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'wallets',
            [
                'user_id' => $teamUser->id,
            ]
        );
    }
}
