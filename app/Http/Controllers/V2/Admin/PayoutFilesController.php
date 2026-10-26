<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\AuditLogService;
use App\Services\V2\BankChangeGuard;
use App\Services\V2\PayoutDeductionService;
use App\Services\V2\PermissionService;
use App\Services\V2\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
 * MIXXTUNE_PAYOUT_FILES_V1
 * 1. Axis Bank bulk payout file for APPROVED withdrawals (net amount after TDS).
 *    Nothing is marked paid here: after the bank transfer, enter each UTR and press
 *    Mark Paid as before (e-mailed code).
 * 2. TDS report for a financial year, from the payout documents of paid withdrawals.
 * Super admin only. Every download is written to the audit log.
 */
class PayoutFilesController extends Controller
{
    /* Column order of the "Axis Upload" sheet. Change only here if Axis asks for another order. */
    public const AXIS_COLUMNS = [
        'Payment Mode',
        'Amount',
        'Value Date',
        'Debit Account No',
        'Beneficiary Name',
        'Beneficiary Account No',
        'Beneficiary IFSC',
        'Beneficiary Bank',
        'Customer Reference',
        'Remarks',
        'Beneficiary Email',
    ];

    public const RTGS_FROM = 200000;

    public function bankFile(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess,
        PayoutDeductionService $deductions,
        BankChangeGuard $bankGuard,
        SystemSettingService $settings,
        AuditLogService $audit
    ) {
        $this->authorizeSuperAdmin($request, $permissions);

        $format = $request->input('format') === 'csv' ? 'csv' : 'xlsx';
        $debitAccount = preg_replace('/\s+/', '', (string) $settings->get('payout.debit_account_number', ''));
        $valueDate = now()->timezone('Asia/Kolkata')->format('d-m-Y');

        $query = WithdrawalRequest::query()
            ->with(['user:id,name,email'])
            ->where('status', 'approved')
            ->orderBy('approved_at')
            ->orderBy('id');

        $financialAccess->applyFinancialOwnerScope($query, $request->user());

        $upload = [];
        $check = [];
        $total = 0.0;

        foreach ($query->get() as $withdrawal) {
            $profile = PayoutProfile::query()->where('user_id', $withdrawal->user_id)->first();
            $problem = null;

            if (!$profile || !filled($profile->bank_account_number) || !filled($profile->ifsc_code)) {
                $problem = 'Bank account / IFSC missing';
            } elseif ($profile->kyc_status !== 'verified') {
                $problem = 'KYC not verified ('.($profile->kyc_status ?: 'pending').')';
            } elseif (\App\Models\Finance\BankPayout::query()->where('withdrawal_id', $withdrawal->id)->whereNotIn('status', \App\Models\Finance\BankPayout::FAILED)->exists()) {
                $problem = 'Being paid / paid through Razorpay'; /* W115 */
            } elseif ($until = $bankGuard->holdUntil($profile)) {
                $problem = 'Bank details changed recently - on hold until '
                    .$until->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A');
            }

            $calc = $withdrawal->user
                ? $deductions->preview($withdrawal->user, (float) $withdrawal->amount, (int) $withdrawal->id, $profile)
                : null;

            if (!$problem && (!$calc || $calc['net'] <= 0)) {
                $problem = 'Net amount is zero';
            }

            /* W115: lock the request and check again just before it goes into the file, then remember it */
            $earlierFile = null;
            if (!$problem) {
                $earlierFile = $withdrawal->metadata['bank_file_at'] ?? null;
                $ok = \Illuminate\Support\Facades\DB::transaction(function () use ($withdrawal, $request) {
                    $row = WithdrawalRequest::query()->whereKey($withdrawal->id)->lockForUpdate()->first();
                    if (!$row || $row->status !== 'approved' || \App\Models\Finance\BankPayout::query()->where('withdrawal_id', $row->id)->whereNotIn('status', \App\Models\Finance\BankPayout::FAILED)->exists()) {
                        return false;
                    }
                    $row->forceFill(['metadata' => array_merge($row->metadata ?? [], ['bank_file_at' => now()->toIso8601String(), 'bank_file_by' => $request->user()->id])])->saveQuietly();

                    return true;
                });
                if (!$ok) {
                    $problem = 'Changed meanwhile (being paid through Razorpay)';
                }
            }

            $check[] = [
                $withdrawal->withdrawal_number,
                $withdrawal->user?->name,
                $profile?->account_holder_name,
                $calc['gross'] ?? (float) $withdrawal->amount,
                $calc['gst_amount'] ?? 0,
                $calc['tds_percent'] ?? 0,
                $calc['tds_amount'] ?? 0,
                $calc['net'] ?? 0,
                $calc ? PayoutDeductionService::reasonText($calc['tds_reason']) : '',
                $problem ? 'NOT IN FILE: '.$problem : ($earlierFile ? 'In upload file - ALSO in an earlier file ('.\Illuminate\Support\Carbon::parse($earlierFile)->timezone('Asia/Kolkata')->format('d M, h:i A').'): do not pay it twice' : 'In upload file'),
            ];

            if ($problem) {
                continue;
            }

            $total += $calc['net'];
            $upload[] = [
                $calc['net'] >= self::RTGS_FROM ? 'RTGS' : 'NEFT',
                number_format($calc['net'], 2, '.', ''),
                $valueDate,
                $debitAccount,
                $this->clean((string) $profile->account_holder_name, 50),
                preg_replace('/\s+/', '', (string) $profile->bank_account_number),
                strtoupper(trim((string) $profile->ifsc_code)),
                $this->clean((string) $profile->bank_name, 50),
                $withdrawal->withdrawal_number,
                $this->clean('Mixx Tune royalty '.$withdrawal->withdrawal_number, 30),
                (string) $withdrawal->user?->email,
            ];
        }

        $audit->record(
            'payout.bank_file_downloaded',
            'finance',
            'Axis payout file downloaded: '.count($upload).' payment(s), total '.number_format($total, 2).'.',
            [],
            ['payments' => count($upload), 'total' => round($total, 2), 'skipped' => count($check) - count($upload), 'format' => $format],
            null,
            $request
        );

        $stamp = now()->timezone('Asia/Kolkata')->format('Ymd-Hi');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($upload) {
                $out = fopen('php://output', 'w');
                fputcsv($out, self::AXIS_COLUMNS);
                foreach ($upload as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, "axis-payout-{$stamp}.csv", [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Axis Upload');
        $this->writeRows($sheet, self::AXIS_COLUMNS, $upload);

        $checkSheet = $book->createSheet();
        $checkSheet->setTitle('Check List');
        $this->writeRows($checkSheet, [
            'Withdrawal', 'Owner', 'Account Holder', 'Gross (wallet)', 'GST added', 'TDS %', 'TDS', 'Net to transfer', 'TDS note', 'Result',
        ], $check);

        $info = $book->createSheet();
        $info->setTitle('Read Me');
        $this->writeRows($info, ['Mixx Tune - Axis payout file'], [
            ['Made: '.now()->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST'],
            ['Payments in upload sheet: '.count($upload).'   Total: Rs '.number_format($total, 2)],
            [$debitAccount === '' ? 'WARNING: company debit account is empty - fill it in Master Settings > Payout / Bank file.' : 'Debit account: '.$debitAccount],
            ['Amount = net to transfer (wallet amount + GST if any - TDS).'],
            ['NEFT below Rs 2,00,000, RTGS from Rs 2,00,000.'],
            ['Compare the column order with the sample file of your Axis corporate banking before the first upload.'],
            ['After the bank transfer: Withdraw Requests > Approved > enter UTR > Mark Paid (e-mailed code).'],
        ]);

        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, "axis-payout-{$stamp}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function tdsReport(
        Request $request,
        PermissionService $permissions,
        AuditLogService $audit
    ) {
        $this->authorizeSuperAdmin($request, $permissions);

        $fyInput = (string) $request->input('fy', '');
        if (preg_match('/^(\d{4})-\d{2}$/', $fyInput, $m)) {
            $fy = PayoutDeductionService::financialYear(Carbon::create((int) $m[1], 4, 1));
        } else {
            $fy = PayoutDeductionService::financialYear();
        }

        $invoices = DB::table('invoices')
            ->leftJoin('withdrawals', 'withdrawals.id', '=', 'invoices.withdrawal_id')
            ->leftJoin('users', 'users.id', '=', 'invoices.user_id')
            ->leftJoin('payout_profiles', 'payout_profiles.user_id', '=', 'invoices.user_id')
            ->whereBetween('invoices.invoice_date', [$fy['start']->toDateString(), $fy['end']->toDateString()])
            ->whereNotNull('invoices.withdrawal_id')
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoices.id')
            ->select([
                'invoices.invoice_date', 'invoices.invoice_number', 'invoices.user_id', 'invoices.billing_name',
                'invoices.subtotal', 'invoices.gst_amount', 'invoices.tds_percentage', 'invoices.tds_amount',
                'invoices.net_payable', 'invoices.pan as invoice_pan',
                'withdrawals.withdrawal_number', 'withdrawals.payment_reference', 'withdrawals.paid_at',
                'users.name as user_name', 'users.email as user_email',
                'payout_profiles.pan_number as profile_pan',
            ])
            ->get();

        $rows = [];
        $perPayee = [];

        foreach ($invoices as $row) {
            $pan = strtoupper(trim((string) ($row->invoice_pan ?: $row->profile_pan)));
            $date = Carbon::parse($row->invoice_date);
            $rows[] = [
                $date->format('d-m-Y'),
                $date->format('M Y'),
                $row->invoice_number,
                $row->withdrawal_number,
                $row->user_name,
                $row->billing_name,
                $pan !== '' ? $pan : 'NO PAN',
                round((float) $row->subtotal, 2),
                round((float) $row->gst_amount, 2),
                (float) $row->tds_percentage,
                round((float) $row->tds_amount, 2),
                round((float) $row->net_payable, 2),
                $row->payment_reference,
            ];

            $key = (int) $row->user_id;
            $perPayee[$key] ??= [$row->user_name, $row->user_email, $pan !== '' ? $pan : 'NO PAN', 0, 0.0, 0.0, 0.0];
            $perPayee[$key][3]++;
            $perPayee[$key][4] += (float) $row->subtotal;
            $perPayee[$key][5] += (float) $row->tds_amount;
            $perPayee[$key][6] += (float) $row->net_payable;
        }

        $summary = array_map(fn ($p) => [$p[0], $p[1], $p[2], $p[3], round($p[4], 2), round($p[5], 2), round($p[6], 2)], array_values($perPayee));

        $audit->record(
            'payout.tds_report_downloaded',
            'finance',
            'TDS report downloaded for FY '.$fy['label'].'.',
            [],
            ['financial_year' => $fy['label'], 'payouts' => count($rows)],
            null,
            $request
        );

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Payouts');
        $this->writeRows($sheet, [
            'Paid Date', 'Month', 'Document No', 'Withdrawal', 'Owner', 'Billing Name', 'PAN',
            'Gross Amount', 'GST', 'TDS %', 'TDS Deducted', 'Net Paid', 'UTR',
        ], $rows);

        $sum = $book->createSheet();
        $sum->setTitle('Per Payee');
        $this->writeRows($sum, ['Owner', 'Email', 'PAN', 'Payouts', 'Gross Amount', 'TDS Deducted', 'Net Paid'], $summary);

        $info = $book->createSheet();
        $info->setTitle('Read Me');
        $this->writeRows($info, ['Mixx Tune - TDS report, financial year '.$fy['label']], [
            ['Period: '.$fy['start']->format('d M Y').' to '.$fy['end']->format('d M Y')],
            ['Rows: '.count($rows).'   Total TDS: Rs '.number_format(array_sum(array_column($rows, 10)), 2)],
            ['Made from the payout documents created at Mark Paid. Give this to your CA for TDS deposit and returns.'],
        ]);

        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'tds-report-FY'.$fy['label'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function writeRows($sheet, array $headers, array $rows): void
    {
        foreach ($headers as $c => $header) {
            $sheet->setCellValue([$c + 1, 1], $header);
        }
        $sheet->getStyle([1, 1, count($headers), 1])->getFont()->setBold(true);

        foreach ($rows as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                if (is_string($value)) {
                    // keep account numbers, IFSC, PAN and dates exactly as text
                    $sheet->setCellValueExplicit([$c + 1, $r + 2], $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue([$c + 1, $r + 2], $value);
                }
            }
        }

        foreach (range(1, max(1, count($headers))) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
    }

    private function clean(string $value, int $max): string
    {
        $value = preg_replace('/[^A-Za-z0-9 .\-\/]/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return mb_substr($value, 0, $max);
    }

    private function authorizeSuperAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Super Admin access required.');
    }
}
