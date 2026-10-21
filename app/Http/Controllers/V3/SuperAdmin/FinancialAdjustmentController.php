<?php

namespace App\Http\Controllers\V3\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\Finance\ReverseFinancialAdjustmentRequest;
use App\Http\Requests\V3\Finance\StoreFinancialAdjustmentRequest;
use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use App\Services\V3\FinancialAdjustmentService;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\FinanceAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinancialAdjustmentController extends Controller
{
    public function __construct(
        private FinancialAdjustmentService $service
    ) {
    }

    /*
     * MIXXTUNE_ADJUSTMENTS_V2
     * - summary counted only on live adjustments (before: a reversal showed
     *   up as a "posted debit", so a credit + its reversal looked like -₹1);
     * - filters, search, CSV; owners found by search (before: a dropdown
     *   limited to the first 500 owners A–Z, the rest could not be chosen);
     * - the owner's wallet balance is shown before posting.
     */
    private function filtered(\Illuminate\Http\Request $request)
    {
        $q = FinancialAdjustment::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $q->where(fn ($w) => $w->where('adjustment_number', 'like', $like)
                ->orWhere('reference_number', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like)));
        }

        if (in_array($request->query('direction'), ['credit', 'debit'], true)) {
            $q->where('direction', $request->query('direction'));
        }

        match ((string) $request->query('show', '')) {
            'active' => $q->where('status', 'posted')->whereNull('reversal_of_id'),
            'reversed' => $q->where('status', 'reversed'),
            'reversals' => $q->whereNotNull('reversal_of_id'),
            default => null,
        };

        $month = (string) $request->query('month', '');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $q->where('created_at', 'like', $month.'%');
        }

        return $q;
    }

    public function index(\Illuminate\Http\Request $request): Response
    {
        $adjustments = $this->filtered($request)
            ->with([
                'user:id,name,email,role',
                'walletTransaction:id,transaction_type,direction,amount,currency,balance_before,balance_after',
                'creator:id,name,email',
                'reversedBy:id,name,email',
                'reversalOf:id,adjustment_number,direction,amount,status',
            ])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $live = fn () => FinancialAdjustment::query()->where('status', 'posted')->whereNull('reversal_of_id');

        $credits = (float) $live()->where('direction', 'credit')->sum('amount');
        $debits = (float) $live()->where('direction', 'debit')->sum('amount');

        $summary = [
            'credits' => $credits,
            'debits' => $debits,
            'net' => round($credits - $debits, 2),
            'posted' => $live()->count(),
            'reversed' => FinancialAdjustment::query()->where('status', 'reversed')->count(),
            'reversals' => FinancialAdjustment::query()->whereNotNull('reversal_of_id')->count(),
        ];

        return Inertia::render(
            'V3/SuperAdmin/Adjustments/Index',
            [
                'adjustments' => $adjustments,
                'summary' => $summary,
                'filters' => [
                    'search' => (string) $request->query('search', ''),
                    'direction' => (string) $request->query('direction', ''),
                    'show' => (string) $request->query('show', ''),
                    'month' => (string) $request->query('month', ''),
                ],
            ]
        );
    }

    /** Owners (labels / artists) by name, e-mail or client ID, with their wallet balance. */
    public function owners(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
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

        $balances = DB::table('wallets')->whereIn('user_id', $owners->pluck('id'))
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(available_balance) as available'), DB::raw('SUM(pending_balance) as pending'))
            ->get()->keyBy('user_id');

        return response()->json([
            'owners' => $owners->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'email' => $o->email,
                'role' => $o->role,
                'client_id' => $o->client_id,
                'available' => round((float) ($balances[$o->id]->available ?? 0), 2),
                'pending' => round((float) ($balances[$o->id]->pending ?? 0), 2),
            ])->values(),
        ]);
    }

    public function export(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows = $this->filtered($request)->with(['user:id,name,email,role', 'creator:id,name', 'walletTransaction:id,balance_before,balance_after'])->orderByDesc('id');

        FinanceAudit::log('finance.adjustments_exported', 'Adjustment history exported (CSV).', ['filters' => $request->only(['search', 'direction', 'show', 'month'])]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Adjustment', 'Date', 'Owner', 'E-mail', 'Role', 'Direction', 'Amount', 'Currency', 'Balance before', 'Balance after', 'Status', 'Reversal of', 'Reason', 'Reference', 'Posted by']);
            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, [
                        $a->adjustment_number,
                        $a->created_at?->copy()->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
                        $a->user?->name, $a->user?->email, $a->user?->role,
                        $a->direction, number_format((float) $a->amount, 2, '.', ''), $a->currency ?? 'INR',
                        $a->walletTransaction?->balance_before, $a->walletTransaction?->balance_after,
                        $a->status, $a->reversal_of_id ? 'yes' : '', $a->reason, $a->reference_number, $a->creator?->name,
                    ]);
                }
            });
            fclose($out);
        }, 'wallet-adjustments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* MIXXTUNE_ADJUSTMENTS_V2: panel notice to the owner (switch on the form) */
    private function tellOwner(?User $owner, string $title, string $message): void
    {
        if (!$owner) {
            return;
        }

        try {
            app(\App\Services\V2\PanelNotificationService::class)->send($owner, 'wallet.adjusted', $title, $message, '/v2/wallet');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function store(
        StoreFinancialAdjustmentRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $user = User::query()
            ->findOrFail(
                $data['user_id']
            );

        /*
         * MIXXTUNE_FINANCE_SAFETY_V1: a wallet credit / debit changes an
         * owner's balance directly, so it needs the e-mailed code (one code
         * covers exactly this owner, direction and amount).
         */
        $amountText = number_format((float) $data['amount'], 2, '.', '');

        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'adjustment.post:'.$user->id.':'.$data['direction'].':'.$amountText,
            ($data['direction'] === 'debit' ? 'debit' : 'credit')
                .' '.$amountText.' '.strtoupper($data['currency'] ?? 'INR')
                .' '.($data['direction'] === 'debit' ? 'from' : 'to')
                .' the wallet of '.($user->name ?: $user->email)
        );

        $adjustment = $this->service->post(
            $user,
            $data['direction'],
            (float) $data['amount'],
            $data['reason'],
            [
                'reference_number' =>
                    $data[
                        'reference_number'
                    ]
                    ?? null,

                'idempotency_key' =>
                    $data[
                        'idempotency_key'
                    ],

                'internal_note' =>
                    $data[
                        'internal_note'
                    ]
                    ?? null,

                'effective_date' =>
                    $data[
                        'effective_date'
                    ]
                    ?? null,

                'type' =>
                    $data['type']
                    ?? 'manual',

                'currency' =>
                    strtoupper(
                        $data['currency']
                        ?? 'INR'
                    ),
            ],
            Auth::user()
        );

        FinanceAudit::log(
            'finance.adjustment_posted',
            'Wallet '.$data['direction'].' of '.$amountText.' for user #'.$user->id.' ('.($user->email ?? '-').'): '.$data['reason'],
            FinanceAudit::pick($adjustment instanceof FinancialAdjustment ? $adjustment : null, [
                'id', 'adjustment_number', 'user_id', 'direction', 'amount', 'type', 'reason', 'reference_number', 'status',
            ]) ?: ['user_id' => $user->id, 'direction' => $data['direction'], 'amount' => $amountText],
            $adjustment instanceof FinancialAdjustment ? $adjustment : $user
        );

        if ($request->boolean('notify_owner', true)) {
            $this->tellOwner(
                $user,
                $data['direction'] === 'credit' ? 'Money added to your wallet' : 'Money deducted from your wallet',
                ($data['direction'] === 'credit' ? '₹'.number_format((float) $data['amount'], 2).' was added to' : '₹'.number_format((float) $data['amount'], 2).' was deducted from')
                    .' your wallet. Reason: '.$data['reason']
            );
        }

        return back()->with(
            'success',
            'Financial adjustment posted'.($request->boolean('notify_owner', true) ? ' and the owner was told.' : '.')
        );
    }

    public function reverse(
        ReverseFinancialAdjustmentRequest $request,
        FinancialAdjustment $adjustment
    ): RedirectResponse {
        app(ActionOtpGuard::class)->require(
            Auth::user(),
            'adjustment.reverse:'.$adjustment->id,
            'reverse adjustment '.($adjustment->adjustment_number ?: '#'.$adjustment->id)
        );

        $before = FinanceAudit::pick($adjustment, ['id', 'adjustment_number', 'user_id', 'direction', 'amount', 'status']);

        $this->service->reverse(
            $adjustment,
            $request->validated()['reason'],
            Auth::user()
        );

        FinanceAudit::log(
            'finance.adjustment_reversed',
            'Adjustment '.($adjustment->adjustment_number ?: '#'.$adjustment->id).' reversed: '.$request->validated()['reason'],
            FinanceAudit::pick($adjustment->fresh(), ['id', 'status', 'reversed_by', 'reversed_at']),
            $adjustment,
            $before
        );

        if ($request->boolean('notify_owner')) {
            $this->tellOwner(
                $adjustment->user,
                'Wallet correction',
                'An earlier '.$adjustment->direction.' of ₹'.number_format((float) $adjustment->amount, 2).' to your wallet was reversed. Reason: '.$request->validated()['reason']
            );
        }

        return back()->with(
            'success',
            'Financial adjustment reversed.'
        );
    }
}
