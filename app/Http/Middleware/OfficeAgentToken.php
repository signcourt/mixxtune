<?php

namespace App\Http\Middleware;

use App\Models\Office\OfficeDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W128 MIXXTUNE_OFFICE_MONITOR: the desktop agent authenticates with its own long token
 * (issued at pairing, stored only as a sha256). No user session; just this device.
 */
class OfficeAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No agent token.'], 401);
        }
        $device = OfficeDevice::query()->where('token_hash', hash('sha256', $token))->where('approved', true)->first();
        if (!$device) {
            return response()->json(['message' => 'This agent is not paired any more.'], 401);
        }
        $request->attributes->set('office_device', $device);

        return $next($request);
    }
}
