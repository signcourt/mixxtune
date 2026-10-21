<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (
            ! $this->filled('login')
            && $this->filled('email')
        ) {
            $this->merge([
                'login' => $this->input('email'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
            ],
        ];
    }

    public function authenticatedUser(): User
    {
        $this->ensureIsNotRateLimited();

        $identity = Str::lower(
            trim(
                (string) $this->input('login')
            )
        );

        $field = filter_var(
            $identity,
            FILTER_VALIDATE_EMAIL
        )
            ? 'email'
            : 'username';

        $user = User::query()
            ->where($field, $identity)
            ->first();

        if (
            !$user ||
            !Hash::check(
                (string) $this->input('password'),
                (string) $user->password
            )
        ) {
            RateLimiter::hit(
                $this->throttleKey()
            );

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear(
            $this->throttleKey()
        );

        /* W59: a suspended account cannot sign in (it could before) */
        if (in_array((string) $user->account_status, \App\Http\Middleware\EnsureAccountActive::BLOCKED, true)) {
            throw ValidationException::withMessages([
                'login' => 'This account is suspended. Please contact Mixx Tune support.',
            ]);
        }

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            5
        )) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn(
            $this->throttleKey()
        );

        throw ValidationException::withMessages([
            'login' => trans(
                'auth.throttle',
                [
                    'seconds' => $seconds,
                    'minutes' =>
                        ceil($seconds / 60),
                ]
            ),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower(
                (string) $this->input('login')
            )
            . '|'
            . $this->ip()
        );
    }
}
