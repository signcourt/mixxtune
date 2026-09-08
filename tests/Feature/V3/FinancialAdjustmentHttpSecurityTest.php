<?php

namespace Tests\Feature\V3;

use App\Models\Finance\FinancialAdjustment;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAdjustmentHttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function user(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function payload(
        User $beneficiary
    ): array {
        return [
            'idempotency_key' =>
                'http-adjustment-'
                .$beneficiary->id
                .'-001',

            'user_id' =>
                $beneficiary->id,

            'direction' =>
                'credit',

            'amount' =>
                1000,

            'reason' =>
                'HTTP adjustment test',

            'reference_number' =>
                'HTTP-TEST-001',

            'type' =>
                'manual',

            'currency' =>
                'INR',
        ];
    }

    public function test_guest_cannot_access_adjustment_index(): void
    {
        $response = $this->get(
            '/super-admin/finance/adjustments'
        );

        $response->assertRedirect();
    }

    public function test_normal_admin_cannot_access_adjustment_index(): void
    {
        $admin = $this->user('admin');

        $response = $this
            ->actingAs($admin)
            ->get(
                '/super-admin/finance/adjustments'
            );

        $response->assertForbidden();
    }

    public function test_artist_cannot_access_adjustment_index(): void
    {
        $artist = $this->user('artist');

        $this->actingAs($artist)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertForbidden();
    }

    public function test_label_cannot_access_adjustment_index(): void
    {
        $label = $this->user('label');

        $this->actingAs($label)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertForbidden();
    }

    public function test_super_admin_can_access_adjustment_index(): void
    {
        $admin = $this->user(
            'super_admin'
        );

        $this->actingAs($admin)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertOk();
    }

    public function test_normal_admin_cannot_post_adjustment(): void
    {
        $admin = $this->user('admin');
        $beneficiary = $this->user(
            'artist'
        );

        $this->actingAs($admin)
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );
    }

    public function test_artist_cannot_post_adjustment(): void
    {
        $artist = $this->user('artist');
        $beneficiary = $this->user(
            'artist'
        );

        $this->actingAs($artist)
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );
    }

    public function test_label_cannot_post_adjustment(): void
    {
        $label = $this->user('label');
        $beneficiary = $this->user(
            'artist'
        );

        $this->actingAs($label)
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );
    }

    public function test_super_admin_can_post_credit_adjustment(): void
    {
        $admin = $this->user(
            'super_admin'
        );

        $beneficiary = $this->user(
            'artist'
        );

        $response = $this
            ->actingAs($admin)
            ->from(
                '/super-admin/finance/recoupment'
            )
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            );

        $response->assertRedirect(
            '/super-admin/finance/recoupment'
        );

        $this->assertDatabaseHas(
            'financial_adjustments',
            [
                'user_id' =>
                    $beneficiary->id,

                'direction' =>
                    'credit',

                'status' =>
                    'posted',

                'created_by' =>
                    $admin->id,
            ]
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $beneficiary->id
            )
            ->firstOrFail();

        $this->assertEquals(
            1000,
            (float) $wallet
                ->available_balance
        );
    }

    public function test_super_admin_can_reverse_adjustment(): void
    {
        $admin = $this->user(
            'super_admin'
        );

        $beneficiary = $this->user(
            'artist'
        );

        $this->actingAs($admin)
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            );

        $adjustment =
            FinancialAdjustment::query()
                ->whereNull(
                    'reversal_of_id'
                )
                ->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->from(
                '/super-admin/finance/recoupment'
            )
            ->post(
                '/super-admin/finance/adjustments/'
                    .$adjustment->id
                    .'/reverse',
                [
                    'reason' =>
                        'HTTP reversal test',
                ]
            );

        $response->assertRedirect(
            '/super-admin/finance/recoupment'
        );

        $adjustment->refresh();

        $this->assertSame(
            'reversed',
            $adjustment->status
        );

        $this->assertEquals(
            $admin->id,
            $adjustment->reversed_by
        );

        $this->assertDatabaseHas(
            'financial_adjustments',
            [
                'reversal_of_id' =>
                    $adjustment->id,

                'type' =>
                    'reversal',

                'direction' =>
                    'debit',

                'status' =>
                    'posted',
            ]
        );

        $wallet = WalletAccount::query()
            ->where(
                'user_id',
                $beneficiary->id
            )
            ->firstOrFail();

        $this->assertEquals(
            0,
            (float) $wallet
                ->available_balance
        );
    }

    public function test_normal_admin_cannot_reverse_adjustment(): void
    {
        $superAdmin = $this->user(
            'super_admin'
        );

        $normalAdmin = $this->user(
            'admin'
        );

        $beneficiary = $this->user(
            'artist'
        );

        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/finance/adjustments',
                $this->payload(
                    $beneficiary
                )
            );

        $adjustment =
            FinancialAdjustment::query()
                ->firstOrFail();

        $this->actingAs($normalAdmin)
            ->post(
                '/super-admin/finance/adjustments/'
                    .$adjustment->id
                    .'/reverse',
                [
                    'reason' =>
                        'Unauthorized reversal',
                ]
            )
            ->assertForbidden();

        $adjustment->refresh();

        $this->assertSame(
            'posted',
            $adjustment->status
        );

        $this->assertDatabaseCount(
            'financial_adjustments',
            1
        );
    }

    public function test_invalid_adjustment_payload_is_rejected(): void
    {
        $admin = $this->user(
            'super_admin'
        );

        $beneficiary = $this->user(
            'artist'
        );

        $response = $this
            ->actingAs($admin)
            ->from(
                '/super-admin/finance/recoupment'
            )
            ->post(
                '/super-admin/finance/adjustments',
                [
                    'user_id' =>
                        $beneficiary->id,

                    'direction' =>
                        'invalid',

                    'amount' =>
                        0,

                    'reason' =>
                        '',
                ]
            );

        $response->assertRedirect(
            '/super-admin/finance/recoupment'
        );

        $response->assertSessionHasErrors([
            'direction',
            'amount',
            'reason',
        ]);

        $this->assertDatabaseCount(
            'financial_adjustments',
            0
        );
    }
}
