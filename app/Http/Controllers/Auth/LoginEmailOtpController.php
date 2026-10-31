<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginEmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginEmailOtpController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/LoginOtp', [
            'maskedEmail' => $request->session()->get(
                'login_otp_masked_email'
            ),
        ]);
    }

    public function verify(
        Request $request,
        LoginEmailOtpService $otpService
    ): RedirectResponse {
        $request->validate([
            'otp' => [
                'required',
                'digits:6',
            ],
        ]);

        $userId = $request->session()->get(
            'login_otp_user_id'
        );

        if (!$userId) {
            return redirect()->route('login');
        }

        $key = 'login-email-otp:' .
            $userId . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'otp' => 'Too many OTP attempts. Please request a new OTP.',
            ]);
        }

        $user = User::find($userId);

        if (!$user) {
            $this->clearPendingLogin($request);

            return redirect()->route('login');
        }

        if (!$otpService->verify(
            $user,
            (string) $request->input('otp')
        )) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP.',
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull(
            'login_otp_remember',
            false
        );

        Auth::login(
            $user,
            $remember
        );

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $this->clearPendingLogin($request);

        $role = strtolower(
            trim(
                (string) $user->role
            )
        );

        /* W128 MIXXTUNE_OFFICE_MONITOR: logging in is clocking in for office staff */
        if ($role === 'staff') {
            try {
                app(\App\Services\V2\Office\OfficeService::class)->clockIn($user);
            } catch (\Throwable) {
            }
        }

        $destination = match ($role) {
            'super_admin' =>
                '/super-admin/dashboard',

            'admin' =>
                '/admin/dashboard',

            'label' =>
                '/label/dashboard',

            /* W127 MIXXTUNE_OFFICE */
            'staff' =>
                '/office',

            default =>
                '/artist/dashboard',
        };

        $request->session()->forget(
            'url.intended'
        );

        return redirect()->to(
            $destination
        );
    }

    public function resend(
        Request $request,
        LoginEmailOtpService $otpService
    ): RedirectResponse {
        $userId = $request->session()->get(
            'login_otp_user_id'
        );

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (!$user) {
            $this->clearPendingLogin($request);

            return redirect()->route('login');
        }

        $otpService->send(
            $user,
            $request->ip(),
            $request->userAgent()
        );

        return back()->with(
            'status',
            'A new OTP has been sent to your email.'
        );
    }

    private function clearPendingLogin(
        Request $request
    ): void {
        $request->session()->forget([
            'login_otp_user_id',
            'login_otp_remember',
            'login_otp_masked_email',
        ]);
    }
}
