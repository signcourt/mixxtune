<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\OwnerStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/* MIXXTUNE_OWNER_STATEMENT_V1: super admin e-mails monthly statements to owners. */
class StatementEmailController extends Controller
{
    private function superAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'super_admin', 403);
    }

    public function index(Request $request, OwnerStatementService $statements): JsonResponse
    {
        $this->superAdmin($request);
        $months = $statements->months();
        $month = (string) $request->query('month', $months[0] ?? '');
        $rows = $month !== '' ? $statements->listForMonth($month) : [];

        return response()->json([
            'months' => $months,
            'month' => $month,
            'pending_imports' => $statements->pendingImports(),
            'month_label' => $month !== '' ? $statements->monthLabel($month) : null,
            'rows' => $rows,
            'counts' => [
                'total' => count($rows),
                'with_email' => count(array_filter($rows, fn ($r) => (bool) $r['email'])),
                'sent' => count(array_filter($rows, fn ($r) => (bool) $r['sent_at'])),
                'to_send' => count(array_filter($rows, fn ($r) => $r['email'] && !$r['sent_at'])),
            ],
        ]);
    }

    public function send(Request $request, OwnerStatementService $statements): JsonResponse
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'month' => ['required', 'string', 'max:20'],
            'statement_ids' => ['nullable', 'array', 'max:50'],
            'statement_ids.*' => ['integer'],
            'force' => ['nullable', 'boolean'],
        ]);

        $ids = $data['statement_ids'] ?? null;
        $force = (bool) ($data['force'] ?? false);

        abort_if($force && !$ids, 422, 'Re-sending is only allowed for chosen statements.');

        return response()->json(['ok' => true] + $statements->sendBatch($data['month'], $ids, $force, $request));
    }

    public function preview(Request $request, OwnerStatementService $statements)
    {
        $this->superAdmin($request);
        $data = $request->validate([
            'label_id' => ['required', 'integer', 'exists:labels,id'],
            'import_id' => ['required', 'integer', 'exists:report_imports,id'],
        ]);

        return response($statements->previewPdf((int) $data['label_id'], (int) $data['import_id']), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="MixxTune-Statement-PREVIEW-label-'.$data['label_id'].'.pdf"',
        ]);
    }
}
