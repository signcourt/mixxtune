<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsernameService
{
    /**
     * Usernames unavailable for normal accounts.
     */
    private const RESERVED = [
        'admin',
        'administrator',
        'api',
        'artist',
        'auth',
        'billing',
        'dashboard',
        'help',
        'home',
        'label',
        'login',
        'logout',
        'mixxtune',
        'register',
        'root',
        'security',
        'settings',
        'staff',
        'super-admin',
        'superadmin',
        'support',
        'system',
        'user',
        'users',
        'wallet',
        'www',
    ];

    public function normalize(string $value): string
    {
        $value = Str::lower(
            Str::ascii(
                trim($value)
            )
        );

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        ) ?? '';

        $value = trim($value, '-');

        if ($value === '') {
            $value = 'user';
        }

        if (strlen($value) < 4) {
            $value .= '-user';
        }

        return substr($value, 0, 40);
    }

    public function generate(
        string $name,
        ?string $email = null,
        ?int $ignoreUserId = null
    ): string {
        $source = trim($name);

        if ($source === '' && $email) {
            $source = Str::before($email, '@');
        }

        $base = $this->normalize($source);

        if ($this->isReserved($base)) {
            $base = substr(
                $base . '-account',
                0,
                40
            );
        }

        $candidate = $base;
        $counter = 2;

        while (
            $this->exists(
                $candidate,
                $ignoreUserId
            )
            || $this->isReserved($candidate)
        ) {
            $suffix = '-' . $counter;

            $candidate =
                substr(
                    $base,
                    0,
                    40 - strlen($suffix)
                )
                . $suffix;

            $counter++;
        }

        return $candidate;
    }

    public function isReserved(string $username): bool
    {
        return in_array(
            Str::lower($username),
            self::RESERVED,
            true
        );
    }

    public function exists(
        string $username,
        ?int $ignoreUserId = null
    ): bool {
        $query = DB::table('users')
            ->whereRaw(
                'LOWER(username) = ?',
                [Str::lower($username)]
            );

        if ($ignoreUserId !== null) {
            $query->where(
                'id',
                '!=',
                $ignoreUserId
            );
        }

        return $query->exists();
    }

    public function validateFormat(string $username): bool
    {
        return preg_match(
            '/^[a-z0-9](?:[a-z0-9-]{2,38}[a-z0-9])?$/',
            $username
        ) === 1;
    }
}
