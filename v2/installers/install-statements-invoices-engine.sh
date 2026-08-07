#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/statements-invoices-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/Finance \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Statements \
    resources/js/Pages/V2/Invoices \
    resources/js/Pages/V2/Admin/Invoices \
    resources/views/pdf \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING STATEMENTS + INVOICES + PDF ENGINE"
echo "=================================================="

for FILE in \
    routes/web.php \
    composer.json \
    composer.lock
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[1/9] Installing PDF package..."

if ! composer show barryvdh/laravel-dompdf >/dev/null 2>&1
then
    composer require barryvdh/laravel-dompdf
else
    echo "Laravel DOMPDF already installed."
fi


echo "[2/9] Creating invoice migration..."

MIGRATION="database/migrations/2026_08_01_000012_create_v2_invoices_table.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create(
                'invoices',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->string(
                        'invoice_number',
                        50
                    )->unique();

                    $table->unsignedBigInteger(
                        'user_id'
                    );

                    $table->unsignedBigInteger(
                        'royalty_statement_id'
                    )->nullable();

                    $table->string(
                        'invoice_type',
                        30
                    )->default('royalty');

                    $table->date(
                        'invoice_date'
                    );

                    $table->date(
                        'due_date'
                    )->nullable();

                    $table->string(
                        'currency',
                        10
                    )->default('INR');

                    $table->decimal(
                        'subtotal',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tax_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'tds_amount',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'total_amount',
                        20,
                        8
                    )->default(0);

                    $table->string(
                        'status',
                        30
                    )->default('generated');

                    $table->json(
                        'billing_details'
                    )->nullable();

                    $table->json(
                        'company_details'
                    )->nullable();

                    $table->text(
                        'notes'
                    )->nullable();

                    $table->timestamp(
                        'paid_at'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'created_by'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'user_id',
                        'invoice_date',
                    ]);

                    $table->index([
                        'status',
                        'invoice_date',
                    ]);

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            );
        }

        if (!Schema::hasTable('invoice_items')) {
            Schema::create(
                'invoice_items',
                function (Blueprint $table) {
                    $table->id();

                    $table->unsignedBigInteger(
                        'invoice_id'
                    );

                    $table->string(
                        'description'
                    );

                    $table->decimal(
                        'quantity',
                        16,
                        4
                    )->default(1);

                    $table->decimal(
                        'rate',
                        20,
                        8
                    )->default(0);

                    $table->decimal(
                        'amount',
                        20,
                        8
                    )->default(0);

                    $table->json(
                        'meta'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign(
                        'invoice_id'
                    )
                        ->references('id')
                        ->on('invoices')
                        ->cascadeOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Financial records are not deleted
         * automatically.
         */
    }
};
PHP
fi


echo "[3/9] Creating models..."

cat > app/Models/Finance/Invoice.php <<'PHP'
<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'public_id',
        'invoice_number',
        'user_id',
        'royalty_statement_id',
        'invoice_type',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'tds_amount',
        'total_amount',
        'status',
        'billing_details',
        'company_details',
        'notes',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:8',
        'tax_amount' => 'decimal:8',
        'tds_amount' => 'decimal:8',
        'total_amount' => 'decimal:8',
        'billing_details' => 'array',
        'company_details' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function statement()
    {
        return $this->belongsTo(
            RoyaltyStatement::class,
            'royalty_statement_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            InvoiceItem::class
        );
    }
}
PHP

cat > app/Models/Finance/InvoiceItem.php <<'PHP'
<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'rate',
        'amount',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:8',
        'amount' => 'decimal:8',
        'meta' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class
        );
    }
}
PHP


echo "[4/9] Creating Invoice Service..."

cat > app/Services/V2/InvoiceService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function generateFromStatement(
        RoyaltyStatement $statement,
        User $admin
    ): Invoice {
        $existing = Invoice::query()
            ->where(
                'royalty_statement_id',
                $statement->id
            )
            ->first();

        if ($existing) {
            return $existing;
        }

        $owner = $this->ownerUser(
            $statement
        );

        abort_unless(
            $owner,
            422,
            'Royalty statement owner user is missing.'
        );

        return DB::transaction(
            function () use (
                $statement,
                $owner,
                $admin
            ) {
                $subtotal =
                    (float) $statement
                        ->net_payable;

                $taxAmount = 0;
                $tdsAmount =
                    (float) $statement
                        ->tax_amount;

                $total =
                    $subtotal
                    + $taxAmount
                    - $tdsAmount;

                $invoice = Invoice::query()
                    ->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'invoice_number' =>
                            $this->nextNumber(),

                        'user_id' =>
                            $owner->id,

                        'royalty_statement_id' =>
                            $statement->id,

                        'invoice_type' =>
                            'royalty',

                        'invoice_date' =>
                            now()->toDateString(),

                        'due_date' =>
                            now()
                                ->addDays(15)
                                ->toDateString(),

                        'currency' =>
                            $statement->currency,

                        'subtotal' =>
                            $subtotal,

                        'tax_amount' =>
                            $taxAmount,

                        'tds_amount' =>
                            $tdsAmount,

                        'total_amount' =>
                            $total,

                        'status' =>
                            $statement->status ===
                            'paid'
                                ? 'paid'
                                : 'generated',

                        'billing_details' => [
                            'name' =>
                                $owner->name,

                            'email' =>
                                $owner->email,
                        ],

                        'company_details' => [
                            'name' =>
                                config(
                                    'app.name',
                                    'Backstage Mixx Tune'
                                ),

                            'address' =>
                                'India',

                            'email' =>
                                config(
                                    'mail.from.address'
                                ),
                        ],

                        'notes' =>
                            "Royalty invoice for {$statement->statement_month}",

                        'created_by' =>
                            $admin->id,
                    ]);

                $invoice->items()->create([
                    'description' =>
                        "Royalty earnings for {$statement->statement_month}",

                    'quantity' => 1,

                    'rate' =>
                        $subtotal,

                    'amount' =>
                        $subtotal,

                    'meta' => [
                        'statement_id' =>
                            $statement->id,
                    ],
                ]);

                return $invoice->fresh([
                    'items',
                    'user',
                    'statement',
                ]);
            }
        );
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');

        $lastId = Invoice::query()
            ->whereYear(
                'invoice_date',
                $year
            )
            ->max('id') ?? 0;

        return sprintf(
            'INV-%s-%06d',
            $year,
            $lastId + 1
        );
    }

    private function ownerUser(
        RoyaltyStatement $statement
    ): ?User {
        if ($statement->artist_id) {
            $userId = Artist::query()
                ->where(
                    'id',
                    $statement->artist_id
                )
                ->value('user_id');

            return $userId
                ? User::query()->find(
                    $userId
                )
                : null;
        }

        if ($statement->label_id) {
            $userId = Label::query()
                ->where(
                    'id',
                    $statement->label_id
                )
                ->value('user_id');

            return $userId
                ? User::query()->find(
                    $userId
                )
                : null;
        }

        return null;
    }
}
PHP


echo "[5/9] Creating controllers..."

cat > app/Http/Controllers/V2/StatementController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RoyaltyStatement;
use App\Services\V2\PermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        $query =
            RoyaltyStatement::query();

        $this->applyScope(
            $query,
            $request,
            $role
        );

        return Inertia::render(
            'V2/Statements/Index',
            [
                'role' => $role,

                'statements' =>
                    $query
                        ->orderByDesc(
                            'statement_month'
                        )
                        ->paginate(25),
            ]
        );
    }

    public function download(
        Request $request,
        RoyaltyStatement $statement,
        PermissionService $permissions
    ): HttpResponse {
        $role = $permissions->role(
            $request->user()
        );

        $query = RoyaltyStatement::query()
            ->where(
                'id',
                $statement->id
            );

        $this->applyScope(
            $query,
            $request,
            $role
        );

        abort_unless(
            $query->exists(),
            403
        );

        $statement->load(
            'allocations'
        );

        return Pdf::loadView(
            'pdf.royalty-statement',
            [
                'statement' =>
                    $statement,

                'user' =>
                    $request->user(),
            ]
        )->download(
            'statement-'
            . $statement->statement_month
            . '.pdf'
        );
    }

    private function applyScope(
        $query,
        Request $request,
        string $role
    ): void {
        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'artist_id',
                $artistId ?: 0
            );
        }

        if ($role === 'label') {
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->value('id');

            $query->where(
                'label_id',
                $labelId ?: 0
            );
        }

        if ($role === 'admin') {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $request->user()->id
                )
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }
    }
}
PHP

cat > app/Http/Controllers/V2/InvoiceController.php <<'PHP'
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
PHP

cat > app/Http/Controllers/V2/Admin/InvoiceManagementController.php <<'PHP'
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
PHP


echo "[6/9] Creating PDF templates..."

cat > resources/views/pdf/royalty-statement.blade.php <<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Royalty Statement</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1e293b;
            font-size: 12px;
        }

        .header {
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
        }

        .muted {
            color: #64748b;
        }

        .grid {
            width: 100%;
            margin-bottom: 24px;
        }

        .grid td {
            padding: 9px;
            border: 1px solid #e2e8f0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th,
        table.items td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        table.items th {
            background: #f1f5f9;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="title">
            Royalty Statement
        </div>

        <div class="muted">
            {{ config('app.name') }}
        </div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Statement Month</strong><br>
                {{ $statement->statement_month }}
            </td>

            <td>
                <strong>Status</strong><br>
                {{ ucfirst($statement->status) }}
            </td>

            <td>
                <strong>Currency</strong><br>
                {{ $statement->currency }}
            </td>
        </tr>

        <tr>
            <td>
                <strong>Gross Earnings</strong><br>
                {{ number_format($statement->gross_earnings, 2) }}
            </td>

            <td>
                <strong>Deductions</strong><br>
                {{ number_format(
                    $statement->commission_amount
                    + $statement->tax_amount
                    + $statement->other_deductions,
                    2
                ) }}
            </td>

            <td>
                <strong>Net Payable</strong><br>
                {{ number_format($statement->net_payable, 2) }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Release ID</th>
                <th>Track ID</th>
                <th>Gross</th>
                <th>Net</th>
            </tr>
        </thead>

        <tbody>
            @forelse($statement->allocations as $allocation)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $allocation->release_id ?? '—' }}</td>
                    <td>{{ $allocation->track_id ?? '—' }}</td>
                    <td>{{ number_format($allocation->gross_amount, 2) }}</td>
                    <td>{{ number_format($allocation->net_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No allocations found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
BLADE

cat > resources/views/pdf/invoice.blade.php <<'BLADE'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 12px;
        }

        .top {
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
        }

        .meta {
            width: 100%;
            margin-bottom: 25px;
        }

        .meta td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th,
        table.items td {
            padding: 10px;
            border: 1px solid #e2e8f0;
        }

        table.items th {
            background: #f1f5f9;
            text-align: left;
        }

        .totals {
            width: 45%;
            margin-left: auto;
            margin-top: 20px;
        }

        .totals td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="top">
        <div class="invoice-title">
            INVOICE
        </div>

        <div>
            {{ $invoice->invoice_number }}
        </div>
    </div>

    <table class="meta">
        <tr>
            <td>
                <strong>From</strong><br>
                {{ $invoice->company_details['name'] ?? config('app.name') }}<br>
                {{ $invoice->company_details['address'] ?? '' }}<br>
                {{ $invoice->company_details['email'] ?? '' }}
            </td>

            <td>
                <strong>Bill To</strong><br>
                {{ $invoice->billing_details['name'] ?? $invoice->user?->name }}<br>
                {{ $invoice->billing_details['email'] ?? $invoice->user?->email }}
            </td>
        </tr>

        <tr>
            <td>
                Invoice Date:
                {{ $invoice->invoice_date?->format('d M Y') }}
            </td>

            <td>
                Due Date:
                {{ $invoice->due_date?->format('d M Y') ?? '—' }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Rate</th>
                <th>Amount</th>
            </tr>
        </thead>

        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->rate, 2) }}</td>
                    <td>{{ number_format($item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>

        <tr>
            <td>Tax</td>
            <td>{{ number_format($invoice->tax_amount, 2) }}</td>
        </tr>

        <tr>
            <td>TDS</td>
            <td>-{{ number_format($invoice->tds_amount, 2) }}</td>
        </tr>

        <tr>
            <td><strong>Total</strong></td>
            <td>
                <strong>
                    {{ $invoice->currency }}
                    {{ number_format($invoice->total_amount, 2) }}
                </strong>
            </td>
        </tr>
    </table>
</body>
</html>
BLADE


echo "[7/9] Creating frontend pages..."

cat > resources/js/Pages/V2/Statements/Index.jsx <<'JSX'
import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    statements = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Statements"
            subtitle="Monthly royalty statements"
        >
            <Head title="Statements" />

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Month',
                                'Gross',
                                'Deductions',
                                'Net',
                                'Status',
                                'Download',
                            ].map((heading) => (
                                <th
                                    key={heading}
                                    className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-100">
                        {(statements.data ?? []).map(
                            (item) => (
                                <tr key={item.id}>
                                    <Cell>
                                        {item.statement_month}
                                    </Cell>

                                    <Cell>
                                        {item.gross_earnings}
                                    </Cell>

                                    <Cell>
                                        {Number(
                                            item.commission_amount
                                        ) +
                                            Number(
                                                item.tax_amount
                                            ) +
                                            Number(
                                                item.other_deductions
                                            )}
                                    </Cell>

                                    <Cell>
                                        {item.net_payable}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        <a
                                            href={`/v2/statements/${item.id}/download`}
                                            className="font-semibold text-violet-600"
                                        >
                                            PDF
                                        </a>
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Invoices/Index.jsx <<'JSX'
import {
    Head,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'artist',
    invoices = {},
}) {
    return (
        <PanelLayout
            role={role}
            title="Invoices"
            subtitle="Generated royalty invoices"
        >
            <Head title="Invoices" />

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Invoice',
                                'Date',
                                'Month',
                                'Amount',
                                'Status',
                                'Download',
                            ].map((heading) => (
                                <th
                                    key={heading}
                                    className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-100">
                        {(invoices.data ?? []).map(
                            (item) => (
                                <tr key={item.id}>
                                    <Cell>
                                        {item.invoice_number}
                                    </Cell>

                                    <Cell>
                                        {item.invoice_date}
                                    </Cell>

                                    <Cell>
                                        {item.statement
                                            ?.statement_month ??
                                            '—'}
                                    </Cell>

                                    <Cell>
                                        {item.currency}{' '}
                                        {item.total_amount}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        <a
                                            href={`/v2/invoices/${item.id}/download`}
                                            className="font-semibold text-violet-600"
                                        >
                                            PDF
                                        </a>
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/Invoices/Index.jsx <<'JSX'
import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    statements = {},
    invoiceStatementIds = [],
}) {
    const generated = new Set(
        invoiceStatementIds.map(Number)
    );

    const generate = (id) => {
        router.post(
            `/v2/admin/invoices/statements/${id}/generate`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Generate Invoices"
            subtitle="Create invoices from royalty statements"
        >
            <Head title="Generate Invoices" />

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {[
                                'Statement Month',
                                'Artist',
                                'Net Payable',
                                'Status',
                                'Invoice',
                            ].map((heading) => (
                                <th
                                    key={heading}
                                    className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-100">
                        {(statements.data ?? []).map(
                            (item) => (
                                <tr key={item.id}>
                                    <Cell>
                                        {item.statement_month}
                                    </Cell>

                                    <Cell>
                                        {item.artist_id ??
                                            item.label_id ??
                                            '—'}
                                    </Cell>

                                    <Cell>
                                        {item.currency}{' '}
                                        {item.net_payable}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        {generated.has(
                                            Number(item.id)
                                        ) ? (
                                            <span className="font-semibold text-emerald-600">
                                                Generated
                                            </span>
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    generate(
                                                        item.id
                                                    )
                                                }
                                                className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white"
                                            >
                                                Generate
                                            </button>
                                        )}
                                    </Cell>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX


echo "[8/9] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.statements.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/statements',
        [\App\Http\Controllers\V2\StatementController::class, 'index']
    )
    ->name('v2.statements.index');
""",

    "v2.statements.download": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/statements/{statement}/download',
        [\App\Http\Controllers\V2\StatementController::class, 'download']
    )
    ->name('v2.statements.download');
""",

    "v2.invoices.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/invoices',
        [\App\Http\Controllers\V2\InvoiceController::class, 'index']
    )
    ->name('v2.invoices.index');
""",

    "v2.invoices.download": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/invoices/{invoice}/download',
        [\App\Http\Controllers\V2\InvoiceController::class, 'download']
    )
    ->name('v2.invoices.download');
""",

    "v2.admin.invoices.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/invoices',
        [\App\Http\Controllers\V2\Admin\InvoiceManagementController::class, 'index']
    )
    ->name('v2.admin.invoices.index');
""",

    "v2.admin.invoices.generate": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/invoices/statements/{statement}/generate',
        [\App\Http\Controllers\V2\Admin\InvoiceManagementController::class, 'generate']
    )
    ->name('v2.admin.invoices.generate');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} statement/invoice routes added.")
PY


echo "[9/9] Migrating, validating and building..."

php artisan migrate --force

php -l app/Models/Finance/Invoice.php
php -l app/Models/Finance/InvoiceItem.php
php -l app/Services/V2/InvoiceService.php
php -l app/Http/Controllers/V2/StatementController.php
php -l app/Http/Controllers/V2/InvoiceController.php
php -l app/Http/Controllers/V2/Admin/InvoiceManagementController.php
php -l routes/web.php

npm run build

php artisan optimize:clear

printf '{\n  "module": "StatementsInvoicesEngine",\n  "installed": true,\n  "version": "4.3.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/statements-invoices-engine-installed.json

echo ""
echo "===== STATEMENT & INVOICE ROUTES ====="

php artisan route:list | grep -E \
"v2/(statements|invoices|admin/invoices)"

echo ""
echo "=================================================="
echo "STATEMENTS + INVOICES ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/statements-invoices-engine-installed.json

echo ""
echo "User pages:"
echo "https://artist.mixxtune.com/v2/statements"
echo "https://artist.mixxtune.com/v2/invoices"

echo ""
echo "Admin invoice generation:"
echo "https://admin.mixxtune.com/v2/admin/invoices"

echo ""
echo "Backup:"
echo "$BACKUP"
