<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\PayoutProfile;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
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

                /*
                 * USER_READ_ONLY_KYC
                 *
                 * KYC/Payout information is provisioned and managed
                 * exclusively by Super Admin.
                 *
                 * Never expose raw encrypted Bank Account or PAN
                 * values to the user-facing profile.
                 */
                'profile' => $profile
                    ? [
                        'public_id' =>
                            $profile->public_id,

                        'account_holder_name' =>
                            $profile->account_holder_name,

                        'masked_bank_account' =>
                            $profile->masked_bank_account,

                        'bank_name' =>
                            $profile->bank_name,

                        'ifsc_code' =>
                            $profile->ifsc_code,

                        'branch_name' =>
                            $profile->branch_name,

                        'upi_id' =>
                            $profile->upi_id,

                        'masked_pan' =>
                            $profile->masked_pan,

                        'gst_number' =>
                            $profile->gst_number,

                        'address_line_1' =>
                            $profile->address_line_1,

                        'address_line_2' =>
                            $profile->address_line_2,

                        'city' =>
                            $profile->city,

                        'state' =>
                            $profile->state,

                        'postal_code' =>
                            $profile->postal_code,

                        'country_code' =>
                            $profile->country_code,

                        'kyc_status' =>
                            $profile->kyc_status,

                        'verified_at' =>
                            optional(
                                $profile->verified_at
                            )?->toDateTimeString(),
                    ]
                    : null,
            ]
        );
    }
}
