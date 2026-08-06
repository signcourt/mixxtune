<?php

namespace App\Services\V2;

use App\Services\V2\UpcService;
use App\Services\V2\IsrcService;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryWorkflowService
{
    private const STATUSES = [
        'pending',
        'processing',
        'delivered',
        'live',
        'failed',
        'takedown_requested',
        'taken_down',
    ];

    private const TRANSITIONS = [
        'pending' => [
            'processing',
            'failed',
        ],

        'processing' => [
            'delivered',
            'failed',
        ],

        'delivered' => [
            'live',
            'failed',
            'takedown_requested',
        ],

        'live' => [
            'takedown_requested',
            'failed',
        ],

        'failed' => [
            'processing',
        ],

        'takedown_requested' => [
            'taken_down',
            'live',
        ],

        'taken_down' => [],
    ];

    public function __construct(
        private readonly ReleaseWorkflowService $releaseWorkflow,
        private readonly IsrcService $isrc,
        private readonly UpcService $upc
    ) {
    }

    public function initialise(
        Release $release,
        User $user
    ): Collection {
        abort_unless(
            in_array(
                $release->status,
                [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                ],
                true
            ),
            422,
            'Release must be approved before delivery starts.'
        );

        /*
         * Identifiers must exist before a release enters DSP delivery.
         * Existing UPC/ISRC values are preserved.
         */
        $release->loadMissing('tracks');

        foreach ($release->tracks as $track) {
            if (blank($track->isrc)) {
                $this->isrc->generate(
                    $track,
                    $user
                );
            }
        }

        if (blank($release->upc)) {
            $this->upc->generate(
                $release,
                $user
            );
        }

        $release->refresh();
        $release->loadMissing('tracks');

        abort_if(
            blank($release->upc),
            422,
            'UPC generation failed. DSP delivery was not started.'
        );

        abort_if(
            $release->tracks->contains(
                fn ($track) => blank($track->isrc)
            ),
            422,
            'One or more tracks do not have an ISRC. DSP delivery was not started.'
        );

        $storeIds = $this->resolveStoreIds(
            $release
        );

        if (empty($storeIds)) {
            throw ValidationException::withMessages([
                'stores' =>
                    'No active stores are available for delivery.',
            ]);
        }

        DB::transaction(function () use (
            $release,
            $storeIds,
            $user
        ) {
            foreach ($storeIds as $storeId) {
                ReleaseStoreDelivery::query()
                    ->firstOrCreate(
                        [
                            'release_id' =>
                                $release->id,

                            'distribution_store_id' =>
                                $storeId,
                        ],
                        [
                            'public_id' =>
                                (string) Str::ulid(),

                            'status' =>
                                'pending',

                            'created_by' =>
                                $user->id,

                            'updated_by' =>
                                $user->id,
                        ]
                    );
            }

            if ($release->status === 'approved') {
                $this->releaseWorkflow
                    ->transition(
                        $release,
                        'processing',
                        $user,
                        [
                            'action' =>
                                'delivery_initialised',

                            'remarks' =>
                                'Store delivery records created.',
                        ]
                    );
            }
        });

        return $this->deliveries(
            $release
        );
    }

    public function transition(
        ReleaseStoreDelivery $delivery,
        string $newStatus,
        User $user,
        array $data = []
    ): ReleaseStoreDelivery {
        $newStatus = trim(
            strtolower($newStatus)
        );

        abort_unless(
            in_array(
                $newStatus,
                self::STATUSES,
                true
            ),
            422,
            'Invalid delivery status.'
        );

        $oldStatus = (string) $delivery->status;

        abort_unless(
            in_array(
                $newStatus,
                self::TRANSITIONS[
                    $oldStatus
                ] ?? [],
                true
            ),
            422,
            "Store delivery cannot move from {$oldStatus} to {$newStatus}."
        );

        if (
            $newStatus === 'failed'
            && trim(
                (string) (
                    $data['error_message']
                    ?? ''
                )
            ) === ''
        ) {
            throw ValidationException::withMessages([
                'error_message' =>
                    'Failure reason is required.',
            ]);
        }

        DB::transaction(function () use (
            $delivery,
            $newStatus,
            $user,
            $data
        ) {
            $updates = [
                'status' =>
                    $newStatus,

                'delivery_note' =>
                    $data['delivery_note']
                    ?? $delivery
                        ->delivery_note,

                'external_reference' =>
                    $data[
                        'external_reference'
                    ]
                    ?? $delivery
                        ->external_reference,

                'updated_by' =>
                    $user->id,
            ];

            if ($newStatus === 'processing') {
                $updates['error_message'] = null;
                $updates['failed_at'] = null;
            }

            if ($newStatus === 'delivered') {
                $updates['delivered_at'] = now();
                $updates['error_message'] = null;
            }

            if ($newStatus === 'live') {
                $updates['live_at'] = now();
                $updates['error_message'] = null;
            }

            if ($newStatus === 'failed') {
                $updates['failed_at'] = now();

                $updates['error_message'] =
                    $data['error_message'];
            }

            if (
                $newStatus ===
                'takedown_requested'
            ) {
                $updates['delivery_note'] =
                    $data['delivery_note']
                    ?? 'Takedown requested.';
            }

            if ($newStatus === 'taken_down') {
                $updates['taken_down_at'] = now();
            }

            $delivery->update($updates);
        });

        $this->syncReleaseStatus(
            $delivery->release,
            $user
        );

        return $delivery->fresh([
            'store',
            'release',
        ]);
    }

    public function bulkTransition(
        Release $release,
        array $deliveryIds,
        string $newStatus,
        User $user,
        array $data = []
    ): Collection {
        $deliveries =
            ReleaseStoreDelivery::query()
                ->where(
                    'release_id',
                    $release->id
                )
                ->whereIn(
                    'id',
                    array_map(
                        'intval',
                        $deliveryIds
                    )
                )
                ->get();

        abort_if(
            $deliveries->isEmpty(),
            422,
            'Select at least one store delivery.'
        );

        foreach ($deliveries as $delivery) {
            $this->transition(
                $delivery,
                $newStatus,
                $user,
                $data
            );
        }

        return $this->deliveries(
            $release
        );
    }

    public function deliveries(
        Release $release
    ): Collection {
        return ReleaseStoreDelivery::query()
            ->with('store')
            ->where(
                'release_id',
                $release->id
            )
            ->orderBy('id')
            ->get();
    }

    public function summary(
        Release $release
    ): array {
        $deliveries = $this->deliveries(
            $release
        );

        return [
            'total' => $deliveries->count(),

            'pending' =>
                $deliveries
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'processing' =>
                $deliveries
                    ->where(
                        'status',
                        'processing'
                    )
                    ->count(),

            'delivered' =>
                $deliveries
                    ->where(
                        'status',
                        'delivered'
                    )
                    ->count(),

            'live' =>
                $deliveries
                    ->where(
                        'status',
                        'live'
                    )
                    ->count(),

            'failed' =>
                $deliveries
                    ->where(
                        'status',
                        'failed'
                    )
                    ->count(),

            'takedown_requested' =>
                $deliveries
                    ->where(
                        'status',
                        'takedown_requested'
                    )
                    ->count(),

            'taken_down' =>
                $deliveries
                    ->where(
                        'status',
                        'taken_down'
                    )
                    ->count(),
        ];
    }

    private function syncReleaseStatus(
        Release $release,
        User $user
    ): void {
        $summary = $this->summary(
            $release
        );

        if ($summary['total'] === 0) {
            return;
        }

        if (
            $summary['live']
            === $summary['total']
            && $release->status !== 'live'
        ) {
            $this->safeReleaseTransition(
                $release,
                'live',
                $user,
                'All store deliveries are live.'
            );

            return;
        }

        if (
            $summary['taken_down']
            === $summary['total']
            && $release->status
                !== 'taken_down'
        ) {
            $this->safeReleaseTransition(
                $release,
                'taken_down',
                $user,
                'All store deliveries were taken down.'
            );

            return;
        }

        if (
            (
                $summary['delivered']
                + $summary['live']
            ) === $summary['total']
            && !in_array(
                $release->status,
                [
                    'delivered',
                    'live',
                ],
                true
            )
        ) {
            $this->safeReleaseTransition(
                $release,
                'delivered',
                $user,
                'All store deliveries completed.'
            );

            return;
        }

        if (
            $summary['processing'] > 0
            && $release->status === 'approved'
        ) {
            $this->safeReleaseTransition(
                $release,
                'processing',
                $user,
                'Store processing started.'
            );
        }
    }

    private function safeReleaseTransition(
        Release $release,
        string $status,
        User $user,
        string $remarks
    ): void {
        $release->refresh();

        if (
            $this->releaseWorkflow
                ->canTransition(
                    $release,
                    $status
                )
        ) {
            $this->releaseWorkflow
                ->transition(
                    $release,
                    $status,
                    $user,
                    [
                        'action' =>
                            "delivery_{$status}",

                        'remarks' =>
                            $remarks,
                    ]
                );
        }
    }

    private function resolveStoreIds(
        Release $release
    ): array {
        $storeIds = is_array(
            $release->stores
        )
            ? array_values(
                array_unique(
                    array_map(
                        'intval',
                        $release->stores
                    )
                )
            )
            : [];

        if (!empty($storeIds)) {
            return DistributionStore::query()
                ->where('is_active', true)
                ->whereIn('id', $storeIds)
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();
        }

        return DistributionStore::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all();
    }
}
