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

    /**
     * Submit the current financial owner's payout/KYC profile
     * for Super Admin review.
     *
     * The profile remains user read-only. This endpoint only
     * performs the explicit pending/rejected -> submitted
     * lifecycle transition.
     */
    public function submit(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $role = str_replace('-', '_', (string) $user->role);

        abort_unless(
            in_array($role, ['label', 'artist'], true),
            403,
            'Only Label or Artist accounts can submit payout KYC.'
        );

        $profile = \App\Models\Finance\PayoutProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kyc' => 'Your payout/KYC profile has not been configured yet. Please contact support.',
            ]);
        }

        if ($profile->kyc_status === 'verified') {
            return back()->with(
                'success',
                'Your KYC is already verified.'
            );
        }

        if ($profile->kyc_status === 'submitted') {
            return back()->with(
                'success',
                'Your KYC is already submitted for verification.'
            );
        }

        /*
         * Required identity/address fields.
         */
        $required = [
            'account_holder_name' => 'Account holder name',
            'pan_number'          => 'PAN number',
            'address_line_1'      => 'Address',
            'city'                => 'City',
            'state'               => 'State',
            'postal_code'         => 'Postal code',
            'country_code'        => 'Country',
        ];

        $missing = [];

        foreach ($required as $field => $label) {
            if (blank($profile->{$field})) {
                $missing[] = $label;
            }
        }

        /*
         * At least one usable payout method is required.
         */
        $hasBank =
            filled($profile->account_holder_name)
            && filled($profile->bank_account_number)
            && filled($profile->ifsc_code);

        $hasUpi = filled($profile->upi_id);

        if (! $hasBank && ! $hasUpi) {
            $missing[] = 'Bank account + IFSC or UPI ID';
        }

        if ($missing !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kyc' =>
                    'KYC cannot be submitted. Missing: '
                    .implode(', ', array_unique($missing))
                    .'. Please contact support to complete the profile.',
            ]);
        }

        /*
         * GST is optional. It is intentionally not a submission
         * requirement because non-GST payees are valid.
         */
        $profile->forceFill([
            'kyc_status' => 'submitted',
            'verified_at' => null,
            'verified_by' => null,
        ])->save();

        /*
         * Keep the legacy User-level status synchronized where
         * the schema supports the submitted lifecycle value.
         *
         * Do not allow failure here to roll back the canonical
         * payout profile submission.
         */
        try {
            $column = \Illuminate\Support\Facades\DB::selectOne(
                "SHOW COLUMNS FROM users LIKE 'kyc_status'"
            );

            if ($column) {
                $type = strtolower((string) ($column->Type ?? ''));

                if (
                    ! str_contains($type, 'enum')
                    || str_contains($type, "'submitted'")
                ) {
                    $user->forceFill([
                        'kyc_status' => 'submitted',
                    ])->save();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with(
            'success',
            'KYC submitted for verification successfully.'
        );
    }

}
