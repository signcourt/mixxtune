<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\AdminFinancialAccessService;
use App\Services\V2\PermissionService;
use App\Services\V2\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
    ): Response {
        $role = $permissions->role($request->user());

        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Admin access required.');

        /* MIXXTUNE_WITHDRAWALS_V2: tabs incl. All, search, totals, readiness, pages */
        $status = trim((string) $request->input('status', 'pending'));
        if (!in_array($status, ['pending', 'approved', 'paid', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $month = trim((string) $request->input('month', ''));
        if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = '';
        }

        $search = trim((string) $request->input('search', ''));

        $dateColumns = [
            'pending' => 'requested_at',
            'approved' => 'approved_at',
            'paid' => 'paid_at',
            'rejected' => 'rejected_at',
            'all' => 'requested_at',
        ];

        $filtered = function (string $forStatus) use ($request, $financialAccess, $month, $search, $dateColumns) {
            $builder = WithdrawalRequest::query();
            $financialAccess->applyFinancialOwnerScope($builder, $request->user());

            if ($forStatus !== 'all') {
                $builder->where('status', $forStatus);
            }

            if ($month !== '') {
                $builder->where($dateColumns[$forStatus], 'like', $month.'%');
            }

            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $builder->where(fn ($w) => $w->where('withdrawal_number', 'like', $like)
                    ->orWhere('payment_reference', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like)));
            }

            return $builder;
        };

        $counts = [];
        $totals = [];
        foreach (['pending', 'approved', 'paid', 'rejected', 'all'] as $s) {
            $counts[$s] = $filtered($s)->count();
            $totals[$s] = round((float) $filtered($s)->sum('amount'), 2);
        }

        $page = $this->withDeductions(
            $filtered($status)
                ->with([
                    'user:id,name,email,client_id',
                    'payoutProfile',
                    'invoice:id,withdrawal_id,invoice_number,gst_amount,tds_percentage,tds_amount,net_payable',
                ])
                ->orderByDesc($dateColumns[$status])
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString()
        );

        $guard = app(\App\Services\V2\BankChangeGuard::class);
        $service = app(WithdrawalService::class);
        $userIds = $page->getCollection()->pluck('user_id')->unique();
        $available = DB::table('wallets')->whereIn('user_id', $userIds)->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(available_balance) as total'))->pluck('total', 'user_id');
        $people = \App\Models\User::query()->whereIn('id', $page->getCollection()->flatMap(fn ($w) => [$w->approved_by, $w->updated_by])->filter()->unique())->pluck('name', 'id');

        $page->getCollection()->transform(function (WithdrawalRequest $w) use ($guard, $service, $available, $people) {
            $profile = PayoutProfile::query()->where('user_id', $w->user_id)->first();
            $problems = [];

            if (in_array($w->status, ['pending', 'approved', 'processing'], true)) {
                if (!$profile) {
                    $problems[] = 'No payout profile';
                } else {
                    if ($profile->kyc_status !== 'verified') {
                        $problems[] = 'KYC '.($profile->kyc_status ?: 'pending');
                    }
                    if (!$profile->bank_account_number || !$profile->ifsc_code) {
                        $problems[] = 'No bank account / IFSC';
                    }
                    try {
                        if ($until = $guard->holdUntil($profile)) {
                            $problems[] = 'Bank changed — hold until '.$until->copy()->timezone('Asia/Kolkata')->format('d M, h:i A');
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
                if ($w->user && !$service->withdrawalEnabledFor($w->user)) {
                    $problems[] = 'Withdrawals suspended for this owner';
                }
            }

            $w->setAttribute('problems', $problems);
            $w->setAttribute('ready', $problems === []);
            $w->setAttribute('owner_available', round((float) ($available[$w->user_id] ?? 0), 2));
            $w->setAttribute('kyc_status', $profile?->kyc_status ?: ($profile ? 'pending' : 'missing'));
            $w->setAttribute('holder', $profile?->account_holder_name);
            $w->setAttribute('ifsc', $profile?->ifsc_code);
            $w->setAttribute('bank_name', $profile?->bank_name);
            $w->setAttribute('approved_by_name', $w->approved_by ? ($people[$w->approved_by] ?? null) : null);
            $w->setAttribute('dates', [
                'requested' => $w->requested_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'approved' => $w->approved_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'paid' => $w->paid_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'rejected' => $w->rejected_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            ]);
            $w->setAttribute('waiting_days', $w->status === 'pending' && $w->requested_at ? (int) $w->requested_at->diffInDays(now()) : null);

            return $w;
        });

        return Inertia::render('V2/Admin/Withdrawals/Index', [
            'role' => $role,
            'filters' => ['status' => $status, 'month' => $month, 'search' => $search],
            'counts' => $counts,
            'totals' => $totals,
            'withdrawals' => $page,

            /* MIXXTUNE_PAYOUT_FILES_V1 */
            'payoutTools' => [
                'can_download' => $role === 'super_admin',
                'financial_year' => \App\Services\V2\PayoutDeductionService::financialYear()['label'],
                'tds' => app(\App\Services\V2\PayoutDeductionService::class)->settings(),
            ],
        ]);
    }

    /* MIXXTUNE_WITHDRAWALS_V2: tell the owner what happened to their request */
    private function tellOwner(WithdrawalRequest $withdrawal, string $type, string $title, string $message): void
    {
        try {
            $owner = $withdrawal->user ?? \App\Models\User::query()->find($withdrawal->user_id);
            if ($owner) {
                app(\App\Services\V2\PanelNotificationService::class)->send($owner, $type, $title, $message, '/v2/withdrawals');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function inr($amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }

    public function export(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
    ) {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $status = trim(
            (string) $request->input(
                'status',
                'pending'
            )
        );

        $allowedStatuses = [
            'pending',
            'approved',
            'paid',
            'rejected',
        ];

        abort_unless(
            in_array(
                $status,
                $allowedStatuses,
                true
            ),
            422,
            'Invalid withdrawal status.'
        );

        $month = trim(
            (string) $request->input(
                'month',
                ''
            )
        );

        if (
            $month !== '' &&
            !preg_match(
                '/^\d{4}-\d{2}$/',
                $month
            )
        ) {
            abort(
                422,
                'Invalid month.'
            );
        }

        $dateColumns = [
            'pending' =>
                'requested_at',

            'approved' =>
                'approved_at',

            'paid' =>
                'paid_at',

            'rejected' =>
                'rejected_at',
        ];

        $query = WithdrawalRequest::query()
            ->with([
                'user:id,name,email',
                'payoutProfile',
            ])
            ->where(
                'status',
                $status
            );

        $financialAccess
            ->applyFinancialOwnerScope(
                $query,
                $request->user()
            );

        if ($month !== '') {
            $query->where(
                $dateColumns[$status],
                'like',
                $month . '%'
            );
        }

        $rows = $query
            ->with('invoice:id,withdrawal_id,tds_amount,net_payable')
            ->orderByDesc(
                $dateColumns[$status]
            )
            ->orderByDesc('id')
            ->get();

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $sheet->setTitle(
            ucfirst($status)
        );

        $headers = [
            'Withdrawal Number',
            'Status',
            'User Name',
            'Email',
            'Label ID',
            'Artist ID',
            'Amount',
            'Currency',
            'Payment Method',
            'Payment Reference / UTR',
            'Requested At',
            'Approved At',
            'Paid At',
            'Rejected At',
            'Account Holder Name',
            'Bank Name',
            'Bank Account Number',
            'IFSC Code',
            'Branch Name',
            'PAN Number',
            'GST Number',
            'Address Line 1',
            'Address Line 2',
            'City',
            'State',
            'Postal Code',
            'Country',
            'KYC Status',
            'Request Note',
            'Admin / Rejection Note',
            'TDS Amount',
            'Net To Transfer',
        ];

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $rowNumber = 2;

        foreach ($rows as $withdrawal) {
            $profile =
                $withdrawal->payoutProfile;

            $metadata =
                $withdrawal->metadata
                ?? [];

            $sheet->fromArray(
                [
                    $withdrawal
                        ->withdrawal_number,

                    $withdrawal
                        ->status,

                    $withdrawal
                        ->user?->name,

                    $withdrawal
                        ->user?->email,

                    $withdrawal
                        ->label_id,

                    $withdrawal
                        ->artist_id,

                    (float) $withdrawal
                        ->amount,

                    $withdrawal
                        ->currency,

                    $withdrawal
                        ->payment_method,

                    $withdrawal
                        ->payment_reference,

                    optional(
                        $withdrawal
                            ->requested_at
                    )->format(
                        'Y-m-d H:i:s'
                    ),

                    optional(
                        $withdrawal
                            ->approved_at
                    )->format(
                        'Y-m-d H:i:s'
                    ),

                    optional(
                        $withdrawal
                            ->paid_at
                    )->format(
                        'Y-m-d H:i:s'
                    ),

                    optional(
                        $withdrawal
                            ->rejected_at
                    )->format(
                        'Y-m-d H:i:s'
                    ),

                    $profile
                        ?->account_holder_name,

                    $profile
                        ?->bank_name,

                    $profile
                        ?->bank_account_number,

                    $profile
                        ?->ifsc_code,

                    $profile
                        ?->branch_name,

                    $profile
                        ?->pan_number,

                    $profile
                        ?->gst_number,

                    $profile
                        ?->address_line_1,

                    $profile
                        ?->address_line_2,

                    $profile
                        ?->city,

                    $profile
                        ?->state,

                    $profile
                        ?->postal_code,

                    $profile
                        ?->country_code,

                    $profile
                        ?->kyc_status,

                    $withdrawal
                        ->note,

                    $metadata[
                        'admin_note'
                    ]
                        ?? $metadata[
                            'rejection_reason'
                        ]
                        ?? null,

                    ...$this->exportDeduction(
                        $withdrawal
                    ),
                ],
                null,
                'A' . $rowNumber
            );

            /*
             * Bank account, IFSC, UPI, PAN and GST
             * must remain text so Excel never strips
             * leading zeroes or changes formatting.
             */
            foreach (
                [
                    'Q',
                    'R',
                    'T',
                    'U',
                    'Z',
                ]
                as $column
            ) {
                $sheet
                    ->getStyle(
                        $column .
                        $rowNumber
                    )
                    ->getNumberFormat()
                    ->setFormatCode('@');
            }

            $rowNumber++;
        }

        $sheet
            ->freezePane(
                'A2'
            );

        $sheet
            ->setAutoFilter(
                $sheet
                    ->calculateWorksheetDimension()
            );

        foreach (
            range(
                'A',
                'Z'
            )
            as $column
        ) {
            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setAutoSize(true);
        }

        foreach (
            [
                'AA',
                'AB',
                'AC',
                'AD',
                'AE',
                'AF',
            ]
            as $column
        ) {
            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setAutoSize(true);
        }

        $filename =
            'withdrawals-'
            . $status
            . '-'
            . (
                $month !== ''
                    ? $month
                    : 'all'
            )
            . '.xlsx';

        return response()->streamDownload(
            function () use (
                $spreadsheet
            ) {
                $writer =
                    new Xlsx(
                        $spreadsheet
                    );

                $writer->save(
                    'php://output'
                );

                $spreadsheet
                    ->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                'Cache-Control' =>
                    'no-store, no-cache, must-revalidate',
            ]
        );
    }

    public function destroy(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $financialAccess
                ->canAccessFinancialOwner(
                    $request->user(),
                    $withdrawal->label_id
                        ? (int) $withdrawal->label_id
                        : null,
                    $withdrawal->artist_id
                        ? (int) $withdrawal->artist_id
                        : null
                ),
            403,
            'Financial object outside assigned scope.'
        );

        $before = \App\Services\V2\FinanceAudit::pick($withdrawal, ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']);

        $service
            ->deleteOpenWithdrawal(
                $withdrawal,
                $request->user()
            );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.withdrawal_deleted',
            'Withdrawal '.($withdrawal->withdrawal_number ?: '#'.$withdrawal->id).' deleted; reserved balance returned to the owner.',
            [],
            $withdrawal,
            $before
        );

        $this->tellOwner($withdrawal, 'withdrawal.deleted', 'Withdrawal request removed',
            'Your withdrawal request '.($withdrawal->withdrawal_number ?: '').' of '.self::inr($withdrawal->amount).' was removed by our team. The amount is back in your wallet.');

        return back()->with(
            'success',
            'Withdrawal deleted and reserved balance restored.'
        );
    }

    public function approve(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $financialAccess
                ->canAccessFinancialOwner(
                    $request->user(),
                    $withdrawal->label_id
                        ? (int) $withdrawal->label_id
                        : null,
                    $withdrawal->artist_id
                        ? (int) $withdrawal->artist_id
                        : null
                ),
            403,
            'Financial object outside assigned scope.'
        );

        $validated = $request->validate([
            'admin_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $before = \App\Services\V2\FinanceAudit::pick($withdrawal, ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']);

        $service->approve(
            $withdrawal,
            $request->user(),
            $validated['admin_note']
                ?? null
        );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.withdrawal_approved',
            'Withdrawal '.($withdrawal->withdrawal_number ?: '#'.$withdrawal->id).' approved.',
            \App\Services\V2\FinanceAudit::pick($withdrawal->fresh(), ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']) + ['admin_note' => $validated['admin_note'] ?? null],
            $withdrawal,
            $before
        );

        $this->tellOwner($withdrawal, 'withdrawal.approved', 'Withdrawal approved',
            'Your withdrawal '.($withdrawal->withdrawal_number ?: '').' of '.self::inr($withdrawal->amount).' is approved. The money will be sent to your bank account soon.');

        return back()->with(
            'success',
            'Withdrawal approved.'
        );
    }

    public function reject(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $financialAccess
                ->canAccessFinancialOwner(
                    $request->user(),
                    $withdrawal->label_id
                        ? (int) $withdrawal->label_id
                        : null,
                    $withdrawal->artist_id
                        ? (int) $withdrawal->artist_id
                        : null
                ),
            403,
            'Financial object outside assigned scope.'
        );

        $validated = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'min:3',
                'max:2000',
            ],
        ]);

        $before = \App\Services\V2\FinanceAudit::pick($withdrawal, ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']);

        $service->reject(
            $withdrawal,
            $request->user(),
            $validated[
                'rejection_reason'
            ]
        );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.withdrawal_rejected',
            'Withdrawal '.($withdrawal->withdrawal_number ?: '#'.$withdrawal->id).' rejected: '.$validated['rejection_reason'],
            \App\Services\V2\FinanceAudit::pick($withdrawal->fresh(), ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']),
            $withdrawal,
            $before
        );

        $this->tellOwner($withdrawal, 'withdrawal.rejected', 'Withdrawal not approved',
            'Your withdrawal '.($withdrawal->withdrawal_number ?: '').' of '.self::inr($withdrawal->amount).' was rejected: '.$validated['rejection_reason'].' The amount is back in your wallet.');

        return back()->with(
            'success',
            'Withdrawal rejected. The amount is back in the owner\'s wallet.'
        );
    }

    public function markPaid(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $financialAccess
                ->canAccessFinancialOwner(
                    $request->user(),
                    $withdrawal->label_id
                        ? (int) $withdrawal->label_id
                        : null,
                    $withdrawal->artist_id
                        ? (int) $withdrawal->artist_id
                        : null
                ),
            403,
            'Financial object outside assigned scope.'
        );

        $validated = $request->validate([
            'payment_reference' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],

            'admin_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        /* MIXXTUNE_TWO_STEP_ACTIONS_V1: e-mailed code before money is marked as sent. */
        app(\App\Services\V2\BankChangeGuard::class)->assertPayoutAllowed((int) $withdrawal->user_id);
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'withdrawal.paid:'.$withdrawal->id,
            'mark withdrawal #'.$withdrawal->id.' as paid'
        );

        $before = \App\Services\V2\FinanceAudit::pick($withdrawal, ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']);

        $service->markPaid(
            $withdrawal,
            $request->user(),
            $validated[
                'payment_reference'
            ],
            $validated['admin_note']
                ?? null
        );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.withdrawal_paid',
            'Withdrawal '.($withdrawal->withdrawal_number ?: '#'.$withdrawal->id).' marked paid, UTR '.$validated['payment_reference'].'.',
            \App\Services\V2\FinanceAudit::pick($withdrawal->fresh(), ['id', 'withdrawal_number', 'user_id', 'amount', 'currency', 'status', 'payment_method', 'payment_reference']),
            $withdrawal,
            $before
        );

        $this->tellOwner($withdrawal, 'withdrawal.paid', 'Payout sent',
            'Your withdrawal '.($withdrawal->withdrawal_number ?: '').' of '.self::inr($withdrawal->amount).' has been sent to your bank account. Bank reference (UTR): '.$validated['payment_reference'].'.');

        return back()->with(
            'success',
            'Withdrawal marked as paid.'
        );
    }

    public function kycIndex(
        Request $request,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
    ): Response {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $status = trim(
            (string) $request->input(
                'status',
                'submitted'
            )
        );

        $allowedStatuses = [
            'submitted',
            'verified',
            'rejected',
        ];

        if (
            $status !== '' &&
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'submitted';
        }

        $query = PayoutProfile::query()
            ->with([
                'user:id,name,email,role,phone,client_id,country,state_code,kyc_status',
            ]);

        if (
            $role =
                $permissions->role(
                    $request->user()
                )
        ) {
            if ($role === 'admin') {
                $query->whereIn(
                    'user_id',
                    $financialAccess
                        ->accessibleUserIds(
                            $request->user()
                        )
                );
            }
        }

        if ($status !== '') {
            $query->where(
                'kyc_status',
                $status
            );
        }

        $countBase =
            PayoutProfile::query();

        if (
            $permissions->role(
                $request->user()
            ) === 'admin'
        ) {
            $countBase->whereIn(
                'user_id',
                $financialAccess
                    ->accessibleUserIds(
                        $request->user()
                    )
            );
        }

        return Inertia::render(
            'V2/Admin/Kyc/Index',
            [
                'filters' => [
                    'status' => $status,
                ],

                'counts' => [
                    'submitted' =>
                        (clone $countBase)
                            ->where(
                                'kyc_status',
                                'submitted'
                            )
                            ->count(),

                    'verified' =>
                        (clone $countBase)
                            ->where(
                                'kyc_status',
                                'verified'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $countBase)
                            ->where(
                                'kyc_status',
                                'rejected'
                            )
                            ->count(),
                ],

                'profiles' =>
                    $query
                        ->orderByDesc(
                            'updated_at'
                        )
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function verifyKyc(
        Request $request,
        PayoutProfile $profile,
        PermissionService $permissions,
        AdminFinancialAccessService $financialAccess
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $financialAccess
                ->canAccessUser(
                    $request->user(),
                    (int) $profile->user_id
                ),
            403,
            'KYC profile outside assigned scope.'
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:verified,rejected',
            ],

            'kyc_notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $profile->update([
            'kyc_status' =>
                $validated['status'],

            'kyc_notes' =>
                $validated['kyc_notes']
                ?? null,

            'verified_at' =>
                $validated['status']
                    === 'verified'
                    ? now()
                    : null,

            'verified_by' =>
                $request->user()->id,
        ]);

        // Keep the legacy users.kyc_status mirror aligned with
        // the canonical payout profile lifecycle.
        $profile->user()
            ->update([
                'kyc_status' =>
                    $validated['status'],
            ]);

        return back()->with(
            'success',
            'KYC status updated.'
        );
    }

    /*
     * MIXXTUNE_TDS_V1: show GST / TDS / net to transfer on each request.
     * Paid requests show the saved payout document; open ones show a live preview.
     */
    private function withDeductions($paginator)
    {
        $service = app(\App\Services\V2\PayoutDeductionService::class);

        $paginator->getCollection()->transform(function ($withdrawal) use ($service) {
            $invoice = $withdrawal->invoice;

            if ($invoice) {
                $withdrawal->setAttribute('deduction', [
                    'source' => 'document',
                    'gross' => round((float) $withdrawal->amount, 2),
                    'gst_amount' => round((float) $invoice->gst_amount, 2),
                    'tds_percent' => (float) $invoice->tds_percentage,
                    'tds_amount' => round((float) $invoice->tds_amount, 2),
                    'net' => round((float) $invoice->net_payable, 2),
                    'note' => 'From payout document '.$invoice->invoice_number,
                ]);
            } elseif ($withdrawal->user && in_array($withdrawal->status, ['pending', 'approved', 'processing'], true)) {
                $calc = $service->preview($withdrawal->user, (float) $withdrawal->amount, (int) $withdrawal->id, $withdrawal->payoutProfile);
                $withdrawal->setAttribute('deduction', [
                    'source' => 'preview',
                    'gross' => $calc['gross'],
                    'gst_amount' => $calc['gst_amount'],
                    'tds_percent' => $calc['tds_percent'],
                    'tds_amount' => $calc['tds_amount'],
                    'net' => $calc['net'],
                    'note' => \App\Services\V2\PayoutDeductionService::reasonText($calc['tds_reason']),
                ]);
            }

            return $withdrawal;
        });

        return $paginator;
    }

    private function exportDeduction($withdrawal): array
    {
        if ($withdrawal->invoice) {
            return [round((float) $withdrawal->invoice->tds_amount, 2), round((float) $withdrawal->invoice->net_payable, 2)];
        }

        if ($withdrawal->user && in_array($withdrawal->status, ['pending', 'approved', 'processing'], true)) {
            $calc = app(\App\Services\V2\PayoutDeductionService::class)
                ->preview($withdrawal->user, (float) $withdrawal->amount, (int) $withdrawal->id, $withdrawal->payoutProfile);

            return [$calc['tds_amount'], $calc['net']];
        }

        return [null, null];
    }

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            in_array(
                $permissions->role(
                    $request->user()
                ),
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );
    }
}
