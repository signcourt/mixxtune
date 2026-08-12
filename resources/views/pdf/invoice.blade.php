<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    @php
        $isGst =
            $invoice->tax_treatment === 'gst'
            || (float) $invoice->gst_amount > 0;

        $documentTitle = $isGst
            ? 'GST TAX INVOICE'
            : 'ROYALTY PAYMENT STATEMENT';

        $company =
            $invoice->company_details ?? [];

        $billing =
            $invoice->billing_details ?? [];

        $metadata =
            $invoice->metadata ?? [];

        $companyName =
            $company['legal_name']
            ?? $company['name']
            ?? config('app.name');

        $payeeName =
            $invoice->billing_name
            ?? $billing['account_holder_name']
            ?? $invoice->user?->name;

        $withdrawalNumber =
            $metadata['withdrawal_number']
            ?? $invoice->withdrawal?->withdrawal_number
            ?? null;

        $paymentReference =
            $metadata['payment_reference']
            ?? $invoice->withdrawal?->payment_reference
            ?? null;
    @endphp

    <title>
        {{ $documentTitle }} -
        {{ $invoice->invoice_number }}
    </title>

    <style>
        @page {
            margin: 32px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 11px;
            line-height: 1.5;
        }

        .header {
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .header-table,
        .meta,
        .items,
        .totals,
        .payment {
            width: 100%;
            border-collapse: collapse;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }

        .title {
            text-align: right;
            font-size: 21px;
            font-weight: bold;
            color: #5b21b6;
        }

        .number {
            text-align: right;
            margin-top: 4px;
            color: #64748b;
        }

        .section-title {
            margin-top: 20px;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
        }

        .meta td,
        .payment td {
            width: 50%;
            vertical-align: top;
            padding: 11px;
            border: 1px solid #e2e8f0;
        }

        .label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .items th,
        .items td {
            padding: 9px;
            border: 1px solid #e2e8f0;
        }

        .items th {
            background: #f1f5f9;
            text-align: left;
            color: #475569;
        }

        .right {
            text-align: right;
        }

        .totals {
            width: 48%;
            margin-left: auto;
            margin-top: 18px;
        }

        .totals td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .net-row td {
            border-top: 2px solid #7c3aed;
            border-bottom: 2px solid #7c3aed;
            font-size: 13px;
            font-weight: bold;
            color: #111827;
        }

        .note {
            margin-top: 22px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            color: #64748b;
            font-size: 9px;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="brand">
                    {{ $company['name'] ?? config('app.name') }}
                </div>

                @if(!empty($company['legal_name']))
                    <div>
                        {{ $company['legal_name'] }}
                    </div>
                @endif
            </td>

            <td>
                <div class="title">
                    {{ $documentTitle }}
                </div>

                <div class="number">
                    {{ $invoice->invoice_number }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="meta">
    <tr>
        <td>
            <div class="label">
                Issued By
            </div>

            <strong>{{ $companyName }}</strong><br>

            @if(!empty($company['address']))
                {{ $company['address'] }}<br>
            @endif

            @if(!empty($company['email']))
                {{ $company['email'] }}<br>
            @endif

            @if(!empty($company['phone']))
                {{ $company['phone'] }}<br>
            @endif

            @if(!empty($company['gst_number']))
                GSTIN:
                {{ $company['gst_number'] }}<br>
            @endif

            @if(!empty($company['pan_number']))
                PAN:
                {{ $company['pan_number'] }}
            @endif
        </td>

        <td>
            <div class="label">
                Payee / Bill To
            </div>

            <strong>{{ $payeeName }}</strong><br>

            @if(!empty($invoice->billing_address))
                {{ $invoice->billing_address }}<br>
            @elseif(!empty($billing['address']))
                {{ $billing['address'] }}<br>
            @endif

            @if(!empty($invoice->billing_email))
                {{ $invoice->billing_email }}<br>
            @endif

            @if(!empty($invoice->gstin))
                GSTIN:
                {{ $invoice->gstin }}<br>
            @endif

            @if(!empty($invoice->pan))
                PAN:
                {{ $invoice->pan }}
            @elseif(!empty($billing['masked_pan']))
                PAN:
                {{ $billing['masked_pan'] }}
            @endif
        </td>
    </tr>

    <tr>
        <td>
            <div class="label">
                Document Date
            </div>

            {{ $invoice->invoice_date?->format('d M Y') }}
        </td>

        <td>
            <div class="label">
                Tax Treatment
            </div>

            {{ $isGst
                ? 'GST Registered'
                : 'Non-GST / TDS Applicable'
            }}
        </td>
    </tr>
</table>

<div class="section-title">
    Royalty Payout Details
</div>

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="right">Quantity</th>
            <th class="right">Rate</th>
            <th class="right">Amount</th>
        </tr>
    </thead>

    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td>
                    {{ $item->description }}
                </td>

                <td class="right">
                    {{ number_format(
                        (float) $item->quantity,
                        2
                    ) }}
                </td>

                <td class="right">
                    {{ $invoice->currency }}
                    {{ number_format(
                        (float) $item->rate,
                        2
                    ) }}
                </td>

                <td class="right">
                    {{ $invoice->currency }}
                    {{ number_format(
                        (float) $item->amount,
                        2
                    ) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Royalty Amount</td>

        <td class="right">
            {{ $invoice->currency }}
            {{ number_format(
                (float) $invoice->subtotal,
                2
            ) }}
        </td>
    </tr>

    @if($isGst)
        <tr>
            <td>
                GST
                ({{ number_format(
                    (float) $invoice->gst_percentage,
                    2
                ) }}%)
            </td>

            <td class="right">
                {{ $invoice->currency }}
                {{ number_format(
                    (float) $invoice->gst_amount,
                    2
                ) }}
            </td>
        </tr>
    @endif

    <tr>
        <td>
            Gross Amount
        </td>

        <td class="right">
            {{ $invoice->currency }}
            {{ number_format(
                (float) $invoice->total_amount,
                2
            ) }}
        </td>
    </tr>

    @if((float) $invoice->tds_amount > 0)
        <tr>
            <td>
                Less: TDS
                ({{ number_format(
                    (float) $invoice->tds_percentage,
                    2
                ) }}%)
            </td>

            <td class="right">
                - {{ $invoice->currency }}
                {{ number_format(
                    (float) $invoice->tds_amount,
                    2
                ) }}
            </td>
        </tr>
    @endif

    <tr class="net-row">
        <td>
            Net Payable
        </td>

        <td class="right">
            {{ $invoice->currency }}
            {{ number_format(
                (float) $invoice->net_payable,
                2
            ) }}
        </td>
    </tr>
</table>

@if($withdrawalNumber || $paymentReference)
    <div class="section-title">
        Payment Reference
    </div>

    <table class="payment">
        <tr>
            <td>
                <div class="label">
                    Withdrawal
                </div>

                {{ $withdrawalNumber ?? '—' }}
            </td>

            <td>
                <div class="label">
                    Payment Reference
                </div>

                {{ $paymentReference ?? '—' }}
            </td>
        </tr>
    </table>
@endif

@if(!empty($invoice->notes))
    <div class="note">
        <strong>Note:</strong><br>
        {{ $invoice->notes }}
    </div>
@endif

<div class="footer">
    This document was generated electronically by
    {{ $companyName }}.

    @if(!$isGst)
        TDS shown above represents tax deduction
        applicable to this royalty payout.
    @endif
</div>

</body>
</html>
