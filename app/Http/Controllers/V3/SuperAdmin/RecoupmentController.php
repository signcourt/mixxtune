<?php

namespace App\Http\Controllers\V3\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\Finance\StoreRecoupmentDocumentRequest;
use App\Http\Requests\V3\Finance\StoreRecoupmentExpenseRequest;
use App\Http\Requests\V3\Finance\StoreRecoupmentPlanRequest;
use App\Models\Finance\RecoupmentDocument;
use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\V3\RecoupmentDocumentService;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecoupmentController extends Controller
{
    public function __construct(
        private readonly RecoupmentManagementService $management,
        private readonly RecoupmentDocumentService $documents
    ) {
    }

    public function index(
        Request $request
    ): Response {
        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $status = trim(
            (string) $request->input(
                'status',
                ''
            )
        );

        $query = RecoupmentPlan::query()
            ->with([
                'user:id,name,email,role',
                'label:id,name,user_id',
                'artist:id,stage_name,legal_name,user_id,label_id',
            ])
            ->withCount([
                'expenses',
                'recoveries',
                'documents',
            ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'plan_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'user',
                        function ($userQuery) use ($search) {
                            $userQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    )
                    ->orWhereHas(
                        'label',
                        fn ($labelQuery) =>
                            $labelQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                    )
                    ->orWhereHas(
                        'artist',
                        function ($artistQuery) use ($search) {
                            $artistQuery
                                ->where(
                                    'stage_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'legal_name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        $plans = $query
            ->orderByRaw(
                "CASE
                    WHEN status = 'active' THEN 0
                    WHEN status = 'completed' THEN 1
                    ELSE 2
                END"
            )
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'total_recoverable' => (float) RecoupmentPlan::query()
                ->sum(
                    'total_recoverable_amount'
                ),

            'total_recovered' => (float) RecoupmentPlan::query()
                ->sum(
                    'total_recovered_amount'
                ),

            'outstanding' => (float) RecoupmentPlan::query()
                ->where(
                    'status',
                    'active'
                )
                ->sum(
                    'outstanding_amount'
                ),

            'active_plans' => RecoupmentPlan::query()
                ->where(
                    'status',
                    'active'
                )
                ->count(),
        ];

        $accounts = DB::table('users')
            ->whereIn(
                'role',
                [
                    'label',
                    'artist',
                ]
            )
            ->select([
                'id',
                'name',
                'email',
                'role',
            ])
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render(
            'V3/SuperAdmin/Recoupment/Index',
            [
                'plans' => $plans,
                'summary' => $summary,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
                'accounts' => $accounts,
            ]
        );
    }

    public function store(
        StoreRecoupmentPlanRequest $request
    ) {
        $data = $request->validated();

        $user = User::query()->findOrFail(
            $data['user_id']
        );

        $plan = $this->management->createPlan(
            $user,
            $data,
            Auth::user()
        );

        return back()->with(
            'success',
            'Recoupment plan created.'
        );
    }

    public function addExpense(
        StoreRecoupmentExpenseRequest $request,
        RecoupmentPlan $plan
    ) {
        $expense = $this->management->addExpense(
            $plan,
            $request->validated(),
            Auth::user()
        );

        return back()->with(
            'success',
            'Expense added.'
        );
    }

    public function details(
        RecoupmentPlan $plan
    ): JsonResponse {
        $plan->load([
            'user:id,name,email,role',
            'label:id,name,user_id',
            'artist:id,stage_name,legal_name,user_id,label_id',
            'expenses' => fn ($query) =>
                $query->orderByDesc('expense_date')
                    ->orderByDesc('id'),
            'recoveries' => fn ($query) =>
                $query->orderByDesc('reporting_month')
                    ->orderByDesc('id'),
            'documents' => fn ($query) =>
                $query->orderByDesc('id'),
        ]);

        return response()->json([
            'data' => $plan,
        ]);
    }

    public function downloadDocument(
        RecoupmentPlan $plan,
        RecoupmentDocument $document
    ): BinaryFileResponse {
        abort_unless(
            (int) $document->recoupment_plan_id
                === (int) $plan->id,
            404
        );

        $path = $this->documents->downloadPath(
            $document
        );

        return response()->download(
            $path,
            $document->original_name,
            [
                'Content-Type' =>
                    $document->mime_type
                    ?: 'application/octet-stream',
            ]
        );
    }


    public function uploadDocument(
        StoreRecoupmentDocumentRequest $request,
        RecoupmentPlan $plan
    ) {
        $data = $request->validated();

        if (
            isset($data['recoupment_expense_id'])
            && ! $plan->expenses()
                ->whereKey(
                    $data['recoupment_expense_id']
                )
                ->exists()
        ) {
            abort(
                422,
                'Expense does not belong to this recoupment plan.'
            );
        }

        $document = $this->documents->store(
            $plan,
            $request->file('document'),
            $data['document_type'],
            $data['recoupment_expense_id'] ?? null,
            Auth::user()
        );

        return back()->with(
            'success',
            'Document uploaded.'
        );
    }
}
