<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        /* MIXXTUNE_FINANCE_CLEANUP_V1: only known roles; any other role sees nothing */
        abort_unless(
            in_array($role, ['artist', 'label', 'admin', 'super_admin'], true),
            403
        );

        $query =
            RoyaltyStatement::query();

        $this->applyScope(
            $query,
            $request,
            $role
        );

        /*
         * Search and status now run on the server over every statement
         * (before: only inside the 25 rows of the current page).
         */
        $filters = [
            'search' => mb_substr(trim((string) $request->input('search', '')), 0, 100),
            'status' => in_array($request->input('status'), ['pending', 'generated', 'approved', 'available', 'paid', 'cancelled'], true)
                ? (string) $request->input('status')
                : '',
        ];

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']).'%';

            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('statement_month', 'like', $term)
                    ->orWhere('public_id', 'like', $term)
                    ->orWhere('currency', 'like', $term)
                    ->orWhereIn('label_id', DB::table('labels')->where('name', 'like', $term)->select('id'))
                    ->orWhereIn('artist_id', DB::table('artists')->where(function ($artists) use ($term) {
                        $artists->where('stage_name', 'like', $term)->orWhere('legal_name', 'like', $term);
                    })->select('id'));
            });
        }

        return Inertia::render(
            'V2/Statements/Index',
            [
                'role' => $role,

                'filters' => $filters,

                'statements' =>
                    $query
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->orderByDesc('id')
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }

    public function download(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions
    ): HttpResponse {
        $role = $permissions->role(
            $request->user()
        );

        $query = RoyaltyStatement::query()
            ->where(
                'id',
                $statement->id
            );

        $this->applyScope(
            $query,
            $request,
            $role
        );

        abort_unless(
            $query->exists(),
            403
        );

        /* MIXXTUNE_OWNER_STATEMENT_V1: readable owner statement PDF. */
        $service = app(\App\Services\V2\OwnerStatementService::class);

        return response($service->pdf($statement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$service->fileName($statement).'"',
        ]);
    }

    private function applyScope(
        $query,
        Request $request,
        string $role
    ): void {
        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'artist_id',
                $artistId ?: 0
            );
        }

        if ($role === 'label') {
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            $labelIds = $teamAccess
                ->accessibleLabelIds(
                    $request->user()
                );

            $artistIds = $teamAccess
                ->accessibleArtistIds(
                    $request->user()
                );

            if (
                $labelIds->isEmpty()
                && $artistIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $labelIds,
                        $artistIds
                    ) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->whereIn(
                                'label_id',
                                $labelIds
                            );
                        }

                        if ($artistIds->isNotEmpty()) {
                            if ($labelIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'artist_id',
                                    $artistIds
                                );
                            } else {
                                $builder->whereIn(
                                    'artist_id',
                                    $artistIds
                                );
                            }
                        }
                    }
                );
            }
        }

        if ($role === 'admin') {
            $assignments = app(
                AdminAssignmentService::class
            );

            $artistIds =
                $assignments->artistIds(
                    $request->user()
                );

            $labelIds =
                $assignments->labelIds(
                    $request->user()
                );

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    function ($builder) use (
                        $artistIds,
                        $labelIds
                    ) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->whereIn(
                                'artist_id',
                                $artistIds
                            );
                        }

                        if ($labelIds->isNotEmpty()) {
                            if ($artistIds->isNotEmpty()) {
                                $builder->orWhereIn(
                                    'label_id',
                                    $labelIds
                                );
                            } else {
                                $builder->whereIn(
                                    'label_id',
                                    $labelIds
                                );
                            }
                        }
                    }
                );
            }
        }
    }
}
