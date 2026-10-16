<?php

namespace App\Http\Controllers\Auth;

use App\Services\LoginEmailOtpService;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        LoginEmailOtpService $otpService
    ): RedirectResponse {
        $user = $request->authenticatedUser();

        $otpService->send(
            $user,
            $request->ip(),
            $request->userAgent()
        );

        $request->session()->put([
            'login_otp_user_id' =>
                $user->id,

            'login_otp_remember' =>
                $request->boolean('remember'),

            'login_otp_masked_email' =>
                $this->maskEmail(
                    (string) $user->email
                ),
        ]);

        return redirect()->route(
            'login.otp.show'
        );
    }

    private function maskEmail(
        string $email
    ): string {
        [$local, $domain] =
            array_pad(
                explode('@', $email, 2),
                2,
                ''
            );

        if ($domain === '') {
            return '***';
        }

        $visible = mb_substr(
            $local,
            0,
            min(2, mb_strlen($local))
        );

        return $visible .
            str_repeat(
                '*',
                max(
                    3,
                    mb_strlen($local) -
                    mb_strlen($visible)
                )
            ) .
            '@' .
            $domain;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
