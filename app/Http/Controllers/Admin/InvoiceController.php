<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Invoices\InvoiceGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'status' => trim(
                (string) $request->input('status', '')
            ),
            'label_id' => trim(
                (string) $request->input('label_id', '')
            ),
            'search' => trim(
                (string) $request->input('search', '')
            ),
        ];

        $query = DB::table('invoices')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'invoices.label_id'
            )
            ->leftJoin(
                'withdrawals',
                'withdrawals.id',
                '=',
                'invoices.withdrawal_id'
            )
            ->select([
                'invoices.id',
                'invoices.public_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.currency',
                'invoices.subtotal',
                'invoices.gst_amount',
                'invoices.tds_amount',
                'invoices.total_amount',
                'invoices.net_payable',
                'invoices.status',
                'invoices.billing_name',
                'invoices.created_at',
                'labels.name as label_name',
                'withdrawals.withdrawal_number',
                'withdrawals.payment_reference',
            ]);

        if ($filters['status'] !== '') {
            $query->where(
                'invoices.status',
                $filters['status']
            );
        }

        if ($filters['label_id'] !== '') {
            $query->where(
                'invoices.label_id',
                $filters['label_id']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'invoices.invoice_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'labels.name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'withdrawals.withdrawal_number',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $query
                ->orderByDesc('invoices.id')
                ->paginate(25)
                ->withQueryString(),

            'paidWithdrawals' => DB::table('withdrawals')
                ->leftJoin(
                    'labels',
                    'labels.id',
                    '=',
                    'withdrawals.label_id'
                )
                ->leftJoin(
                    'invoices',
                    'invoices.withdrawal_id',
                    '=',
                    'withdrawals.id'
                )
                ->where('withdrawals.status', 'paid')
                ->whereNull('invoices.id')
                ->select([
                    'withdrawals.id',
                    'withdrawals.withdrawal_number',
                    'withdrawals.amount',
                    'withdrawals.currency',
                    'labels.name as label_name',
                ])
                ->orderByDesc('withdrawals.id')
                ->get(),

            'filterOptions' => [
                'labels' => DB::table('labels')
                    ->select('id', 'name')
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(),

                'statuses' => [
                    'draft',
                    'issued',
                    'paid',
                    'cancelled',
                ],
            ],

            'filters' => $filters,
        ]);
    }

    public function store(
        Request $request,
        InvoiceGenerationService $service
    ) {
        $validated = $request->validate([
            'withdrawal_id' => [
                'required',
                'integer',
            ],
            'gst_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'tds_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        try {
            $result = $service->generateFromWithdrawal(
                (int) $validated['withdrawal_id'],
                $request->user()?->id,
                (float) ($validated['gst_percentage'] ?? 0),
                (float) ($validated['tds_percentage'] ?? 0)
            );

            return back()->with(
                'success',
                $result['message']
                    ?? "Invoice {$result['invoice_number']} generate ho gaya."
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'invoice' => $exception->getMessage(),
            ]);
        }
    }

    public function show(int $id)
    {
        $invoice = DB::table('invoices')
            ->leftJoin(
                'labels',
                'labels.id',
                '=',
                'invoices.label_id'
            )
            ->leftJoin(
                'withdrawals',
                'withdrawals.id',
                '=',
                'invoices.withdrawal_id'
            )
            ->where('invoices.id', $id)
            ->select([
                'invoices.*',
                'labels.name as label_name',
                'withdrawals.withdrawal_number',
                'withdrawals.payment_reference',
                'withdrawals.payment_method',
                'withdrawals.paid_at',
            ])
            ->first();

        abort_if(!$invoice, 404);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => [
                ...((array) $invoice),
                'metadata' => json_decode(
                    (string) $invoice->metadata,
                    true
                ) ?: [],
            ],

            'items' => DB::table('invoice_items')
                ->where('invoice_id', $invoice->id)
                ->orderBy('id')
                ->get(),
        ]);
    }
}
