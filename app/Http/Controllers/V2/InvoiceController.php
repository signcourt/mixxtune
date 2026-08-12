<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Services\V2\LabelAccess\LabelFinancialContextService;
use App\Services\V2\PermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        LabelFinancialContextService $financialContext
    ): Response {
        $actor = $request->user();

        $role = $permissions->role($actor);

        $query = Invoice::query()
            ->with('statement');

        if (
            !in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            $owner = $financialContext->owner($actor);

            $query->where(
                'user_id',
                $owner->id
            );
        }

        return Inertia::render(
            'V2/Invoices/Index',
            [
                'role' => $role,

                'invoices' => $query
                    ->orderByDesc('id')
                    ->paginate(25),
            ]
        );
    }

    public function download(
        Request $request,
        Invoice $invoice,
        PermissionService $permissions,
        LabelFinancialContextService $financialContext
    ): HttpResponse {
        $actor = $request->user();

        $role = $permissions->role($actor);

        if (
            !in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            $owner = $financialContext->owner($actor);

            abort_unless(
                (int) $invoice->user_id ===
                    (int) $owner->id,
                403
            );
        }

        $invoice->load([
            'items',
            'user',
            'statement',
        ]);

        return Pdf::loadView(
            'pdf.invoice',
            [
                'invoice' => $invoice,
            ]
        )->download(
            $invoice->invoice_number . '.pdf'
        );
    }
}
