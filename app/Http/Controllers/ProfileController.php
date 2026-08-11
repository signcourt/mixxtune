<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $payoutProfile = \App\Models\Finance\PayoutProfile::query()
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),

            'payoutProfile' => $payoutProfile ? [
                'public_id' => $payoutProfile->public_id,
                'account_holder_name' => $payoutProfile->account_holder_name,
                'bank_account_number' => $payoutProfile->bank_account_number,
                'bank_name' => $payoutProfile->bank_name,
                'ifsc_code' => $payoutProfile->ifsc_code,
                'branch_name' => $payoutProfile->branch_name,
                'upi_id' => $payoutProfile->upi_id,
                'pan_number' => $payoutProfile->pan_number,
                'gst_number' => $payoutProfile->gst_number,
                'address_line_1' => $payoutProfile->address_line_1,
                'address_line_2' => $payoutProfile->address_line_2,
                'city' => $payoutProfile->city,
                'state' => $payoutProfile->state,
                'postal_code' => $payoutProfile->postal_code,
                'country_code' => $payoutProfile->country_code,
                'kyc_status' => $payoutProfile->kyc_status,
                'verified_at' => optional($payoutProfile->verified_at)?->toDateTimeString(),
            ] : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        abort(
            403,
            'Profile information is read-only. Contact an authorised Mixx Tune administrator for permitted changes.'
        );
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
