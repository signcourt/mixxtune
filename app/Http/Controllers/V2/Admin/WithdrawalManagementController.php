<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WithdrawalRequest;
use App\Services\V2\PermissionService;
use App\Services\V2\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
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

        $query = WithdrawalRequest::query()
            ->with([
                'user:id,name,email',
                'payoutProfile',
            ]);

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        $countBase =
            WithdrawalRequest::query();

        return Inertia::render(
            'V2/Admin/Withdrawals/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' =>
                        $status,
                ],

                'counts' => [
                    'pending' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'pending'
                            )
                            ->count(),

                    'approved' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'paid' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'paid'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $countBase)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],

                'withdrawals' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function approve(
        Request $request,
        WithdrawalRequest $withdrawal,
        PermissionService $permissions,
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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
        WithdrawalService $service
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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

    public function verifyKyc(
        Request $request,
        PayoutProfile $profile,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
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
