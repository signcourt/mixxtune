<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PayoutProfileController extends Controller
{
    public function edit(
        Request $request,
        PermissionService $permissions
    ): Response {
        $profile = PayoutProfile::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        return Inertia::render(
            'V2/KycProfile/Edit',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'profile' =>
                    $profile,
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'account_holder_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bank_account_number' => [
                'nullable',
                'string',
                'min:6',
                'max:40',
            ],

            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'ifsc_code' => [
                'nullable',
                'string',
                'max:30',
            ],

            'branch_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'upi_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pan_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'gst_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address_line_1' => [
                'required',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'state' => [
                'required',
                'string',
                'max:100',
            ],

            'postal_code' => [
                'required',
                'string',
                'max:20',
            ],

            'country_code' => [
                'required',
                'string',
                'size:2',
            ],
        ]);

        $existing = PayoutProfile::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->first();

        if (
            empty(
                $validated[
                    'bank_account_number'
                ]
            )
            && $existing
        ) {
            unset(
                $validated[
                    'bank_account_number'
                ]
            );
        }

        if (
            empty(
                $validated[
                    'pan_number'
                ]
            )
            && $existing
        ) {
            unset(
                $validated[
                    'pan_number'
                ]
            );
        }

        PayoutProfile::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $request->user()->id,
                ],
                [
                    'public_id' =>
                        $existing?->public_id
                        ?: (string) Str::ulid(),

                    ...$validated,

                    'country_code' =>
                        strtoupper(
                            $validated[
                                'country_code'
                            ]
                        ),

                    'ifsc_code' =>
                        isset(
                            $validated[
                                'ifsc_code'
                            ]
                        )
                            ? strtoupper(
                                $validated[
                                    'ifsc_code'
                                ]
                            )
                            : null,

                    'pan_number' =>
                        isset(
                            $validated[
                                'pan_number'
                            ]
                        )
                            ? strtoupper(
                                $validated[
                                    'pan_number'
                                ]
                            )
                            : null,

                    'kyc_status' =>
                        $existing?->kyc_status
                            === 'verified'
                            ? 'verified'
                            : 'submitted',
                ]
            );

        return back()->with(
            'success',
            'Payout and KYC profile saved.'
        );
    }
}
