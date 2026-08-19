<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use App\Services\V2\AdminFinancialAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFinancialAccessServiceTest extends TestCase
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

    private function label(
        User $creator,
        ?User $owner = null
    ): Label {
        return Label::factory()->create([
            'created_by' => $creator->id,
            'user_id' => $owner?->id,
        ]);
    }

    private function artist(
        User $creator,
        Label $label,
        ?User $owner = null
    ): Artist {
        return Artist::factory()->create([
            'label_id' => $label->id,
            'user_id' => $owner?->id
                ?? User::factory()->create([
                    'role' => 'artist',
                    'account_status' => 'active',
                ])->id,
            'created_by' => $creator->id,
            'account_status' => 'active',
        ]);
    }

    private function assignLabel(
        User $admin,
        Label $label,
        User $super
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
            'assigned_by' => $super->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignArtist(
        User $admin,
        Artist $artist,
        User $super
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
            'assigned_by' => $super->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statement(
        ?Label $label = null,
        ?Artist $artist = null
    ): RoyaltyStatement {
        return RoyaltyStatement::query()->create([
            'public_id' => (string) Str::ulid(),
            'label_id' => $label?->id,
            'artist_id' => $artist?->id,
            'statement_month' => '2026-06-01',
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 100,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 900,
            'status' => 'available',
        ]);
    }

    public function test_admin_scope_contains_only_assigned_label(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);
        $foreign = $this->label($super);

        $visible =
            $this->statement($assigned);

        $hidden =
            $this->statement($foreign);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $service = app(
            AdminFinancialAccessService::class
        );

        $query = RoyaltyStatement::query();

        $service->applyFinancialOwnerScope(
            $query,
            $admin
        );

        $ids = $query->pluck('id');

        $this->assertTrue(
            $ids->contains($visible->id)
        );

        $this->assertFalse(
            $ids->contains($hidden->id)
        );
    }

    public function test_admin_scope_contains_directly_assigned_artist(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);

        $artist =
            $this->artist(
                $super,
                $label
            );

        $visible =
            $this->statement(
                null,
                $artist
            );

        $this->assignArtist(
            $admin,
            $artist,
            $super
        );

        $service = app(
            AdminFinancialAccessService::class
        );

        $query = RoyaltyStatement::query();

        $service->applyFinancialOwnerScope(
            $query,
            $admin
        );

        $this->assertTrue(
            $query
                ->pluck('id')
                ->contains($visible->id)
        );
    }

    public function test_unassigned_admin_has_zero_financial_visibility(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);

        $this->statement($label);

        $service = app(
            AdminFinancialAccessService::class
        );

        $query = RoyaltyStatement::query();

        $service->applyFinancialOwnerScope(
            $query,
            $admin
        );

        $this->assertSame(
            0,
            $query->count()
        );
    }

    public function test_admin_cannot_access_foreign_financial_owner(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);
        $foreign = $this->label($super);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $service = app(
            AdminFinancialAccessService::class
        );

        $this->assertTrue(
            $service->canAccessFinancialOwner(
                $admin,
                $assigned->id,
                null
            )
        );

        $this->assertFalse(
            $service->canAccessFinancialOwner(
                $admin,
                $foreign->id,
                null
            )
        );
    }

    public function test_accessible_user_ids_are_assignment_scoped(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assignedOwner =
            $this->user('label');

        $foreignOwner =
            $this->user('label');

        $assigned =
            $this->label(
                $super,
                $assignedOwner
            );

        $this->label(
            $super,
            $foreignOwner
        );

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $service = app(
            AdminFinancialAccessService::class
        );

        $ids =
            $service
                ->accessibleUserIds($admin);

        $this->assertTrue(
            $ids->contains(
                $assignedOwner->id
            )
        );

        $this->assertFalse(
            $ids->contains(
                $foreignOwner->id
            )
        );

        $this->assertTrue(
            $service->canAccessUser(
                $admin,
                $assignedOwner->id
            )
        );

        $this->assertFalse(
            $service->canAccessUser(
                $admin,
                $foreignOwner->id
            )
        );
    }

    public function test_super_admin_financial_scope_is_unrestricted(): void
    {
        $super = $this->user('super_admin');

        $labelA = $this->label($super);
        $labelB = $this->label($super);

        $statementA =
            $this->statement($labelA);

        $statementB =
            $this->statement($labelB);

        $service = app(
            AdminFinancialAccessService::class
        );

        $query = RoyaltyStatement::query();

        $service->applyFinancialOwnerScope(
            $query,
            $super
        );

        $ids = $query->pluck('id');

        $this->assertTrue(
            $ids->contains($statementA->id)
        );

        $this->assertTrue(
            $ids->contains($statementB->id)
        );

        $this->assertTrue(
            $service->canAccessFinancialOwner(
                $super,
                $labelA->id,
                null
            )
        );
    }
}
