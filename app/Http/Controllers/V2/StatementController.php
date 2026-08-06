<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\PermissionService;
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

        $query =
            RoyaltyStatement::query();

        $this->applyScope(
            $query,
            $request,
            $role
        );

        return Inertia::render(
            'V2/Statements/Index',
            [
                'role' => $role,

                'statements' =>
                    $query
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->paginate(25),
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

        $statement->load(
            'allocations'
        );

        return Pdf::loadView(
            'pdf.royalty-statement',
            [
                'statement' =>
                    $statement,

                'user' =>
                    $request->user(),
            ]
        )->download(
            'statement-'
            . $statement->statement_month
            . '.pdf'
        );
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
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'label_id',
                $labelId ?: 0
            );
        }

        if ($role === 'admin') {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $request->user()->id
                )
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }
    }
}
