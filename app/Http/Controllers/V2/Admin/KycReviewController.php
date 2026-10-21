<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\User;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\AuditLogService;
use App\Services\V2\KycChecks;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * MIXXTUNE_KYC_REVIEW_V1
 *
 * KYC Reviews: who needs review, who has not submitted, who has no payout
 * profile at all, automatic checks, verify / reject / send back with the
 * owner told, full PAN / account shown to Super Admin only (audited),
 * reminders, and the history of every decision.
 */
class KycReviewController extends Controller
{
    private const TABS = ['submitted', 'pending', 'missing', 'verified', 'rejected', 'all'];

    public const REJECT_REASONS = [
        'PAN name does not match the bank account holder name',
        'IFSC code is wrong for this bank / branch',
        'Bank account number could not be confirmed',
        'PAN number is not valid',
        'GST number does not belong to this PAN',
        'Address is incomplete',
        'Same bank account is used by another account',
    ];

    public function __construct(
        private readonly KycChecks $checks,
        private readonly AdminFinancialAccessService $access,
        private readonly PanelNotificationService $notices,
        private readonly AuditLogService $audit,
    ) {
    }

    private function authorize(Request $request, PermissionService $permissions): string
    {
        $role = $permissions->role($request->user());
        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Admin access required.');

        return $role;
    }

    private function scopeIds(Request $request): ?Collection
    {
        return $request->user()->role === 'super_admin' ? null : $this->access->accessibleUserIds($request->user());
    }

    private function audit(string $action, string $text, PayoutProfile $profile, array $new = [], array $old = []): void
    {
        try {
            $this->audit->record($action, 'kyc', $text, $old, $new, $profile, request());
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $role = $this->authorize($request, $permissions);
        $scope = $this->scopeIds($request);

        $tab = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'submitted';
        $search = trim((string) $request->query('search', ''));
        $ownerRole = in_array($request->query('role'), ['label', 'artist'], true) ? $request->query('role') : '';

        $base = PayoutProfile::query();
        if ($scope) {
            $base->whereIn('user_id', $scope);
        }

        $missingBase = User::query()
            ->whereIn('role', ['label', 'artist'])
            ->where('account_status', 'active')
            ->whereNotIn('id', PayoutProfile::query()->select('user_id'));
        if ($scope) {
            $missingBase->whereIn('id', $scope);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $counts = [
            'submitted' => (clone $base)->where('kyc_status', 'submitted')->count(),
            'pending' => (clone $base)->where(fn ($w) => $w->whereNull('kyc_status')->orWhereNotIn('kyc_status', ['submitted', 'verified', 'rejected']))->count(),
            'missing' => (clone $missingBase)->count(),
            'verified' => (clone $base)->where('kyc_status', 'verified')->count(),
            'rejected' => (clone $base)->where('kyc_status', 'rejected')->count(),
            'all' => (clone $base)->count(),
        ];

        if ($tab === 'missing') {
            $q = (clone $missingBase);
            if ($search !== '') {
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like));
            }
            if ($ownerRole) {
                $q->where('role', $ownerRole);
            }

            $page = $q->orderBy('name')->paginate(30, ['id', 'name', 'email', 'role', 'client_id'])->withQueryString();
            $balances = $this->balances($page->getCollection()->pluck('id'));
            $page->getCollection()->transform(fn ($u) => [
                'user_id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'client_id' => $u->client_id,
                'available' => $balances[$u->id] ?? 0,
            ]);

            return $this->render($role, $tab, $search, $ownerRole, $counts, null, $page);
        }

        $q = (clone $base)->with(['user:id,name,email,role,client_id,phone']);
        match ($tab) {
            'pending' => $q->where(fn ($w) => $w->whereNull('kyc_status')->orWhereNotIn('kyc_status', ['submitted', 'verified', 'rejected'])),
            'all' => null,
            default => $q->where('kyc_status', $tab),
        };

        if ($search !== '') {
            $q->where(fn ($w) => $w->where('account_holder_name', 'like', $like)
                ->orWhere('ifsc_code', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like)));
        }
        if ($ownerRole) {
            $q->whereHas('user', fn ($u) => $u->where('role', $ownerRole));
        }

        $page = $q->orderBy($tab === 'submitted' ? 'updated_at' : 'id', $tab === 'submitted' ? 'asc' : 'desc')->paginate(20)->withQueryString();

        $ids = $page->getCollection()->pluck('user_id');
        $balances = $this->balances($ids);
        $pendingW = Schema::hasTable('withdrawals')
            ? DB::table('withdrawals')->whereIn('user_id', $ids)->whereIn('status', ['pending', 'approved', 'processing'])
                ->groupBy('user_id')->select('user_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(amount) as total'))->get()->keyBy('user_id')
            : collect();
        $verifiers = User::query()->whereIn('id', $page->getCollection()->pluck('verified_by')->filter())->pluck('name', 'id');

        $page->getCollection()->transform(function (PayoutProfile $p) use ($balances, $pendingW, $verifiers) {
            $checks = $this->checks->run($p);

            return [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'user' => $p->user ? $p->user->only(['name', 'email', 'role', 'client_id', 'phone']) : null,
                'kyc_status' => $p->kyc_status ?: 'pending',
                'kyc_notes' => $p->kyc_notes,
                'account_holder_name' => $p->account_holder_name,
                'masked_bank_account' => $p->masked_bank_account,
                'masked_pan' => $p->masked_pan,
                'bank_name' => $p->bank_name,
                'ifsc_code' => $p->ifsc_code,
                'branch_name' => $p->branch_name,
                'gst_number' => $p->gst_number,
                'address' => collect([$p->address_line_1, $p->address_line_2, $p->city, $p->state, $p->postal_code, $p->country_code])->filter()->implode(', '),
                'updated_at' => $p->updated_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'waiting_days' => $p->kyc_status === 'submitted' && $p->updated_at ? (int) $p->updated_at->diffInDays(now()) : null,
                'verified_at' => $p->verified_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'verified_by' => $p->verified_by ? ($verifiers[$p->verified_by] ?? null) : null,
                'checks' => $checks,
                'summary' => KycChecks::summary($checks),
                'available' => $balances[$p->user_id] ?? 0,
                'pending_withdrawals' => (int) ($pendingW[$p->user_id]->n ?? 0),
                'pending_withdrawal_total' => (float) ($pendingW[$p->user_id]->total ?? 0),
            ];
        });

        return $this->render($role, $tab, $search, $ownerRole, $counts, $page, null);
    }

    private function balances(Collection $userIds): array
    {
        if ($userIds->isEmpty() || !Schema::hasTable('wallets')) {
            return [];
        }

        return DB::table('wallets')->whereIn('user_id', $userIds)
            ->groupBy('user_id')->select('user_id', DB::raw('SUM(available_balance) as total'))
            ->pluck('total', 'user_id')->map(fn ($v) => round((float) $v, 2))->all();
    }

    private function render(string $role, string $tab, string $search, string $ownerRole, array $counts, $profiles, $missing): Response
    {
        return Inertia::render('V2/Admin/Kyc/Index', [
            'role' => $role,
            'filters' => ['status' => $tab, 'search' => $search, 'role' => $ownerRole],
            'counts' => $counts,
            'profiles' => $profiles,
            'missing' => $missing,
            'rejectReasons' => self::REJECT_REASONS,
        ]);
    }

    public function history(Request $request, PayoutProfile $profile, PermissionService $permissions): JsonResponse
    {
        $this->authorize($request, $permissions);
        abort_unless($this->access->canAccessUser($request->user(), (int) $profile->user_id), 403);

        if (!Schema::hasTable('audit_logs')) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->where('audit_logs.auditable_type', PayoutProfile::class)
            ->where('audit_logs.auditable_id', $profile->id)
            ->orderByDesc('audit_logs.id')->limit(30)
            ->get(['audit_logs.action', 'audit_logs.description', 'audit_logs.created_at', 'users.name as by'])
            ->map(fn ($r) => [
                'action' => $r->action,
                'text' => $r->description,
                'by' => $r->by,
                'at' => \Illuminate\Support\Carbon::parse($r->created_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            ]);

        return response()->json(['items' => $items]);
    }

    public function decide(Request $request, PayoutProfile $profile, PermissionService $permissions): RedirectResponse
    {
        $this->authorize($request, $permissions);
        abort_unless($this->access->canAccessUser($request->user(), (int) $profile->user_id), 403, 'KYC profile outside assigned scope.');

        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected,pending'],
            'kyc_notes' => ['nullable', 'string', 'max:3000', 'required_if:status,rejected', 'required_if:status,pending'],
            'override' => ['nullable', 'boolean'],
        ], [
            'kyc_notes.required_if' => 'Write the reason — the owner sees it.',
        ]);

        $status = $validated['status'];
        $note = trim((string) ($validated['kyc_notes'] ?? ''));

        if ($status === 'verified') {
            $fails = collect($this->checks->run($profile))->where('state', 'fail');

            if ($fails->isNotEmpty() && (!($validated['override'] ?? false) || $note === '')) {
                return back()->withErrors([
                    'kyc_notes' => 'Checks failed: '.$fails->pluck('label')->implode(', ')
                        .'. Fix the details on the user page, or tick "Verify anyway" and write why.',
                ]);
            }
        }

        $old = ['kyc_status' => $profile->kyc_status, 'kyc_notes' => $profile->kyc_notes];

        $profile->forceFill([
            'kyc_status' => $status,
            'kyc_notes' => $note !== '' ? $note : null,
            'verified_at' => $status === 'verified' ? now() : null,
            'verified_by' => $request->user()->id,
        ])->save();

        try {
            $profile->user()->update(['kyc_status' => $status]);
        } catch (Throwable $e) {
            report($e);
        }

        $words = ['verified' => 'verified', 'rejected' => 'rejected', 'pending' => 'sent back to the owner'];
        $this->audit('kyc.'.($status === 'pending' ? 'sent_back' : $status), 'KYC of '.($profile->user?->name ?? '#'.$profile->user_id).' '.$words[$status].($note ? ': '.$note : '').'.', $profile, ['kyc_status' => $status, 'kyc_notes' => $note], $old);

        if ($profile->user) {
            try {
                match ($status) {
                    'verified' => $this->notices->send($profile->user, 'kyc.verified', 'Your KYC is verified', 'You can now request payouts to your bank account.', '/v2/withdrawals'),
                    'rejected' => $this->notices->send($profile->user, 'kyc.rejected', 'Your KYC needs a correction', $note, '/v2/kyc-profile'),
                    default => $this->notices->send($profile->user, 'kyc.sent_back', 'Please check your KYC details', $note, '/v2/kyc-profile'),
                };
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'KYC of '.($profile->user?->name ?? 'owner').' '.$words[$status].'. The owner was told.');
    }

    /** Full PAN / account number, Super Admin only, every view is audited. */
    public function reveal(Request $request, PayoutProfile $profile): JsonResponse
    {
        abort_unless($request->user()?->role === 'super_admin', 403);

        $this->audit('kyc.revealed', 'Super Admin viewed full PAN / bank account of '.($profile->user?->name ?? '#'.$profile->user_id).'.', $profile);

        return response()->json([
            'pan' => $profile->pan_number,
            'account' => $profile->bank_account_number,
        ]);
    }

    /** Panel notice asking owners to finish / submit KYC. */
    public function remind(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->authorize($request, $permissions);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer'],
        ]);

        $ids = collect($validated['user_ids'])->map(fn ($i) => (int) $i)->unique()
            ->filter(fn ($id) => $this->access->canAccessUser($request->user(), $id));

        $users = User::query()->whereIn('id', $ids)->whereIn('role', ['label', 'artist'])->where('account_status', 'active')->get();
        $profiles = PayoutProfile::query()->whereIn('user_id', $users->pluck('id'))->pluck('kyc_status', 'user_id');

        $sent = 0;
        foreach ($users as $u) {
            if (($profiles[$u->id] ?? null) === 'verified') {
                continue;
            }

            $this->notices->send(
                $u,
                'kyc.reminder',
                'Complete your KYC to receive payouts',
                isset($profiles[$u->id])
                    ? 'Please check your payout details and press "Submit for Verification".'
                    : 'Please send us your PAN, bank account number, IFSC and address (reply to our e-mail or open a support ticket) so we can set up your payouts.',
                isset($profiles[$u->id]) ? '/v2/kyc-profile' : '/v2/support/create?category=kyc'
            );
            $sent++;
        }

        try {
            $this->audit->record('kyc.reminders_sent', 'kyc', 'KYC reminder sent to '.$sent.' owner(s).', [], ['user_ids' => $users->pluck('id')->all()], null, $request);
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Reminder sent to '.$sent.' owner(s).');
    }
}
