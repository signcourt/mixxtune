<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorMoneyService;
use App\Services\V2\DistributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W226 - the distributor's "Sub-label payouts" page: what every sub-label has earned (its share), what the
 * distributor has paid it, and what is still due. A payment here is a register entry only - the distributor
 * pays outside Mixx Tune. An entry is never deleted: a wrong one is cancelled with a reason and stays visible.
 */
class DistributorPayoutController extends Controller
{
    private const METHODS = ['bank_transfer', 'upi', 'cash', 'cheque', 'other'];

    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off. Write to support.');
        abort_unless(DistributorMoneyService::ready(), 503, 'Payouts are not set up yet.');

        return $d;
    }

    public function index(Request $request, DistributorService $service, DistributorMoneyService $money): Response
    {
        $d = $this->owner($request, $service);

        return Inertia::render('V2/Distributor/Payouts', [
            'role' => 'label',
            'ledger' => $money->ledger($d),
            'methods' => self::METHODS,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, DistributorService $service, AuditLogService $audit): RedirectResponse
    {
        $d = $this->owner($request, $service);
        $data = $request->validate([
            'label_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_on' => ['required', 'date', 'after_or_equal:2020-01-01', 'before_or_equal:today'],
            'method' => ['required', Rule::in(self::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['label_id' => 'sub-label', 'paid_on' => 'payment date']);

        $child = DB::table('labels')->where('id', (int) $data['label_id'])->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->first(['id', 'name']);
        abort_unless($child, 404);

        /* the same entry pressed twice within a minute is one entry */
        $twin = DB::table('distributor_sub_label_payments')->where('distributor_id', $d->id)->where('label_id', $child->id)
            ->where('amount', round((float) $data['amount'], 2))->where('paid_on', $data['paid_on'])
            ->whereNull('cancelled_at')->where('created_at', '>=', now()->subMinute())->exists();
        if ($twin) {
            return back()->with('success', 'This payment is already in the register.');
        }

        $id = DB::table('distributor_sub_label_payments')->insertGetId([
            'distributor_id' => (int) $d->id,
            'label_id' => (int) $child->id,
            'amount' => round((float) $data['amount'], 2),
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $audit->record('distributor.sub_label_payment.recorded', 'distributors', 'Payment of '.number_format((float) $data['amount'], 2).' to sub-label "'.$child->name.'" written in the register', [], ['payment_id' => $id, 'label_id' => (int) $child->id, 'amount' => round((float) $data['amount'], 2), 'paid_on' => $data['paid_on'], 'method' => $data['method']], null, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Payment of ₹'.number_format((float) $data['amount'], 2).' to "'.$child->name.'" saved in the register.');
    }

    public function cancel(Request $request, int $payment, DistributorService $service, AuditLogService $audit): RedirectResponse
    {
        $d = $this->owner($request, $service);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        $row = DB::table('distributor_sub_label_payments')->where('id', $payment)->where('distributor_id', $d->id)->first();
        abort_unless($row, 404);
        if ($row->cancelled_at !== null) {
            return back()->with('error', 'This entry is already cancelled.');
        }

        DB::table('distributor_sub_label_payments')->where('id', $row->id)->whereNull('cancelled_at')->update([
            'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancel_reason' => trim($data['reason']), 'updated_at' => now(),
        ]);

        try {
            $audit->record('distributor.sub_label_payment.cancelled', 'distributors', 'Register entry of '.number_format((float) $row->amount, 2).' cancelled', [], ['payment_id' => (int) $row->id, 'label_id' => (int) $row->label_id, 'reason' => trim($data['reason'])], null, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Entry cancelled. It stays in the register, marked as cancelled.');
    }
}
