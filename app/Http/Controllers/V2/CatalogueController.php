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

            'product_type' => trim(
                (string) $request->input(
                    'product_type',
                    ''
                )
            ),

            'label' => trim(
                (string) $request->input(
                    'label',
                    ''
                )
            ),

            'artist' => trim(
                (string) $request->input(
                    'artist',
                    ''
                )
            ),

            'date_from' => trim(
                (string) $request->input(
                    'date_from',
                    ''
                )
            ),

            'date_to' => trim(
                (string) $request->input(
                    'date_to',
                    ''
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

        if ($filters['product_type'] !== '') {
            $query->where(
                'release_type',
                $filters['product_type']
            );
        }

        if ($filters['label'] !== '') {
            $query->where(
                'label_name',
                $filters['label']
            );
        }

        if ($filters['artist'] !== '') {
            $query->where(
                'primary_artist_name',
                $filters['artist']
            );
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate(
                'digital_release_date',
                '>=',
                $filters['date_from']
            );
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate(
                'digital_release_date',
                '<=',
                $filters['date_to']
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
            ->paginate(50)
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

    public function export(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ) {
        $permissions->authorize(
            $request->user(),
            'catalogue.export'
        );

        $query = $catalogue->scopedQuery(
            $request->user(),
            $permissions
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        if ($search !== '') {
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

        foreach ([
            'status' => 'release_status',
            'product_type' => 'release_type',
            'label' => 'label_name',
            'artist' => 'primary_artist_name',
        ] as $requestKey => $column) {
            $value = trim(
                (string) $request->input(
                    $requestKey,
                    ''
                )
            );

            if ($value !== '') {
                $query->where(
                    $column,
                    $value
                );
            }
        }

        $visibility = trim(
            (string) $request->input(
                'visibility',
                ''
            )
        );

        if ($visibility === 'visible') {
            $query->where(
                'is_visible',
                true
            );
        }

        if ($visibility === 'hidden') {
            $query->where(
                'is_visible',
                false
            );
        }

        $dateFrom = trim(
            (string) $request->input(
                'date_from',
                ''
            )
        );

        $dateTo = trim(
            (string) $request->input(
                'date_to',
                ''
            )
        );

        if ($dateFrom !== '') {
            $query->whereDate(
                'digital_release_date',
                '>=',
                $dateFrom
            );
        }

        if ($dateTo !== '') {
            $query->whereDate(
                'digital_release_date',
                '<=',
                $dateTo
            );
        }

        match (
            (string) $request->input(
                'sort',
                'latest'
            )
        ) {
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

        $filename =
            'mixx-tune-catalogue-'
            .now()->format('Y-m-d-His')
            .'.csv';

        return response()->streamDownload(
            function () use ($query) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    [
                        'Release Title',
                        'Primary Artist',
                        'Label',
                        'Product Type',
                        'UPC',
                        'Catalogue Number',
                        'Track Count',
                        'ISRC Assigned',
                        'Release Date',
                        'Status',
                        'Visible',
                    ]
                );

                $query
                    ->chunkById(
                        500,
                        function ($items) use ($handle) {
                            foreach ($items as $item) {
                                fputcsv(
                                    $handle,
                                    [
                                        $item->title,
                                        $item->primary_artist_name,
                                        $item->label_name,
                                        $item->release_type,
                                        $item->upc,
                                        $item->catalog_number,
                                        $item->track_count,
                                        $item->isrc_assigned_count,
                                        optional(
                                            $item->digital_release_date
                                        )->format('Y-m-d')
                                            ?? $item->digital_release_date,
                                        $item->release_status,
                                        $item->is_visible
                                            ? 'Yes'
                                            : 'No',
                                    ]
                                );
                            }
                        }
                    );

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
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
