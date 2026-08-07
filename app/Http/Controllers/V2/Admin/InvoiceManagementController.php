<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\InvoiceService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceManagementController extends Controller
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
                ['admin', 'super_admin'],
                true
            ),
            403
        );

        return Inertia::render(
            'V2/Admin/Invoices/Index',
            [
                'role' => $role,

                'statements' =>
                    RoyaltyStatement::query()
                        ->whereIn(
                            'status',
                            [
                                'approved',
                                'available',
                                'paid',
                            ]
                        )
                        ->orderByDesc('id')
                        ->paginate(25),

                'invoiceStatementIds' =>
                    Invoice::query()
                        ->whereNotNull(
                            'royalty_statement_id'
                        )
                        ->pluck(
                            'royalty_statement_id'
                        ),
            ]
        );
    }

    public function generate(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions,
        InvoiceService $service
    ): RedirectResponse {
        abort_unless(
            in_array(
                $permissions->role(
                    $request->user()
                ),
                ['admin', 'super_admin'],
                true
            ),
            403
        );

        $invoice =
            $service->generateFromStatement(
                $statement,
                $request->user()
            );

        return back()->with(
            'success',
            "Invoice {$invoice->invoice_number} generated."
        );
    }
}
