<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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

    public function authenticate(): void
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

        if (! Auth::attempt(
            [
                $field => $identity,
                'password' =>
                    (string) $this->input('password'),
            ],
            $this->boolean('remember')
        )) {
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
