<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reports\ReportImport;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $request->user()
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
}
