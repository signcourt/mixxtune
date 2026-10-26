<?php

namespace App\Services\V2\Payouts;

use App\Models\Payroll\SalaryItem;
use App\Models\Payroll\SalaryRun;
use App\Models\Payroll\StaffMember;
use App\Models\User;
use App\Services\V2\FinanceAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * W115 MIXXTUNE_STAFF_SALARY
 * A month's salary sheet: made from the active staff (their monthly salary), changed while "draft"
 * (extra / cut / note / leave out), then "Approve & pay" sends every line through RazorpayX (NEFT).
 */
class SalaryService
{
    public function createRun(string $month, User $admin): SalaryRun
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw ValidationException::withMessages(['month' => 'Pick a month.']);
        }
        if (SalaryRun::query()->where('month', $month)->exists()) {
            throw ValidationException::withMessages(['month' => 'The sheet for '.$month.' is already there.']);
        }
        $staff = StaffMember::query()->where('status', 'active')->orderBy('name')->get();
        if ($staff->isEmpty()) {
            throw ValidationException::withMessages(['month' => 'Add staff first (with their monthly salary).']);
        }

        return DB::transaction(function () use ($month, $admin, $staff) {
            $run = SalaryRun::query()->create(['month' => $month, 'status' => 'draft', 'created_by' => $admin->id]);
            foreach ($staff as $s) {
                $run->items()->create([
                    'staff_member_id' => $s->id,
                    'salary' => $s->monthly_salary,
                    'net' => $s->monthly_salary,
                    'status' => 'unpaid',
                    'snapshot' => ['name' => $s->name, 'designation' => $s->designation],
                ]);
            }
            FinanceAudit::log('payroll.sheet_created', 'Salary sheet '.$month.' made for '.$staff->count().' people.', ['salary_run_id' => $run->id]);

            return $run;
        });
    }

    public function updateItem(SalaryItem $item, array $data): SalaryItem
    {
        if ($item->run->status !== 'draft') {
            throw ValidationException::withMessages(['item' => 'The sheet is approved - it cannot be changed any more.']);
        }
        $salary = round((float) ($data['salary'] ?? $item->salary), 2);
        $add = round((float) ($data['additions'] ?? 0), 2);
        $cut = round((float) ($data['deductions'] ?? 0), 2);
        $net = round($salary + $add - $cut, 2);
        if ($net < 0) {
            throw ValidationException::withMessages(['deductions' => 'The cut is more than the salary.']);
        }
        $item->forceFill([
            'salary' => $salary, 'additions' => $add, 'deductions' => $cut, 'net' => $net,
            'note' => isset($data['note']) ? mb_substr(trim((string) $data['note']), 0, 255) ?: null : $item->note,
            'status' => !empty($data['skip']) ? 'skipped' : 'unpaid',
        ])->save();

        return $item;
    }

    /** approve the sheet and send every unpaid / failed line through Razorpay */
    public function approveAndPay(SalaryRun $run, User $admin): array
    {
        if (!in_array($run->status, ['draft', 'approved', 'paying'], true)) {
            throw ValidationException::withMessages(['run' => 'This sheet is already done.']);
        }
        if ($run->status === 'draft') {
            $run->forceFill(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()])->save();
            FinanceAudit::log('payroll.sheet_approved', 'Salary sheet '.$run->month.' approved (₹'.number_format((float) $run->items()->whereNot('status', 'skipped')->sum('net'), 2).').', ['salary_run_id' => $run->id]);
        }
        $sent = 0;
        $problems = [];
        $svc = app(BankPayoutService::class);
        foreach ($run->items()->whereIn('status', ['unpaid', 'failed'])->with('staff')->get() as $item) {
            if ((float) $item->net <= 0) {
                $item->forceFill(['status' => 'skipped'])->save();
                continue;
            }
            $p = $svc->paySalary($item, $admin);
            if ($p) {
                $sent++;
            } else {
                $problems[] = ($item->staff?->name ?? 'Staff').': '.($item->fresh()->snapshot['problem'] ?? 'not sent');
            }
        }
        $run->forceFill(['status' => 'paying'])->save();
        $this->closeRunIfDone($run->fresh());

        return ['sent' => $sent, 'problems' => $problems];
    }

    public function markManual(SalaryItem $item, string $utr, User $admin): void
    {
        DB::transaction(function () use ($item, $utr, $admin) {
            $it = SalaryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $running = $it->bank_payout_id && \App\Models\Finance\BankPayout::query()->whereKey($it->bank_payout_id)->whereNotIn('status', \App\Models\Finance\BankPayout::FAILED)->exists();
            if (!in_array($it->status, ['unpaid', 'failed'], true) || $it->run->status === 'draft' || $running) {
                throw ValidationException::withMessages(['utr' => 'Approve the sheet first; a line being paid (or paid) by Razorpay cannot be marked by hand.']);
            }
            $it->forceFill(['status' => 'paid', 'utr' => mb_substr(trim($utr), 0, 60), 'paid_how' => 'manual', 'paid_at' => now(), 'paid_by' => $admin->id])->save();
        });
        $item->refresh();
        FinanceAudit::log('payroll.salary_paid', 'Salary '.$item->run->month.' to '.($item->staff?->name).' marked paid by hand, UTR '.$utr.'.', ['salary_item_id' => $item->id]);
        $this->closeRunIfDone($item->run);
    }

    public function closeRunIfDone(?SalaryRun $run): void
    {
        if (!$run || $run->status === 'draft') {
            return;
        }
        $open = $run->items()->whereIn('status', ['unpaid', 'paying', 'failed'])->exists();
        $run->forceFill(['status' => $open ? 'paying' : 'done'])->save();
    }
}
