<?php

namespace Tests\Feature\ClientIdentity;

use App\Models\User;
use App\Services\V2\ClientIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientIdServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_client_id_has_expected_format(): void
    {
        $service = app(ClientIdService::class);

        $clientId = $service->generate(
            'label',
            'IN',
            'DL',
            Carbon::create(
                2026,
                8,
                10,
                12,
                0,
                0
            )
        );

        $this->assertSame(
            'MTINDLL26081000001',
            $clientId
        );
    }

    public function test_artist_uses_artist_role_code(): void
    {
        $service = app(ClientIdService::class);

        $clientId = $service->generate(
            'artist',
            'IN',
            'DL',
            Carbon::create(
                2026,
                8,
                10,
                12,
                0,
                0
            )
        );

        $this->assertSame(
            'MTINDLA26081000001',
            $clientId
        );
    }

    public function test_sequence_increments_for_existing_prefix(): void
    {
        User::factory()->create([
            'client_id' =>
                'MTINDLL26081000012',
        ]);

        $service = app(ClientIdService::class);

        $clientId = $service->generate(
            'label',
            'IN',
            'DL',
            Carbon::create(
                2026,
                8,
                10,
                12,
                0,
                0
            )
        );

        $this->assertSame(
            'MTINDLL26081000013',
            $clientId
        );
    }

    public function test_different_role_has_independent_sequence(): void
    {
        User::factory()->create([
            'client_id' =>
                'MTINDLL26081000012',
        ]);

        $service = app(ClientIdService::class);

        $clientId = $service->generate(
            'artist',
            'IN',
            'DL',
            Carbon::create(
                2026,
                8,
                10,
                12,
                0,
                0
            )
        );

        $this->assertSame(
            'MTINDLA26081000001',
            $clientId
        );
    }

    public function test_invalid_state_code_is_rejected(): void
    {
        $this->expectException(
            ValidationException::class
        );

        app(ClientIdService::class)
            ->generate(
                'label',
                'IN',
                'DEL',
                Carbon::create(
                    2026,
                    8,
                    10
                )
            );
    }

    public function test_admin_cannot_receive_client_id(): void
    {
        $this->expectException(
            ValidationException::class
        );

        app(ClientIdService::class)
            ->generate(
                'admin',
                'IN',
                'DL',
                Carbon::create(
                    2026,
                    8,
                    10
                )
            );
    }
}
