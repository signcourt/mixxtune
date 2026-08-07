<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
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
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        $query = Invoice::query()
            ->with('statement');

        if (
            !in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
        ) {
            $query->where(
                'user_id',
                $request->user()->id
            );
        }

        return Inertia::render(
            'V2/Invoices/Index',
            [
                'role' => $role,

                'invoices' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function download(
        Request $request,
        Invoice $invoice,
        PermissionService $permissions
    ): HttpResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            )
            || $invoice->user_id ===
                $request->user()->id,
            403
        );

        $invoice->load([
            'items',
            'user',
            'statement',
        ]);

        return Pdf::loadView(
            'pdf.invoice',
            [
                'invoice' =>
                    $invoice,
            ]
        )->download(
            $invoice->invoice_number
            . '.pdf'
        );
    }
}
