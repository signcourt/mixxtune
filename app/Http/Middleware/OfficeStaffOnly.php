<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W129 MIXXTUNE_OFFICE_LOGIN
 * An office "staff" account may only use My Office. Any other panel page sends them back to /office.
 * (All pages already need a login; this just keeps staff inside the office.)
 */
class OfficeStaffOnly
{
    /* paths an office staff member is allowed to open (prefix match, no leading slash) */
    private const ALLOW = ['office', 'notifications', 'logout', 'profile', 'email/verify', 'email/verification-notification'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->role === 'staff') {
            $path = ltrim($request->path(), '/');
            $ok = $path === '' || $path === 'office';
            foreach (self::ALLOW as $p) {
                if ($path === $p || str_starts_with($path, $p.'/')) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Office staff can only use My Office.'], 403)
                    : redirect('/office');
            }
        }

        return $next($request);
    }
}
