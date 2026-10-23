<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\KycEditUnlock;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\KycEditLock;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * W80 MIXXTUNE_KYC_EDIT_LOCK
 * Super Admin opens a locked KYC / bank profile for ONE change by the owner,
 * only with the owner's signed change form attached. Lock again, history and
 * the signed form download are here too.
 */
class KycUnlockController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can open KYC for changes.');
    }

    public function store(Request $request, User $user, PermissionService $permissions, KycEditLock $lock, AuditLogService $audit, PanelNotificationService $notices): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless(in_array($user->role, ['label', 'artist'], true), 422, 'Only Label or Artist accounts have payout KYC.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'document.required' => 'Attach the change form signed by the owner (PDF or photo).',
            'document.mimes' => 'The signed form must be a PDF, JPG or PNG.',
            'document.max' => 'The signed form can be at most 10 MB.',
        ]);

        if ($lock->activeUnlock((int) $user->id)) {
            return back()->withErrors(['kyc_unlock' => 'KYC is already open for this owner.']);
        }

        $file = $data['document'];
        $path = $file->store('kyc-unlocks/'.$user->id, 'local');

        $unlock = KycEditUnlock::query()->create([
            'user_id' => $user->id,
            'unlocked_by' => $request->user()->id,
            'reason' => trim($data['reason']),
            'document_path' => $path,
            'document_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'expires_at' => now()->addDays(KycEditLock::UNLOCK_DAYS),
        ]);

        $audit->record('kyc.edit_unlocked', 'kyc', 'KYC opened for one change by '.($user->name ?: $user->email).' (signed form attached).', [], ['reason' => $unlock->reason, 'expires_at' => $unlock->expires_at->toIso8601String()], $unlock, $request);

        try {
            $notices->send($user, 'kyc.edit_unlocked', 'You can now change your KYC / bank details', 'Open KYC & Payout, make the change and press "Send for verification" within '.KycEditLock::UNLOCK_DAYS.' days.', '/kyc-profile');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'KYC opened for one change by the owner (for '.KycEditLock::UNLOCK_DAYS.' days).');
    }

    public function revoke(Request $request, User $user, PermissionService $permissions, KycEditLock $lock, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);

        $unlock = $lock->activeUnlock((int) $user->id);
        if (!$unlock) {
            return back()->with('success', 'KYC was already locked.');
        }

        $unlock->forceFill(['revoked_at' => now(), 'revoked_by' => $request->user()->id])->save();
        $audit->record('kyc.edit_locked', 'kyc', 'KYC locked again for '.($user->name ?: $user->email).'.', [], [], $unlock, $request);

        return back()->with('success', 'KYC locked again.');
    }

    public function document(Request $request, KycEditUnlock $unlock, PermissionService $permissions): StreamedResponse
    {
        $this->superAdmin($request, $permissions);
        abort_unless(Storage::disk('local')->exists($unlock->document_path), 404, 'The signed form file is missing.');

        return Storage::disk('local')->download($unlock->document_path, $unlock->document_name ?: 'signed-change-form');
    }
}
