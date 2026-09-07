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
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
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

        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $status = 'pending';
        }

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
            $month = '';
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
            ]);

        $financialAccess
            ->applyFinancialOwnerScope(
                $query,
                $request->user()
            );

        $query->where(
            'status',
            $status
        );

        if ($month !== '') {
            $query->where(
                $dateColumns[$status],
                'like',
                $month . '%'
            );
        }

        $countForStatus = function (
            string $countStatus
        ) use (
            $request,
            $financialAccess,
            $month,
            $dateColumns
        ): int {
            $builder =
                WithdrawalRequest::query();

            $financialAccess
                ->applyFinancialOwnerScope(
                    $builder,
                    $request->user()
                );

            $builder->where(
                'status',
                $countStatus
            );

            if ($month !== '') {
                $builder->where(
                    $dateColumns[$countStatus],
                    'like',
                    $month . '%'
                );
            }

            return $builder->count();
        };

        return Inertia::render(
            'V2/Admin/Withdrawals/Index',
            [
                'role' =>
                    $role,

                'filters' => [
                    'status' =>
                        $status,

                    'month' =>
                        $month,
                ],

                'counts' => [
                    'pending' =>
                        $countForStatus(
                            'pending'
                        ),

                    'approved' =>
                        $countForStatus(
                            'approved'
                        ),

                    'paid' =>
                        $countForStatus(
                            'paid'
                        ),

                    'rejected' =>
                        $countForStatus(
                            'rejected'
                        ),
                ],

                'withdrawals' =>
                    $query
                        ->orderByDesc(
                            $dateColumns[$status]
                        )
                        ->orderByDesc('id')
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
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
            'UPI ID',
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
                        ?->upi_id,

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
                    'V',
                    'AA',
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

        $service
            ->deleteOpenWithdrawal(
                $withdrawal,
                $request->user()
            );

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

        $service->approve(
            $withdrawal,
            $request->user(),
            $validated['admin_note']
                ?? null
        );

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

        $service->reject(
            $withdrawal,
            $request->user(),
            $validated[
                'rejection_reason'
            ]
        );

        return back()->with(
            'success',
            'Withdrawal rejected.'
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

        $service->markPaid(
            $withdrawal,
            $request->user(),
            $validated[
                'payment_reference'
            ],
            $validated['admin_note']
                ?? null
        );

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
                'user:id,name,email',
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

        return back()->with(
            'success',
            'KYC status updated.'
        );
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
