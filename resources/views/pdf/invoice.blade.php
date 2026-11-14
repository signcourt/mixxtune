<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    @php
        // W180: $forSuperAdmin is passed by the controller. The owner copy shows
        // the gross (+GST) with NO TDS line; the super-admin copy adds the TDS
        // breakdown and the net actually released.
        $forSuperAdmin = $forSuperAdmin ?? false;

        $company   = $invoice->company_details ?? [];
        $billing   = $invoice->billing_details ?? [];
        $metadata  = $invoice->metadata ?? [];

        $isRecovery = ($invoice->invoice_type ?? '') === 'advance_recovery';
        $isGst      = (float) $invoice->gst_amount > 0;

        // FROM = the label / payee (the biller). TO = our company (the payer).
        $fromName =
            $invoice->billing_name
            ?? $billing['account_holder_name']
            ?? $metadata['label_name']
            ?? $invoice->user?->name
            ?? 'Payee';

        $companyName =
            $company['legal_name']
            ?? $company['name']
            ?? config('app.name');

        // GST split: compare the state code (first 2 chars of GSTIN) of the
        // company and the biller. Same state -> CGST + SGST; cross state -> IGST.
        $billerGstin  = $billing['gst_number'] ?? ($invoice->gstin ?? null);
        $companyGstin = $company['gst_number'] ?? null;
        $sameState = $billerGstin && $companyGstin
            && strtoupper(substr((string) $billerGstin, 0, 2)) === strtoupper(substr((string) $companyGstin, 0, 2));
        $gstAmount  = (float) $invoice->gst_amount;
        $gstPercent = (float) $invoice->gst_percentage;
        $halfGst    = round($gstAmount / 2, 2);
        $halfPct    = round($gstPercent / 2, 2);

        $taxable    = (float) $invoice->subtotal;
        $grossTotal = (float) $invoice->total_amount;         // taxable + GST
        $tdsAmount  = (float) $invoice->tds_amount;
        $tdsPercent = (float) $invoice->tds_percentage;
        $netPayable = (float) $invoice->net_payable;          // gross - TDS

        $payoutPeriod =
            $metadata['payout_period']
            ?? $invoice->invoice_date?->format('F Y');

        $withdrawalNumber =
            $metadata['withdrawal_number']
            ?? $invoice->withdrawal?->withdrawal_number
            ?? null;

        $currency = $invoice->currency ?: 'INR';
        $fmt = fn ($v) => $currency.' '.number_format((float) $v, 2);
    @endphp

    <title>TAX INVOICE - {{ $invoice->invoice_number }}</title>

    <style>
        @page { margin: 32px; }
        body { font-family: DejaVu Sans, sans-serif; color:#172033; font-size:11px; line-height:1.55; }
        .header { border-bottom:3px solid #7c3aed; padding-bottom:16px; margin-bottom:20px; }
        .header-table,.party,.items,.totals,.ref,.bank { width:100%; border-collapse:collapse; }
        .fromname { font-size:20px; font-weight:bold; color:#111827; letter-spacing:.3px; }
        .title { text-align:right; font-size:20px; font-weight:bold; color:#5b21b6; }
        .number { text-align:right; margin-top:5px; color:#64748b; }
        .section-title { margin-top:20px; margin-bottom:8px; font-size:10px; font-weight:bold; text-transform:uppercase; color:#475569; letter-spacing:.6px; }
        .party td { width:50%; vertical-align:top; padding:12px; border:1px solid #e2e8f0; }
        .ref td { vertical-align:top; padding:10px 12px; border:1px solid #e2e8f0; }
        .label { color:#64748b; font-size:9px; text-transform:uppercase; margin-bottom:5px; letter-spacing:.6px; }
        .items th,.items td { padding:10px; border:1px solid #e2e8f0; }
        .items th { background:#f1f5f9; text-align:left; color:#475569; }
        .right { text-align:right; }
        .totals { width:52%; margin-left:auto; margin-top:16px; }
        .totals td { padding:8px 10px; border-bottom:1px solid #e2e8f0; }
        .net-row td { border-top:2px solid #7c3aed; border-bottom:2px solid #7c3aed; font-size:14px; font-weight:bold; color:#111827; }
        .bank { border:1px solid #e2e8f0; border-radius:8px; }
        .bank td { width:50%; vertical-align:top; padding:12px 14px; border-top:1px solid #eef2f7; }
        .bank tr:first-child td { border-top:none; }
        .bank td:first-child { border-right:1px solid #eef2f7; }
        .bk-label { color:#94a3b8; font-size:8.5px; text-transform:uppercase; letter-spacing:.6px; margin-bottom:3px; }
        .bk-val { font-size:12px; font-weight:600; color:#111827; }
        .admin-box { margin-top:18px; padding:12px 14px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; }
        .admin-box .label { color:#9a3412; }
        .footer { margin-top:26px; border-top:1px solid #e2e8f0; padding-top:10px; color:#94a3b8; font-size:9px; text-align:center; }
    </style>
</head>

<body>

@if($isRecovery)
    {{-- Advance-recovery invoices keep their own simple layout (W176/W177). --}}
    <div class="header">
        <table class="header-table"><tr>
            <td><div class="fromname">{{ $companyName }}</div></td>
            <td>
                <div class="title">ADVANCE RECOVERY</div>
                <div class="number">{{ $invoice->invoice_number }}</div>
            </td>
        </tr></table>
    </div>
    <table class="party"><tr>
        <td>
            <div class="label">Owner</div>
            <strong>{{ $fromName }}</strong>
        </td>
        <td>
            <div class="label">Document Date</div>
            {{ $invoice->invoice_date?->format('d M Y') }}
        </td>
    </tr></table>
    <table class="totals">
        <tr class="net-row"><td>Amount Recovered</td><td class="right">{{ $fmt($invoice->net_payable) }}</td></tr>
    </table>
    @if(!empty($invoice->notes))
        <div class="footer">{{ $invoice->notes }}</div>
    @endif
@else

<div class="header">
    <table class="header-table"><tr>
        <td>
            <div class="fromname">{{ $fromName }}</div>
        </td>
        <td>
            <div class="title">TAX INVOICE</div>
            <div class="number">Invoice No: <strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="number">Date: {{ $invoice->invoice_date?->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>

<table class="party">
    <tr>
        <td>
            <div class="label">From</div>
            <strong>{{ $fromName }}</strong><br>
            @if(!empty($billing['address']))
                {{ $billing['address'] }}<br>
            @elseif(!empty($invoice->billing_address))
                {{ $invoice->billing_address }}<br>
            @endif
            @if(!empty($invoice->billing_email))
                {{ $invoice->billing_email }}<br>
            @endif
            @php $fromPan = $invoice->pan ?? ($billing['pan_number'] ?? null); @endphp
            @if(!empty($fromPan))
                PAN: {{ $fromPan }}<br>
            @endif
            @if(!empty($billerGstin))
                GSTIN: {{ $billerGstin }}
            @endif
        </td>
        <td>
            <div class="label">Bill To</div>
            <strong>{{ $companyName }}</strong><br>
            @if(!empty($company['address']))
                {{ $company['address'] }}<br>
            @endif
            @if(!empty($companyGstin))
                GSTIN: {{ $companyGstin }}<br>
            @endif
            @if(!empty($company['pan_number']))
                PAN: {{ $company['pan_number'] }}
            @endif
        </td>
    </tr>
</table>

<table class="ref" style="margin-top:10px">
    <tr>
        <td style="width:33%"><div class="label">Payout Period</div>{{ $payoutPeriod ?? '—' }}</td>
        <td style="width:33%"><div class="label">Reference No</div>{{ $withdrawalNumber ?? '—' }}</td>
        <td style="width:34%"><div class="label">Payment Against</div>Royalty withdrawal payout</td>
    </tr>
</table>

<div class="section-title">Invoice Details</div>
<table class="items">
    <thead><tr>
        <th>Description</th>
        <th class="right">Qty</th>
        <th class="right">Rate ({{ $currency }})</th>
        <th class="right">Amount ({{ $currency }})</th>
    </tr></thead>
    <tbody>
        @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ number_format((float) $item->quantity, 0) }}</td>
                <td class="right">{{ number_format((float) $item->rate, 2) }}</td>
                <td class="right">{{ number_format((float) $item->amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Taxable Value</td><td class="right">{{ $fmt($taxable) }}</td></tr>

    @if($isGst)
        @if($sameState)
            <tr><td>CGST @ {{ number_format($halfPct, 2) }}%</td><td class="right">{{ $fmt($halfGst) }}</td></tr>
            <tr><td>SGST @ {{ number_format($halfPct, 2) }}%</td><td class="right">{{ $fmt($halfGst) }}</td></tr>
        @else
            <tr><td>IGST @ {{ number_format($gstPercent, 2) }}%</td><td class="right">{{ $fmt($gstAmount) }}</td></tr>
        @endif
    @endif

    @if($forSuperAdmin)
        <tr><td>Gross Amount</td><td class="right">{{ $fmt($grossTotal) }}</td></tr>
        @if($tdsAmount > 0)
            <tr><td>Less: TDS @ {{ number_format($tdsPercent, 2) }}%</td><td class="right">− {{ $fmt($tdsAmount) }}</td></tr>
        @endif
        <tr class="net-row"><td>Net Released</td><td class="right">{{ $fmt($netPayable) }}</td></tr>
    @else
        <tr class="net-row"><td>Total Payable</td><td class="right">{{ $fmt($grossTotal) }}</td></tr>
    @endif
</table>

<div class="section-title">Bank Details</div>
<table class="bank">
    <tr>
        <td>
            <div class="bk-label">Account Holder</div>
            <div class="bk-val">{{ $billing['account_holder_name'] ?? $fromName }}</div>
        </td>
        <td>
            <div class="bk-label">Account Number</div>
            <div class="bk-val">{{ $billing['bank_account_number'] ?? '—' }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="bk-label">IFSC Code</div>
            <div class="bk-val">{{ $billing['ifsc_code'] ?? '—' }}</div>
        </td>
        <td>
            <div class="bk-label">Bank</div>
            <div class="bk-val">{{ $billing['bank_name'] ?? '—' }}{{ !empty($billing['branch_name']) ? ' — '.$billing['branch_name'] : '' }}</div>
        </td>
    </tr>
    @if(!empty($billing['upi_id']))
        <tr>
            <td>
                <div class="bk-label">UPI</div>
                <div class="bk-val">{{ $billing['upi_id'] }}</div>
            </td>
            <td></td>
        </tr>
    @endif
</table>

@if($forSuperAdmin)
    <div class="admin-box">
        <div class="label">Super Admin — payout breakdown</div>
        {{ $fromName }} requested {{ $fmt($taxable) }}@if($isGst) + GST {{ $fmt($gstAmount) }} = {{ $fmt($grossTotal) }}@endif.
        @if($tdsAmount > 0)
            TDS @ {{ number_format($tdsPercent, 2) }}% = {{ $fmt($tdsAmount) }} deducted.
        @else
            No TDS applied.
        @endif
        Net released to the payee: <strong>{{ $fmt($netPayable) }}</strong>.
    </div>
@endif

<div class="footer">
    System-generated tax invoice raised by {{ $fromName }} on {{ $companyName }} for the royalty payout of the stated period.
</div>

@endif

</body>
</html>
