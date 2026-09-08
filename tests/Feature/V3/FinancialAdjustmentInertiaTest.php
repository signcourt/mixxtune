<?php

namespace Tests\Feature\V3;

use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialAdjustmentInertiaTest extends TestCase
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

    public function test_guest_cannot_open_adjustment_page(): void
    {
        $response = $this->get(
            '/super-admin/finance/adjustments'
        );

        $response->assertRedirect();
    }

    public function test_admin_cannot_open_adjustment_page(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertForbidden();
    }

    public function test_artist_cannot_open_adjustment_page(): void
    {
        $artist = $this->user('artist');

        $this->actingAs($artist)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertForbidden();
    }

    public function test_label_cannot_open_adjustment_page(): void
    {
        $label = $this->user('label');

        $this->actingAs($label)
            ->get(
                '/super-admin/finance/adjustments'
            )
            ->assertForbidden();
    }

    public function test_super_admin_receives_adjustment_inertia_page(): void
    {
        $superAdmin =
            $this->user('super_admin');

        $artist =
            $this->user('artist');

        $response =
            $this->actingAs($superAdmin)
                ->get(
                    '/super-admin/finance/adjustments'
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'V3/SuperAdmin/Adjustments/Index'
                        )
                        ->has('adjustments')
                        ->has('adjustments.data')
                        ->has('accounts')
                        ->has('summary')
                        ->where(
                            'summary.credits',
                            0
                        )
                        ->where(
                            'summary.debits',
                            0
                        )
                        ->where(
                            'summary.posted',
                            0
                        )
                        ->where(
                            'summary.reversed',
                            0
                        )
                        ->where(
                            'accounts',
                            fn ($accounts) =>
                                collect($accounts)
                                    ->contains(
                                        fn ($account) =>
                                            (int) $account['id']
                                                === $artist->id
                                    )
                        )
            );
    }

    public function test_posted_adjustment_is_visible_with_wallet_transaction(): void
    {
        $superAdmin =
            $this->user('super_admin');

        $artist =
            $this->user('artist');

        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/finance/adjustments',
                [
                    'idempotency_key' =>
                        'inertia-adjustment-'
                        .$artist->id
                        .'-001',

                    'user_id' =>
                        $artist->id,

                    'direction' =>
                        'credit',

                    'amount' =>
                        500,

                    'reason' =>
                        'Manual correction',

                    'type' =>
                        'manual',

                    'currency' =>
                        'INR',
                ]
            )
            ->assertRedirect();

        $adjustment =
            FinancialAdjustment::query()
                ->firstOrFail();

        $response =
            $this->actingAs($superAdmin)
                ->get(
                    '/super-admin/finance/adjustments'
                );

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->has(
                            'adjustments.data',
                            1
                        )
                        ->where(
                            'adjustments.data.0.id',
                            $adjustment->id
                        )
                        ->where(
                            'adjustments.data.0.direction',
                            'credit'
                        )
                        ->where(
                            'adjustments.data.0.status',
                            'posted'
                        )
                        ->where(
                            'adjustments.data.0.wallet_transaction.balance_before',
                            '0.00000000'
                        )
                        ->where(
                            'adjustments.data.0.wallet_transaction.balance_after',
                            '500.00000000'
                        )
                        ->where(
                            'summary.credits',
                            500
                        )
                        ->where(
                            'summary.posted',
                            1
                        )
            );
    }
}
