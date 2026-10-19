<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\LabelOwnershipBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1
 * Super-admin only: CSV template, preview, OTP, confirm + batched run.
 */
class LabelOwnershipBulkController extends Controller
{
    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'super_admin', 403);

        return $user;
    }

    public function template(Request $request, LabelOwnershipBulkImportService $service): Response
    {
        $this->superAdmin($request);
        $all = $request->query('scope') === 'all';
        $name = 'label-owners-'.($all ? 'all-labels' : 'labels-without-owner').'-'.now()->format('Ymd-His').'.csv';

        return response($service->template($all), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function preview(Request $request, LabelOwnershipBulkImportService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        $request->validate([
            'csv' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ]);

        $analysis = $service->analyse((string) file_get_contents($request->file('csv')->getRealPath()));
        $token = (empty($analysis['errors']) && !empty($analysis['groups']))
            ? $service->storePlan($admin, $analysis)
            : null;

        return response()->json([
            'token' => $token,
            'summary' => $analysis['summary'],
            'errors' => $analysis['errors'],
            'groups' => array_map(fn ($g) => [
                'email' => $g['email'],
                'owner_name' => $g['owner_name'],
                'mode' => $g['mode'],
                'owner_status' => $g['owner_status'] ?? null,
                'assign' => $g['assign'],
                'transfer' => $g['transfer'],
                'unchanged' => $g['unchanged'],
                'labels' => array_map(fn ($l) => [
                    'id' => $l['id'],
                    'name' => $l['name'],
                    'current_owner_email' => $l['current_owner_email'],
                ], $g['labels']),
            ], $analysis['groups']),
        ]);
    }

    public function sendOtp(Request $request, LabelOwnershipBulkImportService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        $data = $request->validate(['token' => ['required', 'string', 'size:40']]);
        $service->sendOtp($admin, $data['token']);

        [$local, $domain] = array_pad(explode('@', (string) $admin->email, 2), 2, '');

        return response()->json([
            'ok' => true,
            'sent_to' => mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain,
        ]);
    }

    public function confirm(Request $request, LabelOwnershipBulkImportService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        $data = $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'otp' => ['required', 'digits:6'],
        ]);

        $service->confirm($admin, $data['token'], $data['otp']);

        return response()->json(['ok' => true]);
    }

    public function run(Request $request, LabelOwnershipBulkImportService $service): JsonResponse
    {
        $admin = $this->superAdmin($request);
        $data = $request->validate(['token' => ['required', 'string', 'size:40']]);

        return response()->json(['ok' => true] + $service->runBatch($admin, $data['token'], $request));
    }
}
