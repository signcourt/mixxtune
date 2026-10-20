<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\BankChangeGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/* MIXXTUNE_BANK_CHANGE_PROTECTION_V1: enter / resend the bank-change confirmation code. */
class BankChangeController extends Controller
{
    public function unlock(Request $request, BankChangeGuard $guard): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $result = $guard->unlock($request->user(), trim($validated['otp']));

        return response()->json([
            'ok' => true,
            'owner_name' => $result['owner_name'],
            'unlock_minutes' => $result['unlock_minutes'],
            'message' => 'Confirmed. Now press Save again within '.$result['unlock_minutes'].' minutes to change the bank details of '.$result['owner_name'].'.',
        ]);
    }

    public function resend(Request $request, BankChangeGuard $guard): JsonResponse
    {
        $pending = $guard->pending($request->user());
        $owner = $pending ? User::query()->find($pending['owner_id']) : null;

        if (!$owner) {
            throw ValidationException::withMessages([
                'otp' => 'No bank change is waiting for a code. Press Save again.',
            ]);
        }

        $guard->sendCode($request->user(), $owner, true);

        return response()->json([
            'ok' => true,
            'message' => 'A new code was e-mailed to '.BankChangeGuard::maskEmail($request->user()->email).'.',
        ]);
    }
}
