<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseTrash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/* W120 MIXXTUNE_RELEASE_TRASH: Super Admin deletes any release -> Trash (90 days) -> Restore / Delete forever */
class ReleaseTrashController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can delete releases.');
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->superAdmin($request, $permissions);
        $rows = DB::table('release_trash as t')->leftJoin('users as u', 'u.id', '=', 't.deleted_by')
            ->orderByDesc('t.trashed_at')->limit(500)->get(['t.*', 'u.name as by'])
            ->map(fn ($t) => [
                'id' => $t->id, 'release_id' => $t->release_id, 'title' => $t->title, 'catalog_number' => $t->catalog_number,
                'upc' => $t->upc, 'label' => $t->label_name, 'artist' => $t->artist_name, 'artwork_path' => $t->artwork_path,
                'status_before' => $t->status_before, 'tracks' => (int) $t->tracks, 'reason' => $t->reason, 'by' => $t->by,
                'at' => Carbon::parse($t->trashed_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'purge_on' => Carbon::parse($t->purge_after)->timezone('Asia/Kolkata')->format('d M Y'),
                'days_left' => max(0, (int) ceil(now()->diffInHours(Carbon::parse($t->purge_after), false) / 24)),
            ]);

        return Inertia::render('V2/Admin/ReleaseTrash/Index', ['role' => 'super_admin', 'rows' => $rows, 'keepDays' => ReleaseTrash::KEEP_DAYS]);
    }

    public function trash(Request $request, Release $release, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $trash->refuseMoney($release); /* before the code is asked: say at once why it cannot be deleted */
        app(ActionOtpGuard::class)->require($request->user(), 'release.trash:'.$release->id, 'delete the release "'.$release->title.'" (to the Trash)');
        $trash->trash($release, $request->user(), $data['reason'] ?? null);

        return redirect('/admin/release-trash')->with('success', 'Release "'.$release->title.'" moved to the Trash. It is removed for good after '.ReleaseTrash::KEEP_DAYS.' days unless you restore it.');
    }

    public function restore(Request $request, int $row, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $release = $trash->restore($row, $request->user());

        return back()->with('success', 'Release "'.$release->title.'" is back, with its tracks and catalogue rows.');
    }

    public function purge(Request $request, int $row, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $t = DB::table('release_trash')->where('id', $row)->first();
        abort_unless($t, 404);
        app(ActionOtpGuard::class)->require($request->user(), 'release.purge:'.$t->release_id, 'delete the release "'.$t->title.'" FOR GOOD (cannot be undone)');
        $title = $trash->purge($row, $request->user());

        return back()->with('success', 'Release "'.$title.'" deleted for good - nothing of it is left.');
    }
}
