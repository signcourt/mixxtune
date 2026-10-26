<?php

namespace App\Services\V2\Payouts;

use App\Models\Finance\BankPayout;
use App\Models\Finance\PaymentGatewaySetting;
use App\Models\Finance\PayoutBeneficiary;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Models\Payroll\SalaryItem;
use App\Models\User;
use App\Services\V2\BankChangeGuard;
use App\Services\V2\FinanceAudit;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PayoutDeductionService;
use App\Services\V2\StaffAlerts;
use App\Services\V2\WithdrawalService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/*
 * W115 MIXXTUNE_RAZORPAY_PAYOUTS
 * Sends a withdrawal or a salary to the person's bank through RazorpayX (NEFT) and follows it to the end:
 *  - a bank_payouts row with its own idempotency key is saved BEFORE Razorpay is called; every later try
 *    uses the SAME key, so Razorpay can never pay it twice; one payout is handled by one process at a time (lock);
 *  - the bank account is fixed when the row is made (details_hash) - a later bank change is never paid by a retry;
 *  - status comes back by webhook (instant) or by `payouts:sync` (every 5 minutes);
 *  - processed  -> withdrawal marked paid with the UTR (wallet + payout document as with "Mark Paid") / salary paid;
 *  - failed / reversed / rejected / cancelled -> withdrawal back to "approved" (pay again or reject) / salary "failed".
 * A payout without any answer is NEVER closed as "not paid" by the panel - it is asked again with the same key.
 */
class BankPayoutService
{
    public const MODE = 'NEFT';

    /* tries without an answer before the panel slows down to once an hour */
    public const FAST_TRIES = 6;

    public function __construct(private readonly RazorpayX $rzp)
    {
    }

    /* ---------------- withdrawals ---------------- */

    /** Approve (if still pending) and send the net amount through Razorpay. */
    public function payWithdrawal(WithdrawalRequest $w, User $admin, ?string $note = null, bool $notInBankFile = false): BankPayout
    {
        $this->assertReady();
        $profile = PayoutProfile::query()->where('user_id', $w->user_id)->first();
        app(BankChangeGuard::class)->assertPayoutAllowed((int) $w->user_id);
        if (!$profile || !$profile->bank_account_number || !$profile->ifsc_code) {
            throw ValidationException::withMessages(['razorpay' => 'This owner has no bank account / IFSC.']);
        }
        $bank = $this->bankOfUser($profile);
        if (!RazorpayX::nameOk($bank['name'])) {
            throw ValidationException::withMessages(['razorpay' => 'The account holder name must have at least 3 English letters for Razorpay - correct it in the KYC profile.']);
        }
        if ($w->status === 'pending') {
            app(WithdrawalService::class)->approve($w, $admin, $note);
            $w->refresh();
            FinanceAudit::log('finance.withdrawal_approved', 'Withdrawal '.($w->withdrawal_number ?: '#'.$w->id).' approved (Approve & Pay via Razorpay).', ['id' => $w->id], $w);
        }

        $payout = DB::transaction(function () use ($w, $admin, $profile, $bank, $notInBankFile) {
            $locked = WithdrawalRequest::query()->whereKey($w->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['razorpay' => 'Only an approved withdrawal can be sent (it is '.$locked->status.').']);
            }
            if (!empty($locked->metadata['bank_file_at']) && !$notInBankFile) {
                throw ValidationException::withMessages(['razorpay' => 'This request is in an Axis bank file - pay it there and Mark Paid, or confirm it was not paid there.']);
            }
            if (BankPayout::query()->where('withdrawal_id', $locked->id)->whereNotIn('status', BankPayout::FAILED)->exists()) {
                throw ValidationException::withMessages(['razorpay' => 'A Razorpay payment for this withdrawal is already running or done.']);
            }
            $calc = app(PayoutDeductionService::class)->preview($locked->user, (float) $locked->amount, (int) $locked->id, $profile);
            $paise = (int) round(((float) $calc['net']) * 100);
            if ($paise < 100) {
                throw ValidationException::withMessages(['razorpay' => 'The amount to send is less than ₹1.']);
            }
            $p = BankPayout::query()->create([
                'public_id' => (string) Str::ulid(),
                'purpose' => 'withdrawal',
                'withdrawal_id' => $locked->id,
                'payee_name' => mb_substr($bank['name'], 0, 120),
                'account_hint' => mb_substr(($profile->masked_bank_account ?? '').' · '.$profile->ifsc_code, 0, 60),
                'amount_paise' => $paise,
                'mode' => self::MODE,
                'live' => $this->rzp->live(),
                'status' => 'creating',
                'idempotency_key' => (string) Str::uuid(),
                'details_hash' => self::hashOf($bank),
                'deduction' => array_intersect_key($calc, array_flip(['gross', 'gst_percent', 'gst_amount', 'total', 'tds_percent', 'tds_amount', 'net', 'tds_reason'])),
                'created_by' => $admin->id,
            ]);
            $locked->forceFill(['status' => 'processing', 'updated_by' => $admin->id, 'metadata' => array_merge($locked->metadata ?? [], ['bank_payout_id' => $p->id])])->save();

            return $p;
        });

        FinanceAudit::log('payout.razorpay_started', 'Withdrawal '.($w->withdrawal_number ?: '#'.$w->id).': ₹'.number_format($payout->rupees(), 2).' sent to Razorpay ('.self::MODE.').', ['bank_payout_id' => $payout->id, 'amount_paise' => $payout->amount_paise, 'live' => $payout->live], $w);
        $this->locked($payout, fn (BankPayout $p) => $this->send($p)); /* if busy, payouts:sync sends it */

        return $payout->fresh();
    }

    /* ---------------- salaries ---------------- */

    public function paySalary(SalaryItem $item, User $admin): ?BankPayout
    {
        $this->assertReady();
        $payout = DB::transaction(function () use ($item, $admin) {
            $it = SalaryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (!in_array($it->status, ['unpaid', 'failed'], true)) {
                return null;
            }
            if ($it->bank_payout_id && BankPayout::query()->whereKey($it->bank_payout_id)->whereNotIn('status', BankPayout::FAILED)->exists()) {
                return null;
            }
            $staff = $it->staff;
            $paise = (int) round(((float) $it->net) * 100);
            $problem = !$staff || !$staff->bankReady() ? 'No bank account / IFSC' : ($paise < 100 ? 'Amount is less than ₹1' : (!RazorpayX::nameOk((string) ($staff->account_holder_name ?: $staff->name)) ? 'Account holder name needs at least 3 English letters' : null));
            if ($problem) {
                $it->forceFill(['status' => 'failed', 'snapshot' => array_merge($it->snapshot ?? [], ['problem' => $problem])])->save();

                return null;
            }
            $bank = $this->bankOfStaff($staff);
            $p = BankPayout::query()->create([
                'public_id' => (string) Str::ulid(),
                'purpose' => 'salary',
                'salary_item_id' => $it->id,
                'payee_name' => mb_substr($bank['name'], 0, 120),
                'account_hint' => mb_substr($staff->masked_bank_account.' · '.$staff->ifsc_code, 0, 60),
                'amount_paise' => $paise,
                'mode' => self::MODE,
                'live' => $this->rzp->live(),
                'status' => 'creating',
                'idempotency_key' => (string) Str::uuid(),
                'details_hash' => self::hashOf($bank),
                'created_by' => $admin->id,
            ]);
            $snap = $it->snapshot ?? [];
            unset($snap['problem']);
            $it->forceFill(['status' => 'paying', 'bank_payout_id' => $p->id, 'snapshot' => array_merge($snap, ['holder' => $p->payee_name, 'account' => $p->account_hint])])->save();

            return $p;
        });
        if (!$payout) {
            return null;
        }
        $this->locked($payout, fn (BankPayout $p) => $this->send($p));

        return $payout->fresh();
    }

    /* ---------------- sending + following ---------------- */

    /** run $fn on a fresh copy of the payout while no other process works on it */
    private function locked(BankPayout $p, callable $fn): ?BankPayout
    {
        $lock = Cache::lock('bank_payout:'.$p->id, 180);
        if (!$lock->get()) {
            return null; /* another process is on it */
        }
        try {
            $fresh = BankPayout::query()->findOrFail($p->id);
            $fn($fresh);
        } finally {
            $lock->release();
        }

        return $p->fresh();
    }

    private function send(BankPayout $p): void
    {
        if (!in_array($p->status, ['creating', 'error'], true) || $p->razorpay_payout_id) {
            return;
        }
        if ($p->live !== $this->rzp->live()) {
            $this->problem($p, 'The Razorpay keys were changed (test ↔ live) after this payout was made - put the old keys back, or check it in the RazorpayX dashboard.');

            return;
        }
        /* "never asked": no POST /payouts has gone out with this key yet - then a clear refusal is final */
        $neverAsked = $p->payout_requested_at === null;
        $p->forceFill(['attempts' => $p->attempts + 1, 'last_checked_at' => now()])->save();
        try {
            if (!$p->fund_account_id) {
                $bank = $this->currentBank($p);
                if (!$bank || self::hashOf($bank) !== $p->details_hash) {
                    /* no fund account yet = nothing can have been paid; never pay the new account by a retry */
                    throw new RazorpayError('The bank details changed before the payout could be sent - not sent. Pay again (it uses the new details).', 400, true);
                }
                $p->forceFill(['fund_account_id' => $this->fundAccount($bank)])->save();
            }
            if (!$neverAsked) {
                /* asked before without a clear answer: first look whether Razorpay has it */
                $found = $this->rzp->findPanelPayout((string) $p->fund_account_id, (string) $p->public_id, $p->payout_requested_at ? $p->payout_requested_at->copy()->subDay()->timestamp : null);
                if ($found) {
                    $this->applyLocked($p, $found);

                    return;
                }
            }
            /* every POST /payouts is noted: a close as "not paid" waits 15 minutes after the LAST one */
            $p->forceFill(['payout_requested_at' => $p->payout_requested_at ?? now(), 'last_requested_at' => now()])->save();
            $ref = $p->purpose === 'withdrawal'
                ? (string) ($p->withdrawal?->withdrawal_number ?: 'WD'.$p->withdrawal_id)
                : 'SAL '.($p->salaryItem?->run?->month ?? '').' '.$p->salary_item_id;
            $narr = (string) (PaymentGatewaySetting::razorpay()->narration ?: 'Mixx Tune');
            $res = $this->rzp->createPayout(
                (string) $p->fund_account_id,
                (int) $p->amount_paise,
                self::MODE,
                $p->purpose === 'salary' ? 'salary' : 'payout',
                $ref,
                $narr.' '.($p->purpose === 'salary' ? 'salary' : 'royalty'),
                (string) $p->idempotency_key,
                ['panel_payout' => $p->public_id, 'purpose' => $p->purpose, 'ref' => $ref]
            );
            $this->applyLocked($p, $res);
        } catch (RazorpayError $e) {
            if ($e->definite && $neverAsked) {
                /* Razorpay refused before any payout was asked with this key - nothing was created; the request can be sent again */
                $p->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 500)])->save();
                $this->settle($p->fresh());
            } else {
                /* no clear answer (or a refusal of a RETRY, which may mean "already being made") - keep it open;
                   payouts:sync asks again with the SAME key (never pays twice) */
                $p->forceFill(['status' => 'error', 'failure_reason' => mb_substr($e->getMessage(), 0, 500)])->save();
            }
        }
    }

    /** contact + fund account at Razorpay, made once per person and bank account */
    private function fundAccount(array $b): string
    {
        $live = $this->rzp->live();
        $hash = self::hashOf($b);
        $known = PayoutBeneficiary::query()->where(['owner_type' => $b['owner_type'], 'owner_id' => $b['owner_id'], 'live' => $live, 'details_hash' => $hash])->first();
        if ($known) {
            return $known->fund_account_id;
        }
        $contact = $this->rzp->createContact($b['name'], $b['type'], 'mt-'.$b['owner_type'].'-'.$b['owner_id'], $b['email'] ?? null, $b['phone'] ?? null);
        $fa = $this->rzp->createBankFundAccount((string) $contact['id'], $b['name'], $b['ifsc'], $b['account']);
        PayoutBeneficiary::query()->firstOrCreate(
            ['owner_type' => $b['owner_type'], 'owner_id' => $b['owner_id'], 'live' => $live, 'details_hash' => $hash],
            ['contact_id' => (string) $contact['id'], 'fund_account_id' => (string) $fa['id']]
        );

        return (string) $fa['id'];
    }

    public static function hashOf(array $b): string
    {
        return hash('sha256', implode('|', [BankChangeGuard::normalize($b['name']), BankChangeGuard::normalize($b['ifsc']), BankChangeGuard::normalize($b['account'])]));
    }

    private function currentBank(BankPayout $p): ?array
    {
        if ($p->purpose === 'withdrawal') {
            $profile = PayoutProfile::query()->where('user_id', $p->withdrawal?->user_id)->first();

            return $profile ? $this->bankOfUser($profile) : null;
        }
        $s = $p->salaryItem?->staff;

        return $s ? $this->bankOfStaff($s) : null;
    }

    private function bankOfUser(PayoutProfile $profile): array
    {
        $user = User::query()->find($profile->user_id);

        return [
            'type' => 'vendor', 'owner_type' => 'user', 'owner_id' => (int) $profile->user_id,
            'name' => (string) ($profile->account_holder_name ?: $user?->name ?: 'Owner'),
            'ifsc' => (string) $profile->ifsc_code, 'account' => (string) $profile->bank_account_number,
            'email' => $user?->email, 'phone' => null,
        ];
    }

    private function bankOfStaff($s): array
    {
        return [
            'type' => 'employee', 'owner_type' => 'staff', 'owner_id' => (int) $s->id, 'name' => (string) ($s->account_holder_name ?: $s->name),
            'ifsc' => (string) $s->ifsc_code, 'account' => (string) $s->bank_account_number, 'email' => $s->email, 'phone' => $s->phone,
        ];
    }

    /** a payout object from Razorpay (webhook); null = busy (another process works on it - send it again later) */
    public function apply(BankPayout $p, array $po): ?BankPayout
    {
        return $this->locked($p, fn (BankPayout $fresh) => $this->applyLocked($fresh, $po));
    }

    private function applyLocked(BankPayout $p, array $po): void
    {
        $status = (string) ($po['status'] ?? '');
        $known = array_merge(BankPayout::OPEN, ['processed'], BankPayout::FAILED);
        if ($status === '' || !in_array($status, $known, true)) {
            return;
        }
        if (!$p->isOpen() && !($p->status === 'processed' && $status === 'reversed')) {
            if ($p->status === 'processed' && !$p->settled) {
                $this->settle($p); /* paid earlier but not booked yet - try booking again */
            } elseif (in_array($p->status, BankPayout::FAILED, true) && in_array($status, ['processing', 'processed'], true)) {
                /* closed here as not paid, but Razorpay says it is paid / being paid */
                $p->forceFill(['razorpay_payout_id' => $p->razorpay_payout_id ?: ($po['id'] ?? null), 'utr' => ($po['utr'] ?? null) ?: $p->utr])->save();
                $this->alertOnce($p, 'late:'.$status, 'Razorpay paid a closed payout: '.$p->payee_name, 'Razorpay says payout '.($po['id'] ?? '').' of ₹'.number_format($p->rupees(), 2).' to '.$p->payee_name.' is '.$status.', but the panel had it as "'.$p->status.'". Check at once whether this person is being paid twice.', 'danger');
                FinanceAudit::log('payout.razorpay_late_success', 'Razorpay payout '.($po['id'] ?? '').' '.$status.' after it was closed as '.$p->status.'.', ['bank_payout_id' => $p->id]);
            }

            return;
        }
        $reason = trim(implode(' · ', array_filter([data_get($po, 'status_details.description'), data_get($po, 'status_details.reason'), data_get($po, 'failure_reason')])));
        $p->forceFill([
            'status' => $status,
            'razorpay_payout_id' => $p->razorpay_payout_id ?: ($po['id'] ?? null),
            'utr' => ($po['utr'] ?? null) ?: $p->utr,
            'failure_reason' => in_array($status, BankPayout::FAILED, true) ? mb_substr($reason ?: 'Payout '.$status.' at Razorpay.', 0, 500) : null,
            'last_checked_at' => now(),
            'last_response' => array_merge(
                array_intersect_key($p->last_response ?? [], ['_alerts' => 1]),
                array_intersect_key($po, array_flip(['id', 'status', 'utr', 'mode', 'amount', 'fees', 'tax', 'status_details', 'created_at', 'reference_id']))
            ),
        ])->save();
        $this->settle($p->fresh());
    }

    /** ask Razorpay again (or send again with the same key when there was no answer) */
    public function sync(BankPayout $p, bool $now = false): BankPayout
    {
        return $this->locked($p, function (BankPayout $p) use ($now) {
            try {
                if ($p->razorpay_payout_id) {
                    if (!$p->isOpen() && !($p->status === 'processed' && !$p->settled)) {
                        return;
                    }
                    if ($p->live !== $this->rzp->live()) {
                        $this->problem($p, 'The Razorpay keys were changed (test ↔ live) after this payout was made - check it in the RazorpayX dashboard.');

                        return;
                    }
                    $p->forceFill(['last_checked_at' => now()])->save();
                    $this->applyLocked($p, $this->rzp->fetchPayout($p->razorpay_payout_id));
                } elseif (in_array($p->status, ['creating', 'error'], true)) {
                    /* never two tries close together; after FAST_TRIES only once an hour */
                    $wait = $p->attempts >= self::FAST_TRIES ? 60 : 3;
                    if (!$now && $p->updated_at && $p->updated_at->gt(now()->subMinutes($wait))) {
                        return;
                    }
                    $this->send($p);
                    $p->refresh();
                    if ($p->status === 'error' && $p->attempts >= self::FAST_TRIES) {
                        $this->alertOnce($p, 'noanswer', 'No answer from Razorpay: '.$p->payee_name, 'The payout '.$p->public_id.' of ₹'.number_format($p->rupees(), 2).' got no answer after '.$p->attempts.' tries ('.$p->failure_reason.'). The panel keeps asking once an hour with the same safety key (it can never pay twice). Check the RazorpayX dashboard and the server\'s internet / IP allow-list.', 'warning');
                    }
                }
            } catch (RazorpayError $e) {
                $p->forceFill(['last_checked_at' => now()])->save();
            } catch (Throwable $e) {
                report($e);
            }
        }) ?? $p->fresh();
    }

    /**
     * Super Admin: close a payout that got no answer as NOT paid - only when the panel is sure:
     * no payout was ever asked with its key, or Razorpay (asked now) has no payout with our reference.
     */
    public function closeNotPaid(BankPayout $p, User $admin): string
    {
        $result = 'busy';
        $this->locked($p, function (BankPayout $p) use ($admin, &$result) {
            if (!in_array($p->status, ['creating', 'error'], true) || $p->razorpay_payout_id) {
                $result = 'not-open';

                return;
            }
            if ($p->payout_requested_at) {
                if (($p->last_requested_at ?? $p->payout_requested_at)->gt(now()->subMinutes(15))) {
                    $result = 'too-soon';

                    return;
                }
                try {
                    $found = $p->fund_account_id ? $this->rzp->findPanelPayout((string) $p->fund_account_id, (string) $p->public_id, $p->payout_requested_at ? $p->payout_requested_at->copy()->subDay()->timestamp : null) : null;
                } catch (RazorpayError $e) {
                    $result = 'no-check:'.$e->getMessage();

                    return;
                }
                if ($found) {
                    $this->applyLocked($p, $found);
                    $result = 'found:'.($found['status'] ?? '');

                    return;
                }
            }
            $p->forceFill(['status' => 'failed', 'failure_reason' => 'Closed as not paid by '.$admin->name.' - Razorpay has no payout for it ('.($p->payout_requested_at ? 'checked' : 'never asked').').'])->save();
            $this->settle($p->fresh());
            FinanceAudit::log('payout.razorpay_closed', 'Payout '.$p->public_id.' closed as not paid by '.$admin->name.'.', ['bank_payout_id' => $p->id]);
            $result = 'closed';
        });

        return $result;
    }

    /** our side: withdrawal paid / back to approved, salary paid / failed */
    public function settle(BankPayout $p): void
    {
        if ($p->status === 'processed' && !$p->settled) {
            $this->settleDone($p);
        } elseif (in_array($p->status, BankPayout::FAILED, true)) {
            if ($p->settled && $p->status === 'reversed' && !$p->reversed_after_paid && $this->wasBookedPaid($p)) {
                /* paid earlier, now the bank sent it back: the money is in the RazorpayX balance again */
                $p->forceFill(['reversed_after_paid' => true])->save();
                $this->alertOnce($p, 'reversed', 'Payout came back: '.$p->payee_name, 'RazorpayX says the payout '.$p->razorpay_payout_id.' of ₹'.number_format($p->rupees(), 2).' to '.$p->payee_name.' was reversed after it was paid. The money is back in the RazorpayX balance. In the panel it still shows as paid - contact the person and pay again by hand.', 'danger');
                FinanceAudit::log('payout.razorpay_reversed_after_paid', 'Razorpay payout '.$p->razorpay_payout_id.' reversed after it was paid.', ['bank_payout_id' => $p->id]);
            } elseif (!$p->settled) {
                $this->settleFailed($p);
            }
        }
    }

    private function wasBookedPaid(BankPayout $p): bool
    {
        return $p->purpose === 'withdrawal' ? $p->withdrawal?->status === 'paid' : $p->salaryItem?->status === 'paid';
    }

    private function settleDone(BankPayout $p): void
    {
        $admin = User::query()->find($p->created_by) ?? app(StaffAlerts::class)->superAdmins()->first();
        $ref = mb_substr((string) ($p->utr ?: $p->razorpay_payout_id), 0, 100);
        if ($p->purpose === 'withdrawal') {
            $w = $p->withdrawal;
            if (!$w || !$admin) {
                return;
            }
            if ($w->status === 'paid') {
                /* already booked */
                $p->forceFill(['settled' => true, 'settled_at' => now(), 'failure_reason' => null])->save();
                if ($w->payment_reference && $w->payment_reference !== $ref) {
                    $this->alertOnce($p, 'twice', 'Check: '.$w->withdrawal_number.' paid twice?', 'Razorpay paid '.$w->withdrawal_number.' (UTR '.$ref.') but the request was already marked paid with '.$w->payment_reference.'. Check whether the owner got the money twice.', 'danger');
                }

                return;
            }
            try {
                app(WithdrawalService::class)->markPaid($w, $admin, $ref, 'Paid through RazorpayX '.$p->razorpay_payout_id.' ('.$p->mode.')', true);
            } catch (Throwable $e) {
                /* the bank has paid, but the panel could not book it (e.g. company details for the payout document) */
                $p->forceFill(['failure_reason' => mb_substr('Paid at the bank, but not booked in the panel yet: '.$e->getMessage(), 0, 500)])->save();
                $this->alertOnce($p, 'notbooked', 'Payout paid but not booked', 'Razorpay paid '.$w->withdrawal_number.' (UTR '.$ref.') but the panel could not mark it paid: '.$e->getMessage().' Fix it, then press "Check now" in Razorpay Payouts.', 'warning');

                return;
            }
            $p->forceFill(['settled' => true, 'settled_at' => now(), 'failure_reason' => null])->save();
            FinanceAudit::log('finance.withdrawal_paid', 'Withdrawal '.$w->withdrawal_number.' paid through RazorpayX, UTR '.$ref.'.', ['bank_payout_id' => $p->id, 'utr' => $ref], $w);
            $this->checkDocument($p, $w->fresh());
            $owner = User::query()->find($w->user_id);
            if ($owner) {
                try {
                    app(PanelNotificationService::class)->send($owner, 'withdrawal.paid', 'Payout sent', 'Your withdrawal '.$w->withdrawal_number.' of ₹'.number_format((float) $w->amount, 2).' has been sent to your bank account. Bank reference (UTR): '.$ref.'.', '/withdrawals');
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return;
        }
        $done = DB::transaction(function () use ($p, $ref) {
            $it = SalaryItem::query()->whereKey($p->salary_item_id)->lockForUpdate()->first();
            if (!$it) {
                return null;
            }
            if ($it->status !== 'paid') {
                $it->forceFill(['status' => 'paid', 'utr' => mb_substr($ref, 0, 60), 'paid_how' => 'razorpay', 'paid_at' => now(), 'paid_by' => $p->created_by])->save();
            }

            return $it;
        });
        $p->forceFill(['settled' => true, 'settled_at' => now()])->save();
        if ($done) {
            app(SalaryService::class)->closeRunIfDone($done->run);
        }
        FinanceAudit::log('payroll.salary_paid', 'Salary '.($done?->run?->month).' to '.$p->payee_name.' paid through RazorpayX, UTR '.$ref.'.', ['bank_payout_id' => $p->id, 'salary_item_id' => $p->salary_item_id]);
    }

    /** the payout document should show what the bank really sent */
    private function checkDocument(BankPayout $p, WithdrawalRequest $w): void
    {
        $net = DB::table('invoices')->where('withdrawal_id', $w->id)->value('net_payable');
        if ($net !== null && abs(round((float) $net * 100) - $p->amount_paise) > 1) {
            $this->alertOnce($p, 'docnet', 'Payout document differs: '.$w->withdrawal_number, 'Razorpay sent ₹'.number_format($p->rupees(), 2).' but the payout document of '.$w->withdrawal_number.' shows net ₹'.number_format((float) $net, 2).' (TDS / settings changed while the bank was paying). Check the document.', 'warning');
        }
    }

    private function settleFailed(BankPayout $p): void
    {
        if ($p->purpose === 'withdrawal' && $p->withdrawal) {
            DB::transaction(function () use ($p) {
                $w = WithdrawalRequest::query()->whereKey($p->withdrawal_id)->lockForUpdate()->first();
                if ($w && $w->status === 'processing') {
                    $w->forceFill(['status' => 'approved', 'metadata' => array_merge($w->metadata ?? [], ['razorpay_failed' => $p->failure_reason])])->save();
                }
            });
            $this->alertOnce($p, 'failed', 'Razorpay payout failed: '.$p->withdrawal->withdrawal_number, 'The ₹'.number_format($p->rupees(), 2).' payout to '.$p->payee_name.' did not go: '.$p->failure_reason.' The withdrawal is back in "Approved" - pay again or reject it.', 'warning');
        } elseif ($p->salary_item_id) {
            DB::transaction(function () use ($p) {
                $it = SalaryItem::query()->whereKey($p->salary_item_id)->lockForUpdate()->first();
                if ($it && $it->status === 'paying' && (int) $it->bank_payout_id === (int) $p->id) {
                    $it->forceFill(['status' => 'failed'])->save();
                }
            });
            $this->alertOnce($p, 'failed', 'Salary payout failed: '.$p->payee_name, 'The salary of ₹'.number_format($p->rupees(), 2).' to '.$p->payee_name.' did not go: '.$p->failure_reason.' Pay again from the salary sheet.', 'warning');
        }
        $p->forceFill(['settled' => true, 'settled_at' => now()])->save();
        FinanceAudit::log('payout.razorpay_failed', 'Razorpay payout to '.$p->payee_name.' '.$p->status.': '.$p->failure_reason, ['bank_payout_id' => $p->id]);
    }

    private function problem(BankPayout $p, string $text): void
    {
        $p->forceFill(['failure_reason' => mb_substr($text, 0, 500), 'last_checked_at' => now()])->save();
        $this->alertOnce($p, 'problem', 'Razorpay payout needs a look: '.$p->payee_name, $text.' (payout '.$p->public_id.', ₹'.number_format($p->rupees(), 2).')', 'warning');
    }

    /** one bell per payout and kind, not every 5 minutes */
    private function alertOnce(BankPayout $p, string $kind, string $title, string $message, string $severity): void
    {
        $sent = (array) (($p->last_response ?? [])['_alerts'] ?? []);
        if (in_array($kind, $sent, true)) {
            return;
        }
        $p->forceFill(['last_response' => array_merge($p->last_response ?? [], ['_alerts' => array_values(array_merge($sent, [$kind]))])])->save();
        try {
            app(StaffAlerts::class)->bell(app(StaffAlerts::class)->superAdmins(), 'payout.razorpay', $title, $message, '/admin/razorpay', $severity, ['bank_payout_id' => $p->id]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function assertReady(): void
    {
        if (!$this->rzp->ready()) {
            throw ValidationException::withMessages(['razorpay' => 'Razorpay is not connected or switched off. Super Admin → Financial → Razorpay Payouts.']);
        }
        if (!$this->rzp->live() && !RazorpayX::testKeysAllowed()) {
            throw ValidationException::withMessages(['razorpay' => 'Razorpay has TEST keys on the live site: a test payout sends no money but would mark the request paid. Put the LIVE keys in Razorpay Payouts (test on the staging site).']);
        }
    }
}
