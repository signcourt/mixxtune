<?php

namespace App\Services\V2;

use App\Mail\ActionOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/*
 * MIXXTUNE_TWO_STEP_ACTIONS_V1
 *
 * Second step for money-sensitive actions (approve a revenue report, change
 * a label's revenue %, mark a withdrawal paid): a 6-digit code is e-mailed
 * to the person doing it. The code unlocks that one action ("scope") for
 * 10 minutes; the unlock is used up by the next request that performs it.
 * Requests without a logged-in user (artisan, scheduler) are not gated.
 */
class ActionOtpGuard
{
    /** @var array<string, bool> W118: scopes whose second approval was used in this request */
    private static array $approvedThisRequest = [];

    public const UNLOCK_MINUTES = 10;

    public const OTP_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /** Scopes unlocked for this request; forgotten when the request ends. */
    private static array $usedThisRequest = [];

    private static bool $terminatingRegistered = false;

    /* File store on purpose: codes are created inside transactions that are rolled back. */
    public static function store(): \Illuminate\Contracts\Cache\Repository
    {
        /* tests: per-test in-memory store, so tests never touch the server's cache folder */
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return static::store();
    }

    private function pendingKey(User $actor): string
    {
        return 'mixxtune:actotp:pending:'.$actor->id;
    }

    private function unlockKey(User $actor, string $scope): string
    {
        return 'mixxtune:actotp:unlock:'.$actor->id.':'.sha1($scope);
    }

    /**
     * Allow the action when the actor has unlocked this scope; otherwise
     * e-mail a code and stop the request (nothing is saved).
     */
    public function require(?User $actor, string $scope, string $what): void
    {
        if (!$actor instanceof User) {
            return;
        }

        /* W118 MIXXTUNE_MAKER_CHECKER: when "Second approval" is ON, another Super Admin must have said yes first */
        $approvalId = isset(self::$approvedThisRequest[$scope]) ? null : app(MakerChecker::class)->require($actor, $scope, $what);

        $key = $this->unlockKey($actor, $scope);

        if (isset(self::$usedThisRequest[$key]) || $this->cache()->get($key)) {
            $this->markUsed($key);
            if ($approvalId) {
                app(MakerChecker::class)->used($approvalId, $actor);
                self::$approvedThisRequest[$scope] = true;
            }

            return;
        }

        $this->sendCode($actor, $scope, $what);

        throw ValidationException::withMessages([
            'action_otp' => 'To '.$what.' a confirmation code is needed. A 6-digit code was e-mailed to '
                .BankChangeGuard::maskEmail($actor->email)
                .'. Enter it in the box that opened, then press the same button again. Nothing was saved yet.',
        ]);
    }

    private function markUsed(string $key): void
    {
        self::$usedThisRequest[$key] = true;

        if (!self::$terminatingRegistered) {
            self::$terminatingRegistered = true;

            app()->terminating(function () {
                foreach (array_keys(self::$usedThisRequest) as $usedKey) {
                    static::store()->forget($usedKey);
                }

                self::$usedThisRequest = [];
                self::$approvedThisRequest = [];
                self::$terminatingRegistered = false;
            });
        }
    }

    public function sendCode(User $actor, string $scope, string $what, bool $force = false): bool
    {
        $key = $this->pendingKey($actor);
        $existing = $this->cache()->get($key);

        if (
            is_array($existing)
            && ($existing['scope'] ?? null) === $scope
            && (int) ($existing['sent_at'] ?? 0) > now()->subSeconds(self::RESEND_SECONDS)->getTimestamp()
        ) {
            if ($force) {
                throw ValidationException::withMessages([
                    'otp' => 'Please wait 60 seconds before requesting another code.',
                ]);
            }

            return false;
        }

        $otp = (string) random_int(100000, 999999);

        $this->cache()->put($key, [
            'scope' => $scope,
            'what' => $what,
            'hash' => Hash::make($otp),
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::OTP_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_MINUTES + 1));

        try {
            Mail::to($actor->email)->send(new ActionOtpMail($otp, (string) ($actor->name ?: 'Admin'), $what));
        } catch (\Throwable $e) {
            $this->cache()->forget($key);
            report($e);

            throw ValidationException::withMessages([
                'action_otp' => 'Could not e-mail the confirmation code. Nothing was saved. Please try again later.',
            ]);
        }

        return true;
    }

    public function pending(User $actor): ?array
    {
        $record = $this->cache()->get($this->pendingKey($actor));

        return is_array($record) ? $record : null;
    }

    public function unlock(User $actor, string $otp): array
    {
        $key = $this->pendingKey($actor);
        $record = $this->cache()->get($key);

        if (!is_array($record) || (int) ($record['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->cache()->forget($key);

            throw ValidationException::withMessages([
                'otp' => 'The code has expired. Press the button again to get a new code.',
            ]);
        }

        if ((int) ($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Too many wrong codes. Press the button again in a few minutes to get a new code.',
            ]);
        }

        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $this->cache()->put($key, $record, now()->addMinutes(self::OTP_MINUTES + 1));

        if (!preg_match('/^\d{6}$/', $otp) || !Hash::check($otp, (string) ($record['hash'] ?? ''))) {
            throw ValidationException::withMessages([
                'otp' => 'Wrong code. Please check the e-mail and try again.',
            ]);
        }

        $this->cache()->forget($key);
        $this->cache()->put($this->unlockKey($actor, (string) $record['scope']), true, now()->addMinutes(self::UNLOCK_MINUTES));

        return [
            'what' => (string) ($record['what'] ?? ''),
            'unlock_minutes' => self::UNLOCK_MINUTES,
        ];
    }
}
