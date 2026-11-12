<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsUpload;
use App\Models\Iprs\IprsWork;
use App\Models\User;
use App\Services\Iprs\IprsImportService;
use App\Services\Iprs\IprsMappingService;
use App\Services\Iprs\IprsPublishService;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/*
 * IPRS Publishing Royalty - Phase 1 admin screens.
 * Super Admin / admin uploads one or many IPRS statement files; each is parsed,
 * validated and stored. Uploading is a major operation, so it is e-mail-OTP
 * gated like report approval / publish.
 */
class IprsImportController extends Controller
{
    public function index(Request $request, PermissionService $permissions): Response
    {
        $role = $this->gate($request, $permissions);

        $uploads = IprsUpload::query()
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (IprsUpload $u) => $this->serialize($u));

        return Inertia::render('V2/Admin/Iprs/Uploads', [
            'role' => $role,
            'uploads' => $uploads,
        ]);
    }

    public function show(Request $request, PermissionService $permissions, IprsUpload $iprsUpload): Response
    {
        $role = $this->gate($request, $permissions);

        $lines = $iprsUpload->lines()->orderBy('work_int_no')->orderBy('source_month')->get();

        $workMeta = IprsWork::whereIn('work_int_no', $lines->pluck('work_int_no')->unique()->all())
            ->get()
            ->keyBy('work_int_no');

        $trackMeta = Track::whereIn('id', $lines->pluck('track_id')->filter()->unique()->all())
            ->get(['id', 'title', 'isrc'])
            ->keyBy('id');
        $clients = User::whereIn('id', $lines->pluck('client_id')->filter()->unique()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $works = $lines->groupBy('work_int_no')->map(function ($group, $workNo) use ($workMeta, $trackMeta, $clients) {
            $meta = $workMeta->get($workNo);
            $first = $group->first();
            $track = $first->track_id ? $trackMeta->get($first->track_id) : null;
            $client = $first->client_id ? $clients->get($first->client_id) : null;

            return [
                'work_int_no' => (string) $workNo,
                'title' => $meta->title ?? $first->title,
                'language' => $meta->language ?? null,
                'credits' => $meta->credits ?? [],
                'total_amount' => round((float) $group->sum('amount'), 4),
                'mapping_status' => $first->mapping_status,
                'mapped_track' => $track ? ['title' => $track->title, 'isrc' => $track->isrc] : null,
                'owner_name' => $client->name ?? null,
                'lines' => $group->map(fn ($l) => [
                    'id' => $l->id,
                    'source' => $l->source,
                    'source_month' => $l->source_month,
                    'pool' => $l->pool,
                    'own_pct' => $l->own_pct !== null ? (float) $l->own_pct : null,
                    'coll_pct' => $l->coll_pct !== null ? (float) $l->coll_pct : null,
                    'amount' => (float) $l->amount,
                ])->values(),
            ];
        })->values();

        return Inertia::render('V2/Admin/Iprs/Show', [
            'role' => $role,
            'upload' => $this->serialize($iprsUpload),
            'works' => $works,
        ]);
    }

    /** Search the catalogue by song title or ISRC for the mapping picker. */
    public function searchTracks(Request $request, PermissionService $permissions, IprsMappingService $mapping): \Illuminate\Http\JsonResponse
    {
        $this->gate($request, $permissions);

        $q = (string) $request->query('q', '');

        return response()->json([
            'tracks' => mb_strlen(trim($q)) >= 2 ? $mapping->searchTracks($q) : [],
        ]);
    }

    /** Map a work to a track: stores the work number on the track and matches its lines. */
    public function mapWork(Request $request, PermissionService $permissions, IprsMappingService $mapping, IprsUpload $iprsUpload): RedirectResponse
    {
        $this->gate($request, $permissions);

        $data = $request->validate([
            'work_int_no' => ['required', 'string', 'max:40'],
            'track_id' => ['required', 'integer'],
        ]);

        $result = $mapping->mapWork($data['work_int_no'], (int) $data['track_id'], $request->user()->id);

        return back()->with('flash', [
            'success' => 'Mapped to "'.$result['track_title'].'"'.($result['isrc'] ? ' ('.$result['isrc'].')' : '').' — '.$result['matched_lines'].' line(s) matched.',
        ]);
    }

    /** Remove a work's mapping. */
    public function unmapWork(Request $request, PermissionService $permissions, IprsMappingService $mapping, IprsUpload $iprsUpload): RedirectResponse
    {
        $this->gate($request, $permissions);

        $data = $request->validate(['work_int_no' => ['required', 'string', 'max:40']]);

        $mapping->unmapWork($data['work_int_no']);

        return back()->with('flash', ['success' => 'Mapping removed.']);
    }

    /** Publish mapped royalty to owners' wallets (OTP-gated, like report approval). */
    public function publish(Request $request, PermissionService $permissions, IprsPublishService $publish, IprsUpload $iprsUpload): RedirectResponse
    {
        $this->gate($request, $permissions);

        app(ActionOtpGuard::class)->require($request->user(), 'iprs.publish', 'publish IPRS royalty to owners');

        $r = $publish->publish($iprsUpload, $request->user()->id);

        $parts = [$r['published'].' line(s) credited ₹'.number_format($r['credited'], 2)];
        if ($r['already']) {
            $parts[] = $r['already'].' already published';
        }
        if ($r['skipped_unmatched']) {
            $parts[] = $r['skipped_unmatched'].' unmatched skipped';
        }
        if ($r['skipped_no_owner']) {
            $parts[] = $r['skipped_no_owner'].' without owner skipped';
        }

        return back()->with('flash', ['success' => 'Published to owners: '.implode(', ', $parts).'.']);
    }

    public function store(Request $request, PermissionService $permissions, IprsImportService $service): RedirectResponse
    {
        $this->gate($request, $permissions);

        $validated = $request->validate([
            'report_files' => ['required', 'array', 'min:1', 'max:30'],
            'report_files.*' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:51200'],
            'period_label' => ['nullable', 'string', 'max:80'],
        ]);

        // Major operation - require the e-mailed confirmation code first.
        app(ActionOtpGuard::class)->require(
            $request->user(),
            'iprs.upload',
            'upload '.count($validated['report_files']).' IPRS royalty statement(s)'
        );

        $results = [];
        foreach ($validated['report_files'] as $file) {
            $results[] = $service->import($file, $request->user()->id, $validated['period_label'] ?? null);
        }

        $imported = count(array_filter($results, fn ($r) => $r['status'] === 'imported'));
        $review = count(array_filter($results, fn ($r) => $r['status'] === 'needs_review'));
        $dupe = count(array_filter($results, fn ($r) => $r['status'] === 'duplicate'));
        $failed = count(array_filter($results, fn ($r) => $r['status'] === 'failed'));

        $summary = [];
        if ($imported) {
            $summary[] = $imported.' imported';
        }
        if ($review) {
            $summary[] = $review.' need review';
        }
        if ($dupe) {
            $summary[] = $dupe.' duplicate skipped';
        }
        if ($failed) {
            $summary[] = $failed.' failed';
        }

        return back()->with('flash', [
            'success' => $summary ? ('IPRS upload: '.implode(', ', $summary).'.') : null,
            'iprs_results' => array_map(fn ($r) => [
                'file_name' => $r['file_name'],
                'status' => $r['status'],
                'message' => $r['message'],
            ], $results),
        ]);
    }

    public function destroy(Request $request, PermissionService $permissions, IprsUpload $iprsUpload): RedirectResponse
    {
        $this->gate($request, $permissions);

        abort_if(
            in_array($iprsUpload->status, ['distributed', 'reversed'], true),
            422,
            'This statement has been distributed to clients and cannot be deleted. Reverse it first.'
        );

        if ($iprsUpload->stored_path) {
            Storage::disk('local')->delete($iprsUpload->stored_path);
        }

        $iprsUpload->delete(); // cascades to lines

        return back()->with('flash', ['success' => 'IPRS statement deleted.']);
    }

    private function gate(Request $request, PermissionService $permissions): string
    {
        $role = $permissions->role($request->user());

        abort_unless(
            in_array($role, ['admin', 'super_admin'], true),
            403,
            'Admin access required.'
        );

        return $role;
    }

    private function serialize(IprsUpload $u): array
    {
        $validation = $u->metadata['validation'] ?? [];

        return [
            'id' => $u->id,
            'public_id' => $u->public_id,
            'file_name' => $u->file_name,
            'member_name' => $u->member_name,
            'member_internal_no' => $u->member_internal_no,
            'ipi_name_no' => $u->ipi_name_no,
            'ipi_base_no' => $u->ipi_base_no,
            'file_total' => (float) $u->file_total,
            'parsed_total' => (float) $u->parsed_total,
            'within_tolerance' => (bool) ($validation['within_tolerance'] ?? false),
            'difference' => $validation['difference'] ?? null,
            'total_lines' => (int) $u->total_lines,
            'total_works' => (int) $u->total_works,
            'status' => $u->status,
            'period_label' => $u->period_label,
            'published' => $u->metadata['published'] ?? null,
            'created_at' => optional($u->created_at)->toIso8601String(),
        ];
    }
}
