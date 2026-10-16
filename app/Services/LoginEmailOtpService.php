<?php

namespace App\Services;

use App\Mail\LoginEmailOtpMail;
use App\Models\Auth\LoginEmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class LoginEmailOtpService
{
    public const EXPIRY_MINUTES = 5;
    public const RESEND_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public function send(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $latest = LoginEmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('sent_at')
            ->first();

        if (
            $latest &&
            $latest->sent_at &&
            $latest->sent_at->gt(
                now()->subSeconds(self::RESEND_SECONDS)
            )
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait before requesting another OTP.',
            ]);
        }

        LoginEmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
            ]);

        $otp = (string) random_int(
            100000,
            999999
        );

        LoginEmailOtp::create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(
                self::EXPIRY_MINUTES
            ),
            'attempts' => 0,
            'sent_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
                ? mb_substr($userAgent, 0, 500)
                : null,
        ]);

        Mail::to($user->email)->send(
            new LoginEmailOtpMail(
                $otp,
                $user->name ?: 'User'
            )
        );
    }

    public function verify(
        User $user,
        string $otp
    ): bool {
        $record = LoginEmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (
            !$record ||
            $record->expires_at->isPast()
        ) {
            return false;
        }

        if (
            $record->attempts >=
            self::MAX_ATTEMPTS
        ) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check(
            $otp,
            $record->otp_hash
        )) {
            return false;
        }

        $record->forceFill([
            'consumed_at' => now(),
        ])->save();

        return true;
    }
}
