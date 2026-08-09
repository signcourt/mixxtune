<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\RevenueImport;
use App\Services\Revenue\RevenueWorkbookParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RevenueImportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $imports = RevenueImport::query()
            ->with('importer:id,name,email')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('dsp_name', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($status, [
                    'uploaded',
                    'processing',
                    'completed',
                    'failed',
                    'cancelled',
                ], true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total_imports' => RevenueImport::count(),
            'processing' => RevenueImport::where('status', 'processing')->count(),
            'completed' => RevenueImport::where('status', 'completed')->count(),
            'failed' => RevenueImport::where('status', 'failed')->count(),
            'total_rows' => RevenueImport::sum('total_rows'),
            'net_revenue' => RevenueImport::sum('net_revenue'),
        ];

        return Inertia::render('Admin/RevenueImports/Index', [
            'imports' => $imports,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dsp_name' => [
                'required',
                'string',
                'max:120',
            ],
            'statement_month' => [
                'required',
                'date_format:Y-m',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
            'report_file' => [
                'required',
                'file',
                'max:102400',
                'mimes:csv,txt,xlsx,xls',
            ],
        ]);

        $file = $request->file('report_file');
        $fileHash = hash_file('sha256', $file->getRealPath());

        $duplicate = RevenueImport::query()
            ->where('file_hash', $fileHash)
            ->whereIn('status', [
                'uploaded',
                'processing',
                'completed',
            ])
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'report_file' => 'This exact report file has already been imported.',
            ]);
        }

        $storedPath = $file->store(
            'revenue-imports/' . now()->format('Y/m'),
            'local'
        );

        $totalRows = $this->countRows(
            Storage::disk('local')->path($storedPath)
        );

        $import = RevenueImport::create([
            'dsp_name' => $validated['dsp_name'],
            'statement_month' => $validated['statement_month'] . '-01',
            'currency' => strtoupper($validated['currency']),
            'original_filename' => $file->getClientOriginalName(),
            'stored_file_path' => $storedPath,
            'file_hash' => $fileHash,
            'total_rows' => $totalRows,
            'status' => 'uploaded',
            'imported_by' => auth()->id(),
            'metadata' => [
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ],
        ]);

        return redirect()
            ->route('single.admin.revenue-imports.show', $import)
            ->with('success', 'Revenue report uploaded successfully.');
    }

    public function show(RevenueImport $revenueImport)
    {
        $revenueImport->load('importer:id,name,email');

        return Inertia::render('Admin/RevenueImports/Show', [
            'revenueImport' => $revenueImport,
        ]);
    }


    public function process(
        RevenueImport $revenueImport,
        RevenueWorkbookParser $parser
    ) {
        if ($revenueImport->status === 'processing') {
            return back()->withErrors([
                'process' => 'This import is already processing.',
            ]);
        }

        $parser->parse($revenueImport);

        return redirect()
            ->route('single.admin.revenue-imports.show', $revenueImport)
            ->with('success', 'Revenue report processed successfully.');
    }

    public function destroy(RevenueImport $revenueImport)
    {
        if ($revenueImport->status === 'processing') {
            return back()->withErrors([
                'delete' => 'A processing import cannot be deleted.',
            ]);
        }

        if (
            $revenueImport->stored_file_path &&
            Storage::disk('local')->exists($revenueImport->stored_file_path)
        ) {
            Storage::disk('local')->delete($revenueImport->stored_file_path);
        }

        $revenueImport->delete();

        return redirect()
            ->route('single.admin.revenue-imports.index')
            ->with('success', 'Revenue import deleted.');
    }

    private function countRows(string $filePath): int
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        return max(
            0,
            $worksheet->getHighestDataRow() - 1
        );
    }
}
