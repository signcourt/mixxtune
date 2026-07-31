<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReleaseDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $allowedStatuses = [
            'pending',
            'processing',
            'delivered',
            'live',
            'failed',
            'takedown',
        ];

        $query = Release::query()
            ->withCount('storeDeliveries')
            ->where('status', 'approved');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere(
                        'primary_artist_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'catalog_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere('upc', 'like', "%{$search}%");
            });
        }

        if (in_array($status, $allowedStatuses, true)) {
            $query->whereHas(
                'storeDeliveries',
                fn ($deliveryQuery) =>
                    $deliveryQuery->where('status', $status)
            );
        }

        return Inertia::render(
            'Admin/Releases/DeliveryStatus',
            [
                'releases' => $query
                    ->latest('approved_at')
                    ->paginate(20)
                    ->withQueryString(),

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
            ]
        );
    }

    public function show(Release $release)
    {
        abort_unless($release->status === 'approved', 404);

        return Inertia::render(
            'Admin/Releases/DeliveryDetails',
            [
                'release' => $release->load([
                    'label:id,name',
                    'tracks:id,release_id,title,isrc',
                    'storeDeliveries' => fn ($query) =>
                        $query
                            ->with('store:id,name,slug,logo_path')
                            ->orderBy('id'),
                ]),
            ]
        );
    }

    public function syncStores(Release $release)
    {
        abort_unless($release->status === 'approved', 422);

        $excludedStoreIds = collect(
            $release->excluded_store_ids ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeStores = \App\Models\DistributionStore::query()
            ->where('is_active', true)
            ->whereNotIn('id', $excludedStoreIds)
            ->get(['id']);

        foreach ($activeStores as $store) {
            ReleaseStoreDelivery::firstOrCreate(
                [
                    'release_id' => $release->id,
                    'distribution_store_id' => $store->id,
                ],
                [
                    'status' => 'pending',
                    'updated_by' => auth()->id(),
                ]
            );
        }

        ReleaseStoreDelivery::query()
            ->where('release_id', $release->id)
            ->whereNotIn(
                'distribution_store_id',
                $activeStores->pluck('id')
            )
            ->delete();

        return back()->with(
            'success',
            'DSP delivery records synced successfully.'
        );
    }

    public function bulkUpdate(
        Request $request,
        Release $release
    ) {
        abort_unless($release->status === 'approved', 422);

        $validated = $request->validate([
            'delivery_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'delivery_ids.*' => [
                'integer',
                'exists:release_store_deliveries,id',
            ],
            'status' => [
                'required',
                Rule::in([
                    'pending',
                    'processing',
                    'delivered',
                    'live',
                    'failed',
                    'takedown',
                ]),
            ],
            'delivery_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $deliveries = ReleaseStoreDelivery::query()
            ->where('release_id', $release->id)
            ->whereIn('id', $validated['delivery_ids'])
            ->get();

        abort_if($deliveries->isEmpty(), 422, 'No valid DSP deliveries selected.');

        foreach ($deliveries as $delivery) {
            $timestamps = [
                'delivered_at' => null,
                'live_at' => null,
                'failed_at' => null,
                'takedown_at' => null,
            ];

            if ($validated['status'] === 'delivered') {
                $timestamps['delivered_at'] = now();
            }

            if ($validated['status'] === 'live') {
                $timestamps['live_at'] = now();
            }

            if ($validated['status'] === 'failed') {
                $timestamps['failed_at'] = now();
            }

            if ($validated['status'] === 'takedown') {
                $timestamps['takedown_at'] = now();
            }

            $delivery->update([
                'status' => $validated['status'],
                'delivery_notes' =>
                    $validated['delivery_notes']
                    ?? $delivery->delivery_notes,
                ...$timestamps,
                'updated_by' => auth()->id(),
            ]);
        }

        return back()->with(
            'success',
            $deliveries->count().' DSP delivery statuses updated successfully.'
        );
    }

    public function update(
        Request $request,
        ReleaseStoreDelivery $delivery
    ) {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'pending',
                    'processing',
                    'delivered',
                    'live',
                    'failed',
                    'takedown',
                ]),
            ],
            'store_release_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'store_url' => [
                'nullable',
                'url',
                'max:1000',
            ],
            'delivery_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $timestamps = [
            'delivered_at' => null,
            'live_at' => null,
            'failed_at' => null,
            'takedown_at' => null,
        ];

        if ($validated['status'] === 'delivered') {
            $timestamps['delivered_at'] = now();
        }

        if ($validated['status'] === 'live') {
            $timestamps['live_at'] = now();
        }

        if ($validated['status'] === 'failed') {
            $timestamps['failed_at'] = now();
        }

        if ($validated['status'] === 'takedown') {
            $timestamps['takedown_at'] = now();
        }

        $delivery->update([
            ...$validated,
            ...$timestamps,
            'updated_by' => auth()->id(),
        ]);

        return back()->with(
            'success',
            'Delivery status updated successfully.'
        );
    }
}
