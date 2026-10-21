<?php

namespace App\Http\Controllers\V3\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\Finance\StoreRecoupmentDocumentRequest;
use App\Http\Requests\V3\Finance\StoreRecoupmentExpenseRequest;
use App\Http\Requests\V3\Finance\StoreRecoupmentPlanRequest;
use App\Models\Finance\RecoupmentDocument;
use App\Models\Finance\RecoupmentExpense;
use App\Models\Finance\RecoupmentPlan;
use App\Models\User;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\FinanceAudit;
use App\Services\V3\RecoupmentDocumentService;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * MIXXTUNE_RECOUPMENT_V2 (W41)
 * - the first advance amount was dropped (form sent initial_advance_amount,
 *   server read initial_amount) -> plans were created with 0 to recover;
 * - owners found by search (was a dropdown of the first 500 owners A-Z),
 *   label / artist scope must belong to the owner (a wrong scope was never
 *   recovered), only one scope at a time;
 * - totals no longer mix in cancelled plans; written-off amount shown;
 * - e-mailed code for every money change (new advance, recoverable cost,
 *   rate change, cost removed), all audited; owner gets panel notices;
 * - change rates / title, take a wrong cost out of recovery, CSV export.
 */
class RecoupmentController extends Controller
{
    private const STATUSES = ['active', 'completed', 'cancelled'];

    public function __construct(
        private readonly RecoupmentManagementService $management,
        private readonly RecoupmentDocumentService $documents
    ) {
    }

    private function filtered(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $query = RecoupmentPlan::query();

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

            $query->where(function ($b) use ($like) {
                $b->where('plan_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like))
                    ->orWhereHas('label', fn ($l) => $l->where('name', 'like', $like))
                    ->orWhereHas('artist', fn ($a) => $a->where('stage_name', 'like', $like)->orWhere('legal_name', 'like', $like));
            });
        }

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $plans = $this->filtered($request)
            ->with([
                'user:id,name,email,role',
                'label:id,name,user_id',
                'artist:id,stage_name,legal_name,user_id,label_id',
            ])
            ->withCount(['expenses', 'recoveries', 'documents'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'completed' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $plans->getCollection()->transform(function (RecoupmentPlan $plan) {
            $plan->setAttribute('rates', $this->rates($plan));

            return $plan;
        });

        $notCancelled = fn () => RecoupmentPlan::query()->where('status', '!=', 'cancelled');

        $summary = [
            'total_recoverable' => round((float) $notCancelled()->sum('total_recoverable_amount'), 2),
            'total_recovered' => round((float) RecoupmentPlan::query()->sum('total_recovered_amount'), 2),
            'outstanding' => round((float) RecoupmentPlan::query()->where('status', 'active')->sum('outstanding_amount'), 2),
            'active_plans' => RecoupmentPlan::query()->where('status', 'active')->count(),
            'completed_plans' => RecoupmentPlan::query()->where('status', 'completed')->count(),
            'written_off' => round((float) RecoupmentPlan::query()->where('status', 'cancelled')->sum('outstanding_amount'), 2),
            'empty_plans' => RecoupmentPlan::query()->where('status', 'active')->where('total_recoverable_amount', '<=', 0)->count(),
        ];

        return Inertia::render('V3/SuperAdmin/Recoupment/Index', [
            'plans' => $plans,
            'summary' => $summary,
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'status' => in_array((string) $request->query('status', ''), self::STATUSES, true) ? (string) $request->query('status') : '',
            ],
        ]);
    }

    /** Recovery % actually used (same rules as RecoupmentCalculationService). */
    private function rates(RecoupmentPlan $plan): array
    {
        $base = max(0, min(100, (float) $plan->base_percentage));
        $uplift = max(0, (float) $plan->recovery_uplift_percentage);

        if ($plan->maximum_recovery_percentage !== null) {
            $uplift = min($uplift, max(0, (float) $plan->maximum_recovery_percentage));
        }

        $uplift = min($uplift, $base);
        $running = $plan->status === 'active' && (float) $plan->outstanding_amount > 0;

        return [
            'base' => round($base, 2),
            'recovery' => round($uplift, 2),
            'owner_now' => round($running ? $base - $uplift : $base, 2),
            'running' => $running,
        ];
    }

    /** Owners (labels / artists) with their label + artist profiles, balance and open plan. */
    public function owners(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['owners' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $owners = DB::table('users')
            ->whereIn('role', ['label', 'artist'])
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'role', 'client_id']);

        $ids = $owners->pluck('id');

        $labels = DB::table('labels')->whereIn('user_id', $ids)->orderBy('name')->get(['id', 'name', 'user_id'])->groupBy('user_id');
        $artists = DB::table('artists')->whereIn('user_id', $ids)->orderBy('stage_name')->get(['id', 'stage_name', 'legal_name', 'user_id'])->groupBy('user_id');
        $open = RecoupmentPlan::query()->whereIn('user_id', $ids)->where('status', 'active')->get(['id', 'user_id', 'plan_number', 'outstanding_amount'])->keyBy('user_id');
        $wallets = DB::table('wallets')->whereIn('user_id', $ids)->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(available_balance) as available'))->get()->keyBy('user_id');

        return response()->json([
            'owners' => $owners->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'email' => $o->email,
                'role' => $o->role,
                'client_id' => $o->client_id,
                'wallet' => round((float) ($wallets[$o->id]->available ?? 0), 2),
                'labels' => ($labels[$o->id] ?? collect())->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->values(),
                'artists' => ($artists[$o->id] ?? collect())->map(fn ($a) => ['id' => $a->id, 'name' => $a->stage_name ?: $a->legal_name])->values(),
                'open_plan' => isset($open[$o->id]) ? [
                    'id' => $open[$o->id]->id,
                    'plan_number' => $open[$o->id]->plan_number,
                    'outstanding' => round((float) $open[$o->id]->outstanding_amount, 2),
                ] : null,
            ])->values(),
        ]);
    }

    public function store(StoreRecoupmentPlanRequest $request)
    {
        $data = $request->validated();
        $user = User::query()->findOrFail($data['user_id']);

        $this->checkScope($user, $data['label_id'] ?? null, $data['artist_id'] ?? null);

        if (RecoupmentPlan::query()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'This owner already has an active plan. Add the new amount to that plan with "Add cost" instead.',
            ]);
        }

        $amount = round((float) ($data['initial_amount'] ?? 0), 2);
        $amountText = number_format($amount, 2, '.', '');

        if ($amount > 0) {
            app(ActionOtpGuard::class)->require(
                Auth::user(),
                'recoupment.create:'.$user->id.':'.$amountText,
                'start recovering '.$amountText.' INR from '.($user->name ?: $user->email)
            );
        }

        $plan = $this->management->createPlan($user, $data, Auth::user());

        FinanceAudit::log(
            'finance.recoupment_plan_created',
            'Recoupment plan '.$plan->plan_number.' created for user #'.$user->id.' ('.($user->email ?? '-').'), first amount '.$amountText.'.',
            FinanceAudit::pick($plan, ['id', 'plan_number', 'user_id', 'label_id', 'artist_id', 'base_percentage', 'recovery_uplift_percentage', 'maximum_recovery_percentage', 'total_recoverable_amount', 'status']),
            $plan
        );

        if ($request->boolean('notify_owner', true) && $amount > 0) {
            $this->tellOwner(
                $user,
                'Advance recorded',
                'An advance / recoverable cost of ₹'.number_format($amount, 2).' was recorded on your account ('.$plan->plan_number.'). '
                    .'It will be recovered from your future royalties at '.$this->rates($plan)['recovery'].'% of revenue until it is fully repaid.'
            );
        }

        return back()->with('success', 'Plan '.$plan->plan_number.' created'.($amount > 0 ? ' with ₹'.number_format($amount, 2).' to recover.' : '. Add the advance with "Add cost".'));
    }

    private function checkScope(User $user, $labelId, $artistId): void
    {
        if ($labelId && $artistId) {
            throw ValidationException::withMessages(['label_id' => 'Choose the whole account, one label or one artist - not both.']);
        }

        if ($labelId && ! DB::table('labels')->where('id', $labelId)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['label_id' => 'This label does not belong to the chosen owner, so nothing would ever be recovered.']);
        }

        if ($artistId && ! DB::table('artists')->where('id', $artistId)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['artist_id' => 'This artist does not belong to the chosen owner, so nothing would ever be recovered.']);
        }
    }

    public function addExpense(StoreRecoupmentExpenseRequest $request, RecoupmentPlan $plan)
    {
        $data = $request->validated();
        $recoverable = (bool) $data['is_recoverable'];
        $amountText = number_format((float) $data['amount'], 2, '.', '');

        if ($plan->status === 'cancelled') {
            throw ValidationException::withMessages(['plan' => 'This plan is cancelled. Start a new plan for this owner.']);
        }

        if (
            $recoverable && $plan->status === 'completed'
            && RecoupmentPlan::query()->where('user_id', $plan->user_id)->where('status', 'active')->whereKeyNot($plan->id)->exists()
        ) {
            throw ValidationException::withMessages(['plan' => 'This owner already has another active plan. Add the cost there.']);
        }

        if ($recoverable) {
            app(ActionOtpGuard::class)->require(
                Auth::user(),
                'recoupment.expense:'.$plan->id.':'.$amountText,
                'add '.$amountText.' INR to recover in plan '.$plan->plan_number
            );
        }

        $expense = $this->management->addExpense($plan, $data, Auth::user());

        FinanceAudit::log(
            'finance.recoupment_expense_added',
            'Cost of '.$amountText.' ('.($recoverable ? 'recoverable' : 'not recovered').') added to plan '.$plan->plan_number.'.',
            FinanceAudit::pick($expense, ['id', 'recoupment_plan_id', 'category', 'title', 'amount', 'is_recoverable', 'expense_date', 'reference_number']),
            $plan
        );

        if ($recoverable && $request->boolean('notify_owner', true)) {
            $this->tellOwner(
                $plan->user,
                'Cost added to your advance',
                $data['title'].' - ₹'.number_format((float) $data['amount'], 2).' was added to the amount recovered from your royalties ('.$plan->plan_number.'). '
                    .'Still to recover: ₹'.number_format((float) $plan->fresh()->outstanding_amount, 2).'.'
            );
        }

        return back()->with('success', $recoverable ? 'Cost added - it will be recovered from future royalties.' : 'Cost noted (not recovered from the owner).');
    }

    /** Take a wrong cost out of recovery (kept in history, marked removed). */
    public function removeExpense(Request $request, RecoupmentPlan $plan, RecoupmentExpense $expense)
    {
        abort_unless((int) $expense->recoupment_plan_id === (int) $plan->id, 404);

        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']])['reason'];

        if (! $expense->is_recoverable) {
            throw ValidationException::withMessages(['expense' => 'This cost is already not recovered.']);
        }

        if ($plan->status === 'cancelled') {
            throw ValidationException::withMessages(['expense' => 'This plan is cancelled; nothing more is recovered anyway.']);
        }

        $left = round((float) $plan->total_recoverable_amount - (float) $expense->amount, 2);

        if ($left < round((float) $plan->total_recovered_amount, 2)) {
            throw ValidationException::withMessages([
                'expense' => '₹'.number_format((float) $plan->total_recovered_amount, 2).' is already recovered - more than would be left (₹'.number_format(max(0, $left), 2).'). '
                    .'Pay the extra back with Financial Adjustments first, or cancel the plan.',
            ]);
        }

        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'recoupment.expense_remove:'.$expense->id,
            'take '.number_format((float) $expense->amount, 2, '.', '').' INR ('.$expense->title.') out of plan '.$plan->plan_number
        );

        $before = FinanceAudit::pick($plan, ['id', 'total_recoverable_amount', 'total_recovered_amount', 'outstanding_amount', 'status']);

        DB::transaction(function () use ($plan, $expense, $reason) {
            $expense->update([
                'is_recoverable' => false,
                'description' => trim(($expense->description ? $expense->description."\n" : '')
                    .'[Removed from recovery '.now()->toDateString().' by '.(Auth::user()?->name ?? 'admin').': '.$reason.']'),
            ]);

            $this->management->recalculatePlanTotals(RecoupmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail());
        });

        FinanceAudit::log(
            'finance.recoupment_expense_removed',
            'Cost #'.$expense->id.' ('.$expense->title.', '.$expense->amount.') taken out of plan '.$plan->plan_number.': '.$reason,
            FinanceAudit::pick($plan->fresh(), ['id', 'total_recoverable_amount', 'total_recovered_amount', 'outstanding_amount', 'status']),
            $plan,
            $before
        );

        if ($request->boolean('notify_owner', true)) {
            $this->tellOwner(
                $plan->user,
                'Advance corrected',
                $expense->title.' (₹'.number_format((float) $expense->amount, 2).') was removed from the amount recovered from your royalties. '
                    .'Still to recover: ₹'.number_format((float) $plan->fresh()->outstanding_amount, 2).'.'
            );
        }

        return back()->with('success', 'Cost taken out of recovery.');
    }

    /** Change title / notes / rates of a plan that is not cancelled. */
    public function update(Request $request, RecoupmentPlan $plan)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'base_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'recovery_uplift_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'lte:base_percentage'],
            'maximum_recovery_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'lte:base_percentage'],
        ], [], [
            'base_percentage' => 'owner share',
            'recovery_uplift_percentage' => 'recovery %',
            'maximum_recovery_percentage' => 'maximum recovery %',
        ]);

        if ($plan->status === 'cancelled') {
            throw ValidationException::withMessages(['plan' => 'A cancelled plan cannot be changed.']);
        }

        $ratesChanged = round((float) $plan->base_percentage, 4) !== round((float) $data['base_percentage'], 4)
            || round((float) $plan->recovery_uplift_percentage, 4) !== round((float) $data['recovery_uplift_percentage'], 4)
            || ($plan->maximum_recovery_percentage === null ? null : round((float) $plan->maximum_recovery_percentage, 4))
                !== (($data['maximum_recovery_percentage'] ?? null) === null ? null : round((float) $data['maximum_recovery_percentage'], 4));

        if ($ratesChanged) {
            app(ActionOtpGuard::class)->require(
                Auth::user(),
                'recoupment.update:'.$plan->id.':'.$data['base_percentage'].':'.$data['recovery_uplift_percentage'].':'.($data['maximum_recovery_percentage'] ?? '-'),
                'change the recovery rates of plan '.$plan->plan_number
            );
        }

        $before = FinanceAudit::pick($plan, ['id', 'title', 'base_percentage', 'recovery_uplift_percentage', 'maximum_recovery_percentage']);

        $plan->update([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'base_percentage' => round((float) $data['base_percentage'], 4),
            'recovery_uplift_percentage' => round((float) $data['recovery_uplift_percentage'], 4),
            'maximum_recovery_percentage' => isset($data['maximum_recovery_percentage']) ? round((float) $data['maximum_recovery_percentage'], 4) : null,
        ]);

        FinanceAudit::log(
            'finance.recoupment_plan_updated',
            'Plan '.$plan->plan_number.' changed'.($ratesChanged ? ' (rates)' : '').'.',
            FinanceAudit::pick($plan->fresh(), ['id', 'title', 'base_percentage', 'recovery_uplift_percentage', 'maximum_recovery_percentage']),
            $plan,
            $before
        );

        if ($ratesChanged && $plan->status === 'active' && $request->boolean('notify_owner', true)) {
            $this->tellOwner(
                $plan->user,
                'Advance recovery rate changed',
                'From now on '.$this->rates($plan->fresh())['recovery'].'% of revenue is used to repay your advance ('.$plan->plan_number.').'
            );
        }

        return back()->with('success', 'Plan saved.');
    }

    public function cancel(Request $request, RecoupmentPlan $plan)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        if ($plan->status !== 'active') {
            throw ValidationException::withMessages(['plan' => 'Only an active plan can be cancelled.']);
        }

        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'recoupment.cancel:'.$plan->id,
            'cancel recoupment plan '.($plan->plan_number ?: '#'.$plan->id)
        );

        $before = FinanceAudit::pick($plan, ['id', 'plan_number', 'user_id', 'status', 'total_recoverable_amount', 'total_recovered_amount', 'outstanding_amount']);

        $this->management->cancelPlan($plan, Auth::user());

        FinanceAudit::log(
            'finance.recoupment_plan_cancelled',
            'Recoupment plan '.($plan->plan_number ?: '#'.$plan->id).' cancelled: '.$validated['reason'],
            FinanceAudit::pick($plan->fresh(), ['id', 'status', 'completed_on', 'completed_by']) + ['reason' => $validated['reason']],
            $plan,
            $before
        );

        if ($request->boolean('notify_owner', true)) {
            $this->tellOwner(
                $plan->user,
                'Advance recovery stopped',
                'Nothing more will be recovered from your royalties for '.$plan->plan_number.'.'
            );
        }

        return back()->with('success', 'Recoupment plan cancelled. Nothing more will be recovered from this owner.');
    }

    public function details(RecoupmentPlan $plan): JsonResponse
    {
        $plan->load([
            'user:id,name,email,role',
            'label:id,name,user_id',
            'artist:id,stage_name,legal_name,user_id,label_id',
            'creator:id,name',
            'completer:id,name',
            'expenses' => fn ($q) => $q->orderByDesc('expense_date')->orderByDesc('id'),
            'recoveries' => fn ($q) => $q->orderByDesc('reporting_month')->orderByDesc('id'),
            'documents' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $plan->setAttribute('rates', $this->rates($plan));

        return response()->json(['data' => $plan]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with(['user:id,name,email,role', 'label:id,name', 'artist:id,stage_name,legal_name'])->orderByDesc('id');

        FinanceAudit::log('finance.recoupment_exported', 'Recoupment plans exported (CSV).', ['filters' => $request->only(['search', 'status'])]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Plan', 'Title', 'Owner', 'E-mail', 'Role', 'Scope', 'Owner share %', 'Recovery %', 'Max recovery %', 'To recover', 'Recovered', 'Outstanding', 'Status', 'Started', 'Closed']);
            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->plan_number, $p->title, $p->user?->name, $p->user?->email, $p->user?->role,
                        $p->label ? 'Label: '.$p->label->name : ($p->artist ? 'Artist: '.($p->artist->stage_name ?: $p->artist->legal_name) : 'Whole account'),
                        $p->base_percentage, $p->recovery_uplift_percentage, $p->maximum_recovery_percentage,
                        number_format((float) $p->total_recoverable_amount, 2, '.', ''),
                        number_format((float) $p->total_recovered_amount, 2, '.', ''),
                        number_format((float) $p->outstanding_amount, 2, '.', ''),
                        $p->status, $p->starts_on?->format('Y-m-d'), $p->completed_on?->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, 'recoupment-plans-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadDocument(RecoupmentPlan $plan, RecoupmentDocument $document): BinaryFileResponse
    {
        abort_unless((int) $document->recoupment_plan_id === (int) $plan->id, 404);

        $path = $this->documents->downloadPath($document);

        return response()->download($path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function uploadDocument(StoreRecoupmentDocumentRequest $request, RecoupmentPlan $plan)
    {
        $data = $request->validated();

        if (
            ! empty($data['recoupment_expense_id'])
            && ! $plan->expenses()->whereKey($data['recoupment_expense_id'])->exists()
        ) {
            throw ValidationException::withMessages(['recoupment_expense_id' => 'That cost is not part of this plan.']);
        }

        $document = $this->documents->store(
            $plan,
            $request->file('document'),
            $data['document_type'],
            $data['recoupment_expense_id'] ?? null,
            Auth::user()
        );

        FinanceAudit::log(
            'finance.recoupment_document_uploaded',
            'Document '.$document->original_name.' added to plan '.$plan->plan_number.'.',
            FinanceAudit::pick($document, ['id', 'recoupment_plan_id', 'recoupment_expense_id', 'document_type', 'original_name']),
            $plan
        );

        return back()->with('success', 'Document uploaded.');
    }

    private function tellOwner(?User $owner, string $title, string $message): void
    {
        if (! $owner) {
            return;
        }

        try {
            app(\App\Services\V2\PanelNotificationService::class)->send($owner, 'recoupment.changed', $title, $message, '/v2/wallet');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
