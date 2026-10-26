<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\BankPayout;
use App\Models\Finance\PaymentGatewaySetting;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\AuditLogService;
use App\Services\V2\Payouts\BankPayoutService;
use App\Services\V2\Payouts\RazorpayError;
use App\Services\V2\Payouts\RazorpayX;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * Super Admin > Financial > Razorpay Payouts: connect RazorpayX (keys are stored encrypted and never shown
 * again), test the connection, switch on/off, and see every bank transfer the panel sent.
 */
class RazorpayPayoutController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can manage Razorpay payouts.');
    }

    public static function isStagingSite(): bool
    {
        return str_contains((string) parse_url((string) config('app.url'), PHP_URL_HOST), 'staging');
    }

    public function index(Request $request, PermissionService $permissions, RazorpayX $rzp): Response
    {
        $this->superAdmin($request, $permissions);
        $s = PaymentGatewaySetting::razorpay();
        $filter = (string) $request->query('status', '');
        $q = BankPayout::query()->with(['withdrawal:id,withdrawal_number,user_id', 'salaryItem.run:id,month', 'creator:id,name'])->latest('id');
        if ($filter === 'open') {
            $q->whereIn('status', BankPayout::OPEN);
        } elseif ($filter === 'failed') {
            $q->whereIn('status', BankPayout::FAILED);
        } elseif ($filter === 'processed') {
            $q->where('status', 'processed');
        }
        $page = $q->paginate(30)->withQueryString()->through(fn (BankPayout $p) => [
            'id' => $p->id,
            'ref' => $p->public_id,
            'purpose' => $p->purpose,
            'what' => $p->purpose === 'withdrawal' ? ($p->withdrawal?->withdrawal_number ?: 'Withdrawal #'.$p->withdrawal_id) : 'Salary '.($p->salaryItem?->run?->month ?? ''),
            'payee' => $p->payee_name,
            'account' => $p->account_hint,
            'amount' => $p->rupees(),
            'mode' => $p->mode,
            'live' => $p->live,
            'status' => $p->status,
            'utr' => $p->utr,
            'razorpay_id' => $p->razorpay_payout_id,
            'reason' => $p->failure_reason,
            'settled' => $p->settled,
            'reversed_after_paid' => $p->reversed_after_paid,
            'by' => $p->creator?->name,
            'at' => $p->created_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            'checked' => $p->last_checked_at?->copy()->timezone('Asia/Kolkata')->format('d M, h:i A'),
        ]);

        return Inertia::render('V2/Admin/Razorpay/Index', [
            'role' => 'super_admin',
            'settings' => [
                'enabled' => $s->enabled,
                'key_id' => $s->key_id,
                'has_secret' => (string) $s->key_secret !== '',
                'has_webhook_secret' => (string) $s->webhook_secret !== '',
                'account_hint' => (string) $s->account_number !== '' ? '••••'.substr((string) $s->account_number, -4) : null,
                'narration' => $s->narration ?: 'Mixx Tune',
                'configured' => $rzp->configured($s),
                'live' => RazorpayX::isLiveKey($s->key_id),
                'updated_at' => $s->updated_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            ],
            'webhookUrl' => url('/api/webhooks/razorpayx'),
            'stagingSite' => self::isStagingSite(),
            'counts' => [
                'open' => BankPayout::query()->whereIn('status', BankPayout::OPEN)->count(),
                'failed' => BankPayout::query()->whereIn('status', BankPayout::FAILED)->where('created_at', '>', now()->subDays(30))->count(),
                'processed' => BankPayout::query()->where('status', 'processed')->count(),
            ],
            'filter' => $filter,
            'payouts' => $page,
        ]);
    }

    public function save(Request $request, PermissionService $permissions, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'key_id' => ['nullable', 'string', 'max:80', 'regex:/^rzp_(test|live)_[A-Za-z0-9]+$/'],
            'key_secret' => ['nullable', 'string', 'max:200'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
            'account_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/'],
            'narration' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9 ]*$/'],
        ], [
            'key_id.regex' => 'The Key Id starts with rzp_test_ or rzp_live_.',
            'account_number.regex' => 'Account number: letters and digits only (no spaces).',
            'narration.regex' => 'Bank statement text: letters, digits and spaces only.',
        ]);
        if (RazorpayX::isLiveKey($data['key_id'] ?? null) && self::isStagingSite()) {
            return back()->withErrors(['key_id' => 'This is the staging site - use the TEST keys (rzp_test_...) here, never live keys.']);
        }
        app(ActionOtpGuard::class)->require($request->user(), 'razorpay.settings', 'change the Razorpay payout settings');

        $s = PaymentGatewaySetting::razorpay();
        if (($data['key_id'] ?? $s->key_id) !== $s->key_id && $s->key_id && BankPayout::query()->whereIn('status', BankPayout::OPEN)->exists()) {
            return back()->withErrors(['key_id' => 'Some payouts are still running with the present keys - wait until they are finished before changing the Key Id.']);
        }
        $before = ['enabled' => $s->enabled, 'key_id' => $s->key_id, 'narration' => $s->narration];
        $s->enabled = (bool) $data['enabled'];
        $s->key_id = $data['key_id'] ?? $s->key_id;
        foreach (['key_secret', 'webhook_secret', 'account_number'] as $f) {
            if (($data[$f] ?? '') !== '') {
                $s->{$f} = trim((string) $data[$f]);
            }
        }
        $s->narration = trim((string) ($data['narration'] ?? '')) ?: 'Mixx Tune';
        $s->updated_by = $request->user()->id;
        if ($s->enabled && !app(RazorpayX::class)->configured($s)) {
            return back()->withErrors(['enabled' => 'Fill in Key Id, Key Secret and the RazorpayX account number before switching on.']);
        }
        $s->save();
        $audit->record('payout.razorpay_settings', 'finance', 'Razorpay payout settings changed'.($s->enabled ? ' (ON)' : ' (OFF)').'.', $before, ['enabled' => $s->enabled, 'key_id' => $s->key_id, 'narration' => $s->narration, 'secrets_changed' => array_values(array_filter(['key_secret', 'webhook_secret', 'account_number'], fn ($f) => ($data[$f] ?? '') !== ''))], null, $request);

        return back()->with('success', 'Razorpay settings saved'.($s->enabled ? ' - payouts are ON.' : ' - payouts are OFF.'));
    }

    public function test(Request $request, PermissionService $permissions, RazorpayX $rzp): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        try {
            $r = $rzp->testConnection();
        } catch (RazorpayError $e) {
            return back()->withErrors(['razorpay' => 'Connection NOT working: '.$e->getMessage().($e->httpStatus === 401 ? ' (check Key Id / Secret)' : '').($e->httpStatus === 0 ? '' : ' - if it says the IP is not allowed, add this server\'s IP in RazorpayX → Settings → Developer Controls.')]);
        }

        return back()->with('success', 'Connection works ('.($r['live'] ? 'LIVE - real money' : 'TEST mode - no real money').').');
    }

    /* a payout without an answer: close as NOT paid - only after Razorpay confirms it has none */
    public function closeNotPaid(Request $request, BankPayout $payout, PermissionService $permissions, BankPayoutService $svc): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        app(ActionOtpGuard::class)->require($request->user(), 'payout.close:'.$payout->id, 'close payout '.$payout->public_id.' as NOT paid');
        $r = $svc->closeNotPaid($payout, $request->user());

        return match (true) {
            $r === 'closed' => back()->with('success', 'Closed as not paid - Razorpay has no such payout. The request can be paid again.'),
            str_starts_with($r, 'found:') => back()->with('success', 'Razorpay HAS this payout ('.substr($r, 6).') - it was not closed; the panel follows it now.'),
            $r === 'too-soon' => back()->withErrors(['razorpay' => 'Wait 15 minutes after the last try, then close it.']),
            str_starts_with($r, 'no-check:') => back()->withErrors(['razorpay' => 'Could not ask Razorpay, so it is NOT closed ('.substr($r, 9).'). Fix the connection first.']),
            $r === 'busy' => back()->withErrors(['razorpay' => 'The panel is working on this payout right now - try again in a minute.']),
            default => back()->withErrors(['razorpay' => 'This payout is not waiting for an answer.']),
        };
    }

    public function check(Request $request, BankPayout $payout, PermissionService $permissions, BankPayoutService $svc): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $p = $svc->sync($payout, true);

        return back()->with('success', 'Checked: '.$p->status.($p->utr ? ' · UTR '.$p->utr : '').'.');
    }
}
