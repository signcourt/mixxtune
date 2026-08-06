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
