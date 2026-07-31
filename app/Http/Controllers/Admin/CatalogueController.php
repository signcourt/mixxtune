<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $allowedStatuses = [
            'draft',
            'submitted',
            'changes_requested',
            'approved',
            'rejected',
        ];

        $query = Release::query()
            ->with([
                'artist',
                'label',
            ])
            ->withCount([
                'tracks',
                'storeDeliveries',
            ])
            ->withCount([
                'storeDeliveries as live_store_count' => function ($builder) {
                    $builder->where('status', 'live');
                },
                'storeDeliveries as delivered_store_count' => function ($builder) {
                    $builder->where('status', 'delivered');
                },
            ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('primary_artist_name', 'like', "%{$search}%")
                    ->orWhere('catalog_number', 'like', "%{$search}%")
                    ->orWhere('upc', 'like', "%{$search}%")
                    ->orWhereHas('tracks', function ($trackQuery) use ($search) {
                        $trackQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('isrc', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/Catalogue/Index', [
            'releases' => $query
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),

            'filters' => [
                'search' => $search,
                'status' => $status,
            ],

            'counts' => [
                'all' => Release::query()->count(),
                'draft' => Release::query()
                    ->where('status', 'draft')
                    ->count(),
                'submitted' => Release::query()
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => Release::query()
                    ->where('status', 'approved')
                    ->count(),
                'rejected' => Release::query()
                    ->where('status', 'rejected')
                    ->count(),
            ],
        ]);
    }
}
