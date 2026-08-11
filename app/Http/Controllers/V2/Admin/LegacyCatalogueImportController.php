<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportLegacyCatalogueJob;
use App\Models\Catalogue\LegacyCatalogueImport;
use App\Services\V2\LegacyCatalogueImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegacyCatalogueImportController extends Controller
{
    public function index(): Response
    {
        $imports = LegacyCatalogueImport::query()
            ->latest('id')
            ->limit(50)
            ->get([
                'id',
                'public_id',
                'original_filename',
                'status',
                'total_rows',
                'ready_rows',
                'blocked_rows',
                'warning_rows',
                'imported_rows',
                'failed_rows',
                'uploaded_by',
                'validated_at',
                'created_at',
            ]);

        return Inertia::render(
            'V2/Admin/LegacyCatalogueImports/Index',
            [
                'imports' => $imports,
            ]
        );
    }

    public function store(
        Request $request,
        LegacyCatalogueImportService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'catalogue_file' => [
                'required',
                'file',
                'max:51200',
                'mimes:csv,txt,xlsx,xls',
            ],
        ]);

        $import = $service->stage(
            $validated['catalogue_file'],
            $request->user()
        );

        return redirect()
            ->route(
                'single.super-admin.legacy-catalogue-imports.show',
                $import
            )
            ->with(
                'success',
                'Catalogue file uploaded and validated successfully.'
            );
    }

    public function dryRun(
        Request $request,
        LegacyCatalogueImport $legacyCatalogueImport,
        LegacyCatalogueImportService $service
    ): RedirectResponse {
        $result = $service->dryRunImport(
            $legacyCatalogueImport
        );

        return back()->with(
            'legacy_dry_run',
            $result
        );
    }

    public function approveEntities(
        Request $request,
        LegacyCatalogueImport $legacyCatalogueImport,
        LegacyCatalogueImportService $service
    ): RedirectResponse {
        $result = $service->approveMissingEntities(
            $legacyCatalogueImport,
            $request->user()
        );

        return back()->with(
            'success',
            sprintf(
                'Missing entities approved. Labels created: %d, Artists created: %d.',
                $result['created_labels'],
                $result['created_artists']
            )
        );
    }

    public function import(
        LegacyCatalogueImport $legacyCatalogueImport,
        LegacyCatalogueImportService $service
    ) {
        if ($legacyCatalogueImport->status !== 'validated') {
            return back()->with(
                'error',
                'Only a validated catalogue import can be imported.'
            );
        }

        if ((int) $legacyCatalogueImport->blocked_rows > 0) {
            return back()->with(
                'error',
                'Import stopped because blocked rows exist.'
            );
        }

        if ((int) $legacyCatalogueImport->imported_rows > 0) {
            return back()->with(
                'error',
                'This catalogue has already been imported.'
            );
        }

        try {
            $claimed = \Illuminate\Support\Facades\DB::transaction(
                function () use ($legacyCatalogueImport) {
                    $locked =
                        LegacyCatalogueImport::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $legacyCatalogueImport->id
                            );

                    if ($locked->status !== 'validated') {
                        return false;
                    }

                    if ((int) $locked->blocked_rows > 0) {
                        return false;
                    }

                    if ((int) $locked->imported_rows > 0) {
                        return false;
                    }

                    $locked->forceFill([
                        'status' => 'queued',
                        'failure_message' => null,
                    ])->save();

                    return true;
                }
            );

            if (!$claimed) {
                return back()->with(
                    'error',
                    'This catalogue import is no longer '
                    .'available to queue.'
                );
            }

            try {
                ImportLegacyCatalogueJob::dispatch(
                    (int) $legacyCatalogueImport->id,
                    (int) request()->user()->id
                );
            } catch (\Throwable $dispatchException) {
                LegacyCatalogueImport::query()
                    ->whereKey($legacyCatalogueImport->id)
                    ->where('status', 'queued')
                    ->update([
                        'status' => 'validated',
                        'failure_message' =>
                            mb_substr(
                                $dispatchException->getMessage(),
                                0,
                                5000
                            ),
                    ]);

                throw $dispatchException;
            }

            return redirect()
                ->route(
                    'single.super-admin.legacy-catalogue-imports.show',
                    $legacyCatalogueImport
                )
                ->with(
                    'success',
                    'Catalogue import queued successfully. '
                    .'It will continue in the background.'
                );

        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Catalogue import could not be queued: '
                .$e->getMessage()
            );
        }
    }

    public function show(
        LegacyCatalogueImport $legacyCatalogueImport
    ): Response {
        $legacyCatalogueImport->load([
            'rows' => function ($query) {
                $query
                    ->orderBy('row_number')
                    ->limit(500);
            },
        ]);

        return Inertia::render(
            'V2/Admin/LegacyCatalogueImports/Show',
            [
                'catalogueImport' => $legacyCatalogueImport,

                'dryRun' =>
                    session(
                        'legacy_dry_run'
                    ),
            ]
        );
    }
}
