<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\BankChangeGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/* MIXXTUNE_TWO_STEP_ACTIONS_V1: enter / resend the confirmation code. */
class ActionOtpController extends Controller
{
    public function unlock(Request $request, ActionOtpGuard $guard): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $result = $guard->unlock($request->user(), trim($validated['otp']));

        return response()->json([
            'ok' => true,
            'message' => 'Confirmed. Now press the same button again within '.$result['unlock_minutes'].' minutes to '.$result['what'].'.',
        ]);
    }

    public function resend(Request $request, ActionOtpGuard $guard): JsonResponse
    {
        $pending = $guard->pending($request->user());

        if (!$pending) {
            throw ValidationException::withMessages([
                'otp' => 'Nothing is waiting for a code. Press the button again.',
            ]);
        }

        $guard->sendCode($request->user(), (string) $pending['scope'], (string) $pending['what'], true);

        return response()->json([
            'ok' => true,
            'message' => 'A new code was e-mailed to '.BankChangeGuard::maskEmail($request->user()->email).'.',
        ]);
    }
}
