<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\BankPayout;
use App\Models\Payroll\SalaryItem;
use App\Models\Payroll\SalaryRun;
use App\Models\Payroll\StaffMember;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\BankChangeGuard;
use App\Services\V2\FinanceAudit;
use App\Services\V2\Payouts\RazorpayX;
use App\Services\V2\Payouts\SalaryService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W115 MIXXTUNE_STAFF_SALARY
 * Super Admin > Financial > Staff & Salary: the staff list (designation, bank, monthly salary) and one
 * salary sheet per month - approve it and every salary goes to the bank through RazorpayX (NEFT).
 */
class PayrollController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can manage staff salaries.');
    }

    private static function ist($d, string $f = 'd M Y, h:i A'): ?string
    {
        return $d ? $d->copy()->timezone('Asia/Kolkata')->format($f) : null;
    }

    public function index(Request $request, PermissionService $permissions, RazorpayX $rzp): Response
    {
        $this->superAdmin($request, $permissions);
        $staff = StaffMember::query()->orderByRaw("status = 'active' desc")->orderBy('name')->get()->map(fn (StaffMember $s) => [
            'id' => $s->id, 'name' => $s->name, 'designation' => $s->designation, 'email' => $s->email, 'phone' => $s->phone,
            'account_holder_name' => $s->account_holder_name, 'masked_bank_account' => $s->masked_bank_account, 'ifsc_code' => $s->ifsc_code,
            'bank_name' => $s->bank_name, 'has_pan' => (string) $s->pan_number !== '', 'monthly_salary' => (float) $s->monthly_salary,
            'joined_on' => $s->joined_on?->format('Y-m-d'), 'status' => $s->status, 'notes' => $s->notes, 'bank_ready' => $s->bankReady(),
        ]);
        $runs = SalaryRun::query()->withCount('items')->orderByDesc('month')->limit(36)->get()->map(fn (SalaryRun $r) => [
            'id' => $r->id, 'month' => $r->month, 'status' => $r->status, 'people' => $r->items_count,
            'total' => round((float) $r->items()->whereNot('status', 'skipped')->sum('net'), 2),
            'paid' => round((float) $r->items()->where('status', 'paid')->sum('net'), 2),
        ]);

        return Inertia::render('V2/Admin/Payroll/Index', [
            'role' => 'super_admin',
            'staff' => $staff,
            'runs' => $runs,
            'monthlyTotal' => round((float) StaffMember::query()->where('status', 'active')->sum('monthly_salary'), 2),
            'razorpayReady' => $rzp->ready(),
            'nextMonth' => now('Asia/Kolkata')->format('Y-m'),
        ]);
    }

    private function staffRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'designation' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\- ]{6,20}$/'],
            'account_holder_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'regex:/^[0-9]{6,20}$/'],
            'ifsc_code' => ['nullable', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'joined_on' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function messages(): array
    {
        return ['bank_account_number.regex' => 'Account number: 6 to 20 digits.', 'ifsc_code.regex' => 'IFSC looks like ABCD0123456.', 'pan_number.regex' => 'PAN looks like ABCDE1234F.'];
    }

    public function storeStaff(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $d = $request->validate($this->staffRules(), $this->messages());
        $d['ifsc_code'] = isset($d['ifsc_code']) ? strtoupper($d['ifsc_code']) : null;
        $d['pan_number'] = isset($d['pan_number']) ? strtoupper($d['pan_number']) : null;
        $s = StaffMember::query()->create($d + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        FinanceAudit::log('payroll.staff_added', 'Staff '.$s->name.' added (salary ₹'.number_format((float) $s->monthly_salary, 2).').', ['staff_member_id' => $s->id]);

        return back()->with('success', $s->name.' added.');
    }

    public function updateStaff(Request $request, StaffMember $staff, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $rules = $this->staffRules();
        $d = $request->validate($rules, $this->messages());
        $d['ifsc_code'] = isset($d['ifsc_code']) ? strtoupper($d['ifsc_code']) : null;
        /* empty account / PAN box = keep the saved one */
        foreach (['bank_account_number', 'pan_number'] as $f) {
            if (($d[$f] ?? '') === '') {
                unset($d[$f]);
            } elseif ($f === 'pan_number') {
                $d[$f] = strtoupper($d[$f]);
            }
        }
        $bankBefore = [BankChangeGuard::normalize($staff->account_holder_name), BankChangeGuard::normalize($staff->bank_account_number), BankChangeGuard::normalize($staff->ifsc_code)];
        $bankAfter = [BankChangeGuard::normalize($d['account_holder_name'] ?? null), BankChangeGuard::normalize($d['bank_account_number'] ?? $staff->bank_account_number), BankChangeGuard::normalize($d['ifsc_code'] ?? null)];
        $hadBank = (string) $staff->bank_account_number !== '';
        $bankChanged = $hadBank && $bankBefore !== $bankAfter;
        if ($bankChanged) {
            app(ActionOtpGuard::class)->require($request->user(), 'staff.bank:'.$staff->id, 'change the bank account of '.$staff->name);
            if (SalaryItem::query()->where('staff_member_id', $staff->id)->where('status', 'paying')->exists()) {
                return back()->withErrors(['bank_account_number' => 'A salary to this person is being paid right now - change the bank after it is done.']);
            }
        }
        $staff->fill($d + ['updated_by' => $request->user()->id])->save();
        FinanceAudit::log($bankChanged ? 'payroll.staff_bank_changed' : 'payroll.staff_changed', 'Staff '.$staff->name.' changed'.($bankChanged ? ' (bank account changed)' : '').'.', ['staff_member_id' => $staff->id]);

        return back()->with('success', $staff->name.' saved.');
    }

    public function createRun(Request $request, PermissionService $permissions, SalaryService $svc): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $run = $svc->createRun((string) $request->input('month'), $request->user());

        return redirect('/admin/salaries/'.$run->id)->with('success', 'Salary sheet '.$run->month.' made - check it, then approve.');
    }

    public function showRun(Request $request, SalaryRun $run, PermissionService $permissions, RazorpayX $rzp): Response
    {
        $this->superAdmin($request, $permissions);
        $items = $run->items()->with(['staff', 'payout'])->get()->sortBy(fn ($i) => $i->staff?->name)->values()->map(fn (SalaryItem $i) => [
            'id' => $i->id,
            'name' => $i->staff?->name ?? ($i->snapshot['name'] ?? '—'),
            'designation' => $i->staff?->designation ?? ($i->snapshot['designation'] ?? null),
            'account' => $i->status === 'paid' || $i->status === 'paying' ? ($i->snapshot['account'] ?? null) : trim(($i->staff?->masked_bank_account ?? '').' · '.($i->staff?->ifsc_code ?? ''), ' ·'),
            'bank_ready' => (bool) $i->staff?->bankReady(),
            'salary' => (float) $i->salary, 'additions' => (float) $i->additions, 'deductions' => (float) $i->deductions, 'net' => (float) $i->net,
            'note' => $i->note, 'status' => $i->status, 'utr' => $i->utr, 'paid_how' => $i->paid_how, 'paid_at' => self::ist($i->paid_at),
            'problem' => $i->snapshot['problem'] ?? ($i->payout && in_array($i->payout->status, BankPayout::FAILED, true) ? $i->payout->failure_reason : null),
            'payout_status' => $i->payout?->status,
        ]);

        return Inertia::render('V2/Admin/Payroll/Run', [
            'role' => 'super_admin',
            'run' => [
                'id' => $run->id, 'month' => $run->month, 'status' => $run->status,
                'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $run->month)->format('F Y'),
                'approved_at' => self::ist($run->approved_at),
                'total' => round($items->where('status', '!=', 'skipped')->sum('net'), 2),
                'paid' => round($items->where('status', 'paid')->sum('net'), 2),
            ],
            'items' => $items,
            'razorpayReady' => $rzp->ready(),
            'live' => $rzp->live(),
        ]);
    }

    public function updateItem(Request $request, SalaryItem $item, PermissionService $permissions, SalaryService $svc): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $d = $request->validate([
            'salary' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'additions' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
            'skip' => ['nullable', 'boolean'],
        ]);
        $svc->updateItem($item, $d);

        return back()->with('success', 'Saved.');
    }

    public function pay(Request $request, SalaryRun $run, PermissionService $permissions, SalaryService $svc, RazorpayX $rzp): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        if (!$rzp->ready()) {
            return back()->withErrors(['razorpay' => 'Razorpay is not connected or switched off (Financial → Razorpay Payouts). You can still mark salaries paid by hand after approving.']);
        }
        $total = (float) $run->items()->whereIn('status', ['unpaid', 'failed'])->sum('net');
        app(ActionOtpGuard::class)->require($request->user(), 'salary.pay:'.$run->id, 'pay the salaries of '.$run->month.' (₹'.number_format($total, 2).') through Razorpay');
        $r = $svc->approveAndPay($run, $request->user());
        $msg = $r['sent'].' salary payment(s) sent to Razorpay (NEFT).';
        if ($r['problems'] !== []) {
            $msg .= ' Not sent: '.implode('; ', $r['problems']).'.';
        }

        return back()->with('success', $msg);
    }

    public function approve(Request $request, SalaryRun $run, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        if ($run->status === 'draft') {
            $run->forceFill(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
            FinanceAudit::log('payroll.sheet_approved', 'Salary sheet '.$run->month.' approved (to pay by hand).', ['salary_run_id' => $run->id]);
        }

        return back()->with('success', 'Sheet approved.');
    }

    public function markPaid(Request $request, SalaryItem $item, PermissionService $permissions, SalaryService $svc): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $d = $request->validate(['utr' => ['required', 'string', 'min:3', 'max:60']]);
        app(ActionOtpGuard::class)->require($request->user(), 'salary.paid:'.$item->id, 'mark the salary of '.($item->staff?->name).' as paid');
        $svc->markManual($item, $d['utr'], $request->user());

        return back()->with('success', 'Marked paid.');
    }

    public function deleteRun(Request $request, SalaryRun $run, PermissionService $permissions): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        if ($run->status !== 'draft') {
            return back()->withErrors(['run' => 'Only a sheet that is not approved yet can be deleted.']);
        }
        $m = $run->month;
        $run->delete();
        FinanceAudit::log('payroll.sheet_deleted', 'Salary sheet '.$m.' (draft) deleted.', []);

        return redirect('/admin/salaries')->with('success', 'Sheet '.$m.' deleted.');
    }
}
