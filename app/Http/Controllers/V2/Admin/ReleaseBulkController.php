<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseTrash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * W208 - bulk actions on releases (Super Admin only).
 * One confirmation code (ActionOtpGuard) covers the whole selection; every single
 * release still goes through ReleaseTrash::trash()/restore()/purge(), so each one
 * keeps its own audit line, code release and file clean-up.
 */
class ReleaseBulkController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can do bulk actions on releases.');
    }

    /** @return int[] */
    private function ids(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        return array_values(array_unique(array_map('intval', $data['ids'])));
    }

    /** the same selection always gives the same scope, so "enter the code, press the button again" works */
    private function scope(string $action, array $ids): string
    {
        sort($ids);

        return 'release.bulk-'.$action.':'.substr(sha1(implode(',', $ids)), 0, 20);
    }

    private function plural(int $n): string
    {
        return $n.' release'.($n === 1 ? '' : 's');
    }

    /** W219: selected releases -> live, one code (each one goes through the W218 one-click live) */
    public function goLive(Request $request, PermissionService $permissions, \App\Services\V2\ReleaseGoLive $live): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $ids = $this->ids($request);

        $releases = Release::query()->whereIn('id', $ids)->orderBy('id')->get();
        $ok = [];
        $skipped = [];
        foreach ($releases as $release) {
            $why = $live->blockers($release);
            if ($why === []) {
                $ok[] = $release;
            } else {
                $skipped[] = '"'.$release->title.'" ('.implode(' ', $why).')';
            }
        }
        if (!$ok) {
            return back()->with('error', 'None of the selected releases can be marked live. '.implode('; ', $skipped));
        }

        app(ActionOtpGuard::class)->require(
            $request->user(),
            $this->scope('go-live', array_map(fn ($r) => (int) $r->id, $ok)),
            'mark '.$this->plural(count($ok)).' live in all stores'
        );

        @set_time_limit(300); /* W220: up to 100 releases in one go */
        $done = 0;
        $failed = [];
        foreach ($ok as $release) {
            try {
                $live->goLive($release->fresh(), $request->user(), 'Marked live by '.$request->user()->name.' (bulk one-click live).');
                $done++;
            } catch (ValidationException $e) {
                $failed[] = '"'.$release->title.'" ('.implode(' ', Arr::flatten($e->errors())).')';
            } catch (Throwable $e) {
                report($e);
                $failed[] = '"'.$release->title.'"';
            }
        }

        $msg = $this->plural($done).' marked live - the owners got a bell.';
        if ($skipped) {
            $msg .= ' Skipped: '.implode('; ', $skipped).'.';
        }
        if ($failed) {
            $msg .= ' Failed: '.implode(', ', $failed).'.';
        }

        return back()->with($done ? 'success' : 'error', $msg);
    }

    /** selected live releases -> Trash */
    public function trash(Request $request, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $ids = $this->ids($request);

        $releases = Release::query()->whereIn('id', $ids)->get();
        $ok = [];
        $skipped = [];
        foreach ($releases as $release) {
            try {
                $trash->refuseMoney($release); /* before the code is asked: say at once what cannot be deleted */
                $ok[] = $release;
            } catch (ValidationException $e) {
                $skipped[] = '"'.$release->title.'" ('.implode(' ', Arr::flatten($e->errors())).')';
            } catch (Throwable $e) {
                $skipped[] = '"'.$release->title.'" ('.$e->getMessage().')';
            }
        }
        if (!$ok) {
            return back()->with('error', 'None of the selected releases can be moved to the Trash. '.implode('; ', $skipped));
        }

        app(ActionOtpGuard::class)->require(
            $request->user(),
            $this->scope('trash', array_map(fn ($r) => (int) $r->id, $ok)),
            'move '.$this->plural(count($ok)).' to the Trash'
        );

        $done = 0;
        $failed = [];
        foreach ($ok as $release) {
            try {
                $trash->trash($release, $request->user(), 'Bulk delete by Super Admin');
                $done++;
            } catch (Throwable $e) {
                report($e);
                $failed[] = '"'.$release->title.'"';
            }
        }

        $msg = $this->plural($done).' moved to the Trash. Removed for good after '.ReleaseTrash::KEEP_DAYS.' days unless restored.';
        if ($skipped) {
            $msg .= ' Skipped: '.implode('; ', $skipped).'.';
        }
        if ($failed) {
            $msg .= ' Failed: '.implode(', ', $failed).'.';
        }

        return redirect('/admin/release-trash')->with('success', $msg);
    }

    /** selected Trash rows -> back to the catalogue (no code, same as a single Restore) */
    public function restore(Request $request, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $ids = $this->ids($request);

        $done = 0;
        $failed = [];
        foreach ($ids as $row) {
            try {
                $release = $trash->restore($row, $request->user());
                $done++;
            } catch (ValidationException $e) {
                $failed[] = '#'.$row.' ('.implode(' ', Arr::flatten($e->errors())).')';
            } catch (Throwable $e) {
                report($e);
                $failed[] = '#'.$row;
            }
        }

        $msg = $this->plural($done).' restored, with tracks and catalogue rows.';
        if ($failed) {
            $msg .= ' Not restored: '.implode(', ', $failed).'.';
        }

        return back()->with($done ? 'success' : 'error', $msg);
    }

    /** selected Trash rows -> deleted for good (one code for the whole selection) */
    public function purge(Request $request, PermissionService $permissions, ReleaseTrash $trash): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $ids = $this->ids($request);

        $rows = DB::table('release_trash')->whereIn('id', $ids)->get();
        if ($rows->isEmpty()) {
            return back()->with('error', 'Nothing selected is in the Trash any more.');
        }

        app(ActionOtpGuard::class)->require(
            $request->user(),
            $this->scope('purge', $rows->pluck('id')->map(fn ($i) => (int) $i)->all()),
            'delete '.$this->plural($rows->count()).' FOR GOOD (cannot be undone)'
        );

        $done = 0;
        $failed = [];
        foreach ($rows as $row) {
            try {
                $trash->purge((int) $row->id, $request->user());
                $done++;
            } catch (ValidationException $e) {
                $failed[] = '"'.$row->title.'" ('.implode(' ', Arr::flatten($e->errors())).')';
            } catch (Throwable $e) {
                report($e);
                $failed[] = '"'.$row->title.'"';
            }
        }

        $msg = $this->plural($done).' deleted for good - nothing of them is left.';
        if ($failed) {
            $msg .= ' Not deleted: '.implode('; ', $failed).'.';
        }

        return back()->with($done ? 'success' : 'error', $msg);
    }
}
