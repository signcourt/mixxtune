<?php

namespace App\Http\Controllers\V2\Office;

use App\Http\Controllers\Controller;
use App\Models\Office\OfficeDevice;
use App\Services\V2\Office\OfficeMonitor;
use App\Services\V2\Office\OfficeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
 * W128 MIXXTUNE_OFFICE_MONITOR: the desktop agent's API (no user session; a device token).
 * /pair is open (needs the one-time code); the rest need the agent token.
 */
class AgentController extends Controller
{
    public function __construct(private readonly OfficeMonitor $monitor)
    {
    }

    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'platform' => ['nullable', 'in:windows,mac'],
            'device_name' => ['nullable', 'string', 'max:190'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'rustdesk_id' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json($this->monitor->pair($data['code'], $data));
    }

    private function device(Request $request): OfficeDevice
    {
        return $request->attributes->get('office_device');
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idle_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'foreground_app' => ['nullable', 'string', 'max:190'],
            'rustdesk_id' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($this->monitor->heartbeat($this->device($request), $data));
    }

    public function screenshot(Request $request): JsonResponse
    {
        if (!OfficeSettings::monitoringEnabled()) {
            return response()->json(['stored' => false, 'reason' => 'monitoring_off']);
        }
        $request->validate(['shot' => ['required', 'file', 'image', 'max:5120']]);
        $s = $this->monitor->storeScreenshot($this->device($request), $request->file('shot'));

        return response()->json(['stored' => (bool) $s]);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(OfficeSettings::all());
    }
}
