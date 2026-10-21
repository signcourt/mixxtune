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

                /* MIXXTUNE_INVOICES_V2: names, month, net amount, type */
                'invoices' => $query
                    ->with('user:id,name,email')
                    ->orderByDesc('id')
                    ->paginate(25)
                    ->through(function (Invoice $v) {
                        $net = (float) ($v->getAttribute('net_payable') ?? 0);

                        return [
                            'id' => $v->id,
                            'number' => $v->invoice_number,
                            'date' => $v->invoice_date?->format('d M Y'),
                            'kind' => $v->getAttribute('withdrawal_id') ? 'Payout' : 'Monthly royalty',
                            'month_label' => $v->statement ? app(\App\Services\V2\InvoiceService::class)->monthLabel($v->statement->statement_month) : null,
                            'owner' => $v->user?->name,
                            'currency' => $v->currency ?: 'INR',
                            'total' => round((float) $v->total_amount, 2),
                            'tds' => round((float) $v->tds_amount, 2),
                            'net' => round($net > 0 ? $net : (float) $v->total_amount - (float) $v->tds_amount, 2),
                            'status' => $v->status,
                        ];
                    }),
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
