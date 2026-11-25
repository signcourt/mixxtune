<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\PosterChangeRequest;
use App\Models\Distribution\Release;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/*
 * W195 MIXXTUNE_POSTER_CHANGE_REQUEST: a label/artist asks to change a release poster.
 * Their upload does NOT go live - it becomes a pending request that a super-admin (all
 * releases) or an admin (only their assigned labels) approves or rejects. One pending
 * request per release; while one is pending the owner cannot upload another.
 * Super-admin/admin own direct edit (W194) is unchanged and still immediate.
 */
class PosterChangeRequestController extends Controller
{
    /* Owner (label/artist) submits a new poster for review. */
    public function store(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        PanelNotificationService $notify
    ) {
        // Owner must be allowed to view/manage this release.
        $access->authorizeView($request->user(), $release);

        // One pending request per release.
        $existing = PosterChangeRequest::query()
            ->where('release_id', $release->id)
            ->where('status', 'pending')
            ->exists();
        if ($existing) {
            return back()->withErrors([
                'artwork' => 'A poster change is already waiting for approval on this release.',
            ]);
        }

        // Same square-only rule as the Super Admin poster (W194d).
        $request->validate([
            'artwork' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png',
                'max:20480',
                function ($attribute, $value, $fail) {
                    $size = @getimagesize($value->getPathname());
                    if ($size === false) {
                        $fail('The poster could not be read as an image.');
                        return;
                    }
                    [$w, $h] = $size;
                    $ok = ($w === 3000 && $h === 3000)
                        || ($w === 1500 && $h === 1500)
                        || ($w === $h);
                    if (! $ok) {
                        $fail('The poster must be square - 3000x3000 or 1500x1500. Yours is ' . $w . 'x' . $h . '.');
                    }
                },
            ],
        ]);

        $path = $request->file('artwork')->store('releases/artwork', 'public');

        PosterChangeRequest::query()->create([
            'release_id' => $release->id,
            'proposed_path' => $path,
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        // Notify the reviewers (super-admins and the admins assigned to this label).
        foreach ($this->reviewersFor($release) as $reviewer) {
            $notify->send(
                $reviewer,
                'poster.change_requested',
                'Poster change requested',
                ($request->user()->name ?? 'A user') . ' requested a poster change for "' . $release->title . '".',
                '/admin/poster-requests'
            );
        }

        return back()->with('success', 'Poster change sent for approval.');
    }

    /* Super-admin (all) / admin (their assigned labels) see pending requests. */
    public function index(
        Request $request,
        PermissionService $permissions,
        AdminAssignmentService $assignments
    ): Response {
        $role = $permissions->role($request->user());
        abort_unless(in_array($role, ['super_admin', 'admin'], true), 403);

        $labelIds = $assignments->labelIds($request->user());

        $query = PosterChangeRequest::query()
            ->where('status', 'pending')
            ->with(['release', 'requester']);

        // super_admin sees all; admin is limited to releases of their assigned labels.
        if ($role !== 'super_admin') {
            $query->whereHas('release', function ($q) use ($labelIds) {
                $q->whereIn('label_id', $labelIds);
            });
        }

        $requests = $query->orderByDesc('id')->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'release_id' => $r->release_id,
                'release_title' => $r->release?->title,
                'current_path' => $r->release?->artwork_path,
                'proposed_path' => $r->proposed_path,
                'requested_by' => $r->requester?->name,
                'created_at' => $r->created_at?->toDateTimeString(),
            ];
        });

        return Inertia::render('V2/Admin/PosterRequests/Index', [
            'role' => $role,
            'requests' => $requests,
        ]);
    }

    /* Approve: the proposed poster replaces the live one; the old file is removed. */
    public function approve(
        Request $request,
        PosterChangeRequest $posterRequest,
        PermissionService $permissions,
        AdminAssignmentService $assignments,
        PanelNotificationService $notify
    ) {
        $this->authorizeReview($request, $posterRequest, $permissions, $assignments);

        abort_unless($posterRequest->status === 'pending', 409, 'Already handled.');

        $release = $posterRequest->release;
        abort_unless($release, 404, 'Release not found.');

        $old = $release->artwork_path;
        $release->artwork_path = $posterRequest->proposed_path;
        $release->save();

        if ($old && $old !== $posterRequest->proposed_path) {
            Storage::disk('public')->delete($old);
        }

        $posterRequest->status = 'approved';
        $posterRequest->reviewed_by = $request->user()->id;
        $posterRequest->reviewed_at = now();
        $posterRequest->save();

        if ($posterRequest->requester) {
            $notify->send(
                $posterRequest->requester,
                'poster.change_approved',
                'Poster change approved',
                'Your poster change for "' . $release->title . '" is now live.',
                '/releases/' . $release->id
            );
        }

        return back()->with('success', 'Poster approved and updated.');
    }

    /* Reject: the request is dropped; the old poster stays; the proposed file is removed. */
    public function reject(
        Request $request,
        PosterChangeRequest $posterRequest,
        PermissionService $permissions,
        AdminAssignmentService $assignments,
        PanelNotificationService $notify
    ) {
        $this->authorizeReview($request, $posterRequest, $permissions, $assignments);

        abort_unless($posterRequest->status === 'pending', 409, 'Already handled.');

        /* W195e: a rejection reason is required and goes to the owner. */
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $reason = trim($validated['reason']);

        if ($posterRequest->proposed_path) {
            Storage::disk('public')->delete($posterRequest->proposed_path);
        }

        $posterRequest->status = 'rejected';
        $posterRequest->reviewed_by = $request->user()->id;
        $posterRequest->reviewed_at = now();
        $posterRequest->save();

        if ($posterRequest->requester) {
            $release = $posterRequest->release;
            $notify->send(
                $posterRequest->requester,
                'poster.change_rejected',
                'Poster change rejected',
                'Your poster change for "' . ($release?->title ?? 'a release') . '" was not approved. Reason: ' . $reason,
                $release ? '/releases/' . $release->id : null
            );
        }

        return back()->with('success', 'Poster change rejected.');
    }

    /* A reviewer is a super-admin, or an admin assigned to this release's label. */
    private function reviewersFor(Release $release): iterable
    {
        $supers = \App\Models\User::query()
            ->where('role', 'super_admin')
            ->get();

        $admins = collect();
        if ($release->label_id) {
            $admins = \App\Models\User::query()
                ->where('role', 'admin')
                ->whereIn('id', function ($q) use ($release) {
                    $q->select('user_id')
                        ->from('admin_label_assignments')
                        ->where('label_id', $release->label_id);
                })
                ->get();
        }

        return $supers; /* W211: Managers no longer review posters */
    }

    private function authorizeReview(
        Request $request,
        PosterChangeRequest $posterRequest,
        PermissionService $permissions,
        AdminAssignmentService $assignments
    ): void {
        $role = $permissions->role($request->user());
        /* W211: the final word on a poster is the Super Admin's - a Manager only requests */

        abort_unless($role === 'super_admin', 403, 'Only a Super Admin can approve or reject a poster change.');

        if ($role === 'super_admin') {

            return;

        }

        // Admin may only act on requests for releases of their assigned labels.
        $labelId = $posterRequest->release?->label_id;
        $labelIds = $assignments->labelIds($request->user());
        abort_unless($labelId && $labelIds->contains($labelId), 403, 'Not your label.');
    }
}
