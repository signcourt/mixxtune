<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\CatalogueItem;
use App\Services\V2\CatalogueService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): Response|JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'status' => trim(
                (string) $request->input(
                    'status',
                    ''
                )
            ),

            'visibility' => trim(
                (string) $request->input(
                    'visibility',
                    ''
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'latest'
                )
            ),
        ];

        $baseQuery = fn () =>
            $catalogue->scopedQuery(
                $request->user(),
                $permissions
            );

        $query = $baseQuery();

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'primary_artist_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'label_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'upc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'catalog_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'release.tracks',
                            function ($trackQuery) use ($search) {
                                $trackQuery
                                    ->where(
                                        'title',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'isrc',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        if ($filters['status'] !== '') {
            $query->where(
                'release_status',
                $filters['status']
            );
        }

        if (
            $filters['visibility']
            === 'visible'
        ) {
            $query->where(
                'is_visible',
                true
            );
        }

        if (
            $filters['visibility']
            === 'hidden'
        ) {
            $query->where(
                'is_visible',
                false
            );
        }

        match ($filters['sort']) {
            'oldest' =>
                $query->orderBy('id'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            'release_date' =>
                $query->orderByDesc(
                    'digital_release_date'
                ),

            default =>
                $query->orderByDesc('id'),
        };

        $counts = [
            'total' =>
                $baseQuery()->count(),

            'visible' =>
                $baseQuery()
                    ->where(
                        'is_visible',
                        true
                    )
                    ->count(),

            'approved' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'approved'
                    )
                    ->count(),

            'processing' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'processing'
                    )
                    ->count(),

            'delivered' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'delivered'
                    )
                    ->count(),

            'live' =>
                $baseQuery()
                    ->where(
                        'release_status',
                        'live'
                    )
                    ->count(),
        ];

        $items = $query
            ->paginate(24)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'filters' => $filters,
                'counts' => $counts,
                'catalogue' => $items,
            ]);
        }

        return Inertia::render(
            'V2/Catalogue/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'filters' => $filters,
                'counts' => $counts,
                'catalogue' => $items,
            ]
        );
    }

    public function show(
        Request $request,
        CatalogueItem $catalogueItem,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): Response|JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $allowed = $catalogue
            ->scopedQuery(
                $request->user(),
                $permissions
            )
            ->where(
                'catalogue_items.id',
                $catalogueItem->id
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You cannot access this catalogue item.'
        );

        $catalogueItem->load([
            'release.tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'catalogue_item' =>
                    $catalogueItem,
            ]);
        }

        return Inertia::render(
            'V2/Catalogue/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'catalogueItem' =>
                    $catalogueItem,
            ]
        );
    }
}
