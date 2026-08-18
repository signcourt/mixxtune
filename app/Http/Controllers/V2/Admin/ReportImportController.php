<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\ReportImport;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReportImportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        return Inertia::render(
            'V2/Admin/Reports/Imports',
            [
                'role' => $role,

                'imports' =>
                    ReportImport::query()
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions,
        ReportImportService $importer
    ): RedirectResponse|JsonResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = validator(
            $request->all(),
            [
                'reporting_month' => [
                    'required',
                    'date_format:Y-m',
                ],

                'report_file' => [
                    'required',
                    'file',
                    'mimes:csv,txt',
                    'max:512000',
                ],
            ]
        )->validate();

        $import = $importer->import(
            $validated['report_file'],
            $request->user(),
            $validated['reporting_month']
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Report import completed.",
                'imported_rows' => $import->imported_rows,
                'duplicate_rows' => $import->duplicate_rows,
                'failed_rows' => $import->failed_rows,
            ]);
        }

        return back()->with(
            'success',
            "Report import completed. Imported: {$import->imported_rows}, duplicates: {$import->duplicate_rows}, failed: {$import->failed_rows}."
        );
    }

    public function update(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = validator(
            $request->all(),
            [
                'reporting_month' => [
                    'required',
                    'date_format:Y-m',
                ],
            ]
        )->validate();

        DB::transaction(
            function () use ($reportImport, $validated): void {
                $reportImport->update([
                    'reporting_month' =>
                        $validated['reporting_month'],
                ]);

                DB::table('report_rows')
                    ->where(
                        'report_import_id',
                        $reportImport->id
                    )
                    ->update([
                        'reporting_month' =>
                            $validated['reporting_month'],
                        'updated_at' => now(),
                    ]);
            }
        );

        return back()->with(
            'success',
            'Reporting month updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $hasRoyaltyAllocations =
            DB::table('royalty_allocations')
                ->join(
                    'report_rows',
                    'report_rows.id',
                    '=',
                    'royalty_allocations.report_row_id'
                )
                ->where(
                    'report_rows.report_import_id',
                    $reportImport->id
                )
                ->exists();

        if ($hasRoyaltyAllocations) {
            return back()->withErrors([
                'delete' =>
                    'This report cannot be deleted because royalty allocations already exist.',
            ]);
        }

        $storedPath = $reportImport->stored_path;
        $errorPath = $reportImport->error_file_path;

        DB::transaction(
            function () use ($reportImport): void {
                $reportImport->delete();
            }
        );

        if ($storedPath) {
            Storage::disk('local')->delete($storedPath);
        }

        if ($errorPath) {
            Storage::disk('local')->delete($errorPath);
        }

        return back()->with(
            'success',
            'Report deleted successfully.'
        );
    }


    public function download(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ) {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        abort_if(
            empty($reportImport->stored_path),
            404,
            'Report file not found.'
        );

        abort_unless(
            Storage::disk('local')->exists(
                $reportImport->stored_path
            ),
            404,
            'Report file not found.'
        );

        $extension = pathinfo(
            $reportImport->stored_path,
            PATHINFO_EXTENSION
        );

        $downloadName =
            trim(
                (string) $reportImport->original_filename
            );

        if ($downloadName === '') {
            $downloadName =
                'report-'.$reportImport->id.
                ($extension ? '.'.$extension : '');
        }

        return Storage::disk('local')->download(
            $reportImport->stored_path,
            $downloadName
        );
    }

}
