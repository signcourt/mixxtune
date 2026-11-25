<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\User;
use App\Services\V2\PermissionService;
use App\Services\V2\PlatformRules;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use App\Models\Distribution\Track;
use App\Services\V2\FinanceAudit;
use App\Services\V2\StaffAlerts;

/**
 * W211 - WAV replacement after a release left the panel.
 *  Super Admin: replaces the track file at once (old file is kept on disk, path stored on the record).
 *  Manager: uploads the new WAV as a REQUEST; a Super Admin listens and decides it under Approvals.
 *  Nobody else has a route. Every step -> audio_change_requests + audit.
 */
class AudioChangeController extends Controller
{
    private const SCOPE = 'track.audio:';

    public static function scope(int $rowId): string
    {
        return self::SCOPE.$rowId;
    }

    private function role(Request $request, PermissionService $permissions): string
    {
        return $permissions->role($request->user());
    }

    private function wavRules(): array
    {
        $max = (int) (class_exists(PlatformRules::class) && method_exists(PlatformRules::class, 'audioMaxMb') ? PlatformRules::audioMaxMb() : 300);

        return [
            'audio' => ['required', 'file', 'mimes:wav,wave', 'max:'.($max * 1024)],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** tracks of a release for the dialog (admin in scope / super admin) */
    public function tracks(Request $request, Release $release, PermissionService $permissions, ReleaseAccessService $access): JsonResponse
    {
        $role = $this->role($request, $permissions);
        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403);
        abort_unless($access->canAccess($request->user(), $release), 403, 'This release is not in your assigned labels / artists.');

        $pending = Schema::hasTable('audio_change_requests')
            ? DB::table('audio_change_requests')->where('release_id', $release->id)->where('status', 'pending')->pluck('track_id')->map(fn ($i) => (int) $i)->all()
            : [];
        $tracks = Track::query()->where('release_id', $release->id)->orderBy('track_number')->orderBy('id')
            ->get(['id', 'title', 'track_number', 'audio_path', 'audio_original_name'])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'title' => (string) $t->title,
                'number' => (int) ($t->track_number ?? 0),
                'has_wav' => (bool) $t->audio_path,
                'file' => (string) ($t->audio_original_name ?: ($t->audio_path ? basename($t->audio_path) : '')),
                'pending' => in_array((int) $t->id, $pending, true),
            ])->values();

        return response()->json(['tracks' => $tracks, 'max_mb' => (int) (class_exists(PlatformRules::class) && method_exists(PlatformRules::class, 'audioMaxMb') ? PlatformRules::audioMaxMb() : 300)]);
    }

    /** Super Admin: replace now */
    public function replace(Request $request, Track $track, PermissionService $permissions): RedirectResponse
    {
        abort_unless($this->role($request, $permissions) === 'super_admin', 403, 'Only a Super Admin can replace a track file. A Manager can request it.');
        $data = $request->validate($this->wavRules(), ['audio.mimes' => 'Only WAV files can be uploaded.']);
        $release = Release::query()->findOrFail($track->release_id);

        $file = $request->file('audio');
        $old = (string) $track->audio_path;
        $path = $file->store('tracks/audio-replaced', 'public');
        $this->applyFile($track, $path, (string) $file->getClientOriginalName(), (int) $file->getSize());

        $now = now();
        DB::table('audio_change_requests')->insert([
            'release_id' => $release->id, 'track_id' => $track->id, 'proposed_path' => $path, 'previous_path' => $old ?: null,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255), 'size_bytes' => (int) $file->getSize(),
            'reason' => mb_substr(trim($data['reason']), 0, 1000), 'status' => 'done',
            'requested_by' => $request->user()->id, 'reviewed_by' => $request->user()->id, 'reviewed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        FinanceAudit::log('track.audio_replaced', 'WAV of "'.$track->title.'" (release "'.$release->title.'") replaced by '.$request->user()->name.': '.$data['reason'].'. Old file kept on disk.', [
            'release_id' => $release->id, 'track_id' => $track->id, 'old' => $old, 'new' => $path,
        ]);

        return back()->with('success', 'The WAV of "'.$track->title.'" is replaced. Audio QC runs again on the new file; nothing is re-sent to the stores by itself.');
    }

    /** Manager (or Super Admin on someone's behalf): ask for a replacement */
    public function request(Request $request, Track $track, PermissionService $permissions, ReleaseAccessService $access): RedirectResponse
    {
        $role = $this->role($request, $permissions);
        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Labels and artists cannot change a track file - please write to your Manager.');
        $release = Release::query()->findOrFail($track->release_id);
        abort_unless($access->canAccess($request->user(), $release), 403, 'This release is not in your assigned labels / artists.');
        if (DB::table('audio_change_requests')->where('track_id', $track->id)->where('status', 'pending')->exists()) {
            return back()->withErrors(['audio' => 'A WAV change for this track is already waiting for a Super Admin.']);
        }
        $data = $request->validate($this->wavRules(), ['audio.mimes' => 'Only WAV files can be uploaded.']);

        $file = $request->file('audio');
        $path = $file->store('tracks/audio-pending', 'public');
        $now = now();
        $rowId = DB::table('audio_change_requests')->insertGetId([
            'release_id' => $release->id, 'track_id' => $track->id, 'proposed_path' => $path, 'previous_path' => $track->audio_path,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255), 'size_bytes' => (int) $file->getSize(),
            'reason' => mb_substr(trim($data['reason']), 0, 1000), 'status' => 'pending',
            'requested_by' => $request->user()->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $what = 'replace the WAV of "'.$track->title.'" in "'.$release->title.'" ('.($release->upc ?: $release->catalog_number).') - asked by '.$request->user()->name.': '.$data['reason'].' [listen: /admin/audio-requests/'.$rowId.'/listen]';
        $approvalId = DB::table('pending_approvals')->insertGetId([
            'scope' => self::scope($rowId), 'what' => mb_substr($what, 0, 2000), 'url' => '/admin/audio-requests/'.$rowId.'/listen',
            'requested_by' => $request->user()->id, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('audio_change_requests')->where('id', $rowId)->update(['approval_id' => $approvalId]);
        FinanceAudit::log('track.audio_change_requested', $request->user()->name.' asked to replace the WAV of "'.$track->title.'" ("'.$release->title.'"): '.$data['reason'], ['release_id' => $release->id, 'track_id' => $track->id, 'approval_id' => $approvalId]);
        try {
            $alerts = app(StaffAlerts::class);
            $alerts->bell($alerts->superAdmins(), 'audio.change_requested', 'WAV change request', $request->user()->name.' asks to replace the WAV of "'.$track->title.'" ('.$release->title.'): '.$data['reason'], '/admin/approvals', 'warning');
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', 'WAV change request sent. A Super Admin listens to it and decides under Administration → Approvals; you get a bell either way.');
    }

    /** Super Admin: play the proposed file (also the "url" of the approval row) */
    public function listen(Request $request, int $row, PermissionService $permissions): StreamedResponse
    {
        abort_unless($this->role($request, $permissions) === 'super_admin', 403);
        $r = DB::table('audio_change_requests')->where('id', $row)->first();
        abort_unless($r && $r->proposed_path && Storage::disk('public')->exists($r->proposed_path), 404, 'The proposed file is no longer there.');
        $name = $r->original_name ?: basename($r->proposed_path);

        return Storage::disk('public')->response($r->proposed_path, $name, ['Content-Type' => 'audio/wav', 'Content-Disposition' => 'inline; filename="'.addslashes($name).'"']);
    }

    /** called from Approvals when a Super Admin decides a track.audio:* request; returns the flash text */
    public function decide(object $approvalRow, User $superAdmin, bool $approve, ?string $note): string
    {
        $rowId = (int) substr((string) $approvalRow->scope, strlen(self::SCOPE));
        $r = DB::table('audio_change_requests')->where('id', $rowId)->first();
        if (!$r) {
            return 'That WAV request no longer exists.';
        }
        if ($r->status !== 'pending') {
            return 'That WAV request was already '.$r->status.'.';
        }
        $track = Track::query()->find($r->track_id);
        $now = now();

        if (!$approve || !$track) {
            DB::table('audio_change_requests')->where('id', $r->id)->update(['status' => 'rejected', 'reviewed_by' => $superAdmin->id, 'reviewed_at' => $now, 'note' => $note ?: ($track ? null : 'Track no longer exists'), 'updated_at' => $now]);
            try {
                if ($r->proposed_path && Storage::disk('public')->exists($r->proposed_path)) {
                    Storage::disk('public')->delete($r->proposed_path); /* a rejected upload is just an upload - the live file was never touched */
                }
            } catch (Throwable $e) {
                report($e);
            }
            FinanceAudit::log('track.audio_change_rejected', 'WAV change for track #'.$r->track_id.' rejected by '.$superAdmin->name.($note ? ': '.$note : ''), ['track_id' => $r->track_id, 'release_id' => $r->release_id]);

            return 'Not approved - the current WAV stays. The requester has been told.';
        }

        if (!Storage::disk('public')->exists($r->proposed_path)) {
            DB::table('audio_change_requests')->where('id', $r->id)->update(['status' => 'rejected', 'reviewed_by' => $superAdmin->id, 'reviewed_at' => $now, 'note' => 'Proposed file missing', 'updated_at' => $now]);

            return 'The uploaded WAV is missing on the server - ask the Manager to upload it again.';
        }
        $this->applyFile($track, $r->proposed_path, (string) $r->original_name, (int) $r->size_bytes);
        DB::table('audio_change_requests')->where('id', $r->id)->update(['status' => 'done', 'previous_path' => $r->previous_path, 'reviewed_by' => $superAdmin->id, 'reviewed_at' => $now, 'note' => $note, 'updated_at' => $now]);
        DB::table('pending_approvals')->where('id', $approvalRow->id)->update(['used_at' => $now, 'used_by' => $superAdmin->id, 'updated_at' => $now]);
        FinanceAudit::log('track.audio_replaced', 'WAV of "'.$track->title.'" replaced on approval by '.$superAdmin->name.($note ? ' - '.$note : '').'. Reason given: '.$r->reason.'. Old file kept on disk.', ['track_id' => $track->id, 'release_id' => $r->release_id, 'old' => $r->previous_path, 'new' => $r->proposed_path]);

        return 'Approved - the WAV of "'.$track->title.'" is replaced. Audio QC runs again on it; nothing is re-sent to the stores by itself.';
    }

    /** point the track at the new file; QC fields reset so validation runs again */
    private function applyFile(Track $track, string $path, string $originalName, int $size): void
    {
        $set = ['audio_path' => $path];
        $cols = [
            'audio_original_name' => mb_substr($originalName, 0, 255), 'audio_size_bytes' => $size, 'audio_mime_type' => 'audio/wav',
            'audio_validation_status' => 'pending', 'audio_validation_errors' => null, 'audio_validated_at' => null, 'audio_validated_by' => null,
            'audio_duration_seconds' => null, 'audio_sample_rate' => null, 'audio_bit_depth' => null, 'audio_channels' => null,
        ];
        foreach ($cols as $c => $v) {
            if (Schema::hasColumn('tracks', $c)) {
                $set[$c] = $v;
            }
        }
        $track->forceFill($set)->saveQuietly();
    }
}
