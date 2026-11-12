<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Iprs\IprsUpload;
use App\Models\Iprs\IprsWork;
use App\Services\Iprs\IprsImportService;
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

        $works = $lines->groupBy('work_int_no')->map(function ($group, $workNo) use ($workMeta) {
            $meta = $workMeta->get($workNo);

            return [
                'work_int_no' => (string) $workNo,
                'title' => $meta->title ?? $group->first()->title,
                'language' => $meta->language ?? null,
                'credits' => $meta->credits ?? [],
                'total_amount' => round((float) $group->sum('amount'), 4),
                'mapping_status' => $group->first()->mapping_status,
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
            'created_at' => optional($u->created_at)->toIso8601String(),
        ];
    }
}
