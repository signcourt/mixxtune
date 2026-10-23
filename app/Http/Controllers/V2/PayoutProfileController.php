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

                        /* MIXXTUNE_KYC_REVIEW_V1: the reviewer's note (why it was rejected / sent back) */
                        'kyc_notes' =>
                            in_array($profile->kyc_status, ['rejected', 'pending'], true)
                                ? $profile->kyc_notes
                                : null,

                        'verified_at' =>
                            optional(
                                $profile->verified_at
                            )?->toDateTimeString(),

                        /* W80: plain (non-secret) values for the form; bank no. / PAN stay masked */
                        'has_bank_account' => filled($profile->bank_account_number),
                        'has_pan' => filled($profile->pan_number),
                    ]
                    : null,

                /* W80 MIXXTUNE_KYC_EDIT_LOCK: may the owner change the details now? */
                'editState' => $this->editState($request),
            ]
        );
    }

    private function editState(Request $request): array
    {
        $user = $request->user();
        $role = str_replace('-', '_', (string) $user->role);

        if (!in_array($role, ['label', 'artist'], true)) {
            return ['can_edit' => false, 'state' => 'staff', 'unlock_expires' => null];
        }

        $state = app(\App\Services\V2\KycEditLock::class)->state($user);

        return [
            'can_edit' => $state['can_edit'],
            'state' => $state['state'],
            'unlock_expires' => $state['unlock']?->expires_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
        ];
    }

    /**
     * W80: the owner fills / corrects the KYC + bank details himself, only
     * while the lock allows it. "submit" also sends them for verification.
     * Bank details that already existed still need the e-mailed code and
     * start the 48-hour payout hold (PayoutProfileBankObserver).
     */
    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $role = str_replace('-', '_', (string) $user->role);

        abort_unless(in_array($role, ['label', 'artist'], true), 403, 'Only Label or Artist accounts have payout KYC.');

        $lock = app(\App\Services\V2\KycEditLock::class);
        $profile = PayoutProfile::query()->where('user_id', $user->id)->first();
        $state = $lock->state($user, $profile);

        if (!$state['can_edit']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kyc' => $state['state'] === 'verified'
                    ? 'Your KYC is verified and locked. To change it, open a Support ticket ("KYC / bank details"); after you sign the change form our team opens it for you.'
                    : 'Your KYC is waiting for verification. You can change it again if our team sends it back.',
            ]);
        }

        $hasBank = filled($profile?->bank_account_number);
        $hasPan = filled($profile?->pan_number);

        $data = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:190'],
            'bank_account_number' => [$hasBank ? 'nullable' : 'required', 'string', 'regex:/^[0-9]{6,20}$/'],
            'ifsc_code' => ['required', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'bank_name' => ['required', 'string', 'max:190'],
            'branch_name' => ['nullable', 'string', 'max:190'],
            'pan_number' => [$hasPan ? 'nullable' : 'required', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'gst_number' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Za-z0-9]{13}$/'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'submit' => ['nullable', 'boolean'],
        ], [
            'bank_account_number.regex' => 'Bank account number: only digits (6 to 20).',
            'ifsc_code.regex' => 'IFSC looks wrong: 11 characters like SBIN0001234.',
            'pan_number.regex' => 'PAN looks wrong: 10 characters like ABCDE1234F.',
            'gst_number.regex' => 'GST looks wrong: 15 characters.',
            'postal_code.regex' => 'PIN code must be 6 digits.',
        ]);

        $values = [
            'account_holder_name' => trim($data['account_holder_name']),
            'ifsc_code' => strtoupper(trim($data['ifsc_code'])),
            'bank_name' => trim($data['bank_name']),
            'branch_name' => filled($data['branch_name'] ?? null) ? trim($data['branch_name']) : null,
            'gst_number' => filled($data['gst_number'] ?? null) ? strtoupper(trim($data['gst_number'])) : null,
            'address_line_1' => trim($data['address_line_1']),
            'address_line_2' => filled($data['address_line_2'] ?? null) ? trim($data['address_line_2']) : null,
            'city' => trim($data['city']),
            'state' => trim($data['state']),
            'postal_code' => trim($data['postal_code']),
            'country_code' => $profile?->country_code ?: 'IN',
        ];
        if (filled($data['bank_account_number'] ?? null)) {
            $values['bank_account_number'] = trim($data['bank_account_number']);
        }
        if (filled($data['pan_number'] ?? null)) {
            $values['pan_number'] = strtoupper(trim($data['pan_number']));
        }

        $profile ??= new PayoutProfile([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $user->id,
            'kyc_status' => 'pending',
        ]);
        $profile->forceFill($values)->save();

        app(\App\Services\V2\AuditLogService::class)->record(
            'kyc.owner_saved',
            'kyc',
            'Owner saved own payout KYC'.(!empty($data['submit']) ? ' and sent it for verification.' : '.'),
            [],
            ['user_id' => $user->id, 'state' => $state['state']],
            $profile,
            $request
        );

        if (!empty($data['submit'])) {
            return $this->submit($request);
        }

        return back()->with('success', 'Saved. Press "Send for verification" when everything is filled.');
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

        /* W80: a verified / submitted profile opened again by a Super Admin can be sent once more */
        $reopened = app(\App\Services\V2\KycEditLock::class)->activeUnlock((int) $user->id) !== null;

        if ($profile->kyc_status === 'verified' && ! $reopened) {
            return back()->with(
                'success',
                'Your KYC is already verified.'
            );
        }

        if ($profile->kyc_status === 'submitted' && ! $reopened) {
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

        /* MIXXTUNE_BANK_ONLY_PAYOUTS_V1 */
        if (! $hasBank) {
            $missing[] = 'Bank account + IFSC';
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

        /* W80: the one-time opening is used up; tell the KYC reviewers */
        app(\App\Services\V2\KycEditLock::class)->markUsed((int) $user->id);
        try {
            $notices = app(\App\Services\V2\PanelNotificationService::class);
            foreach (\App\Models\User::query()->where('role', 'super_admin')->where('account_status', 'active')->get() as $staff) {
                $notices->send($staff, 'kyc.submitted', 'KYC sent for verification', ($user->name ?: $user->email).' sent payout KYC / bank details.', '/admin/kyc?status=submitted');
            }
        } catch (\Throwable $e) {
            report($e);
        }

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
