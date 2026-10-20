{{-- MIXXTUNE_OWNER_STATEMENT_V1 --}}
@php
    $cur = $statement->currency ?: 'INR';
    $money = fn ($v) => $cur.' '.number_format((float) $v, 2);
    $num = fn ($v) => number_format((float) $v, 0);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Royalty Statement {{ $month_label }}</title>
    <style>
        @page { margin: 28px 32px 40px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 10.5px; }
        .top { width: 100%; border-bottom: 2px solid #7c3aed; padding-bottom: 10px; margin-bottom: 14px; }
        .top td { vertical-align: middle; }
        .brand { font-size: 18px; font-weight: bold; color: #4c1d95; }
        .doc { text-align: right; }
        .doc .t { font-size: 16px; font-weight: bold; }
        .muted { color: #64748b; }
        .box { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .box td { border: 1px solid #e2e8f0; padding: 7px 9px; vertical-align: top; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .big { font-size: 15px; font-weight: bold; }
        .net { background: #f5f3ff; }
        .net .big { color: #4c1d95; font-size: 17px; }
        h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; margin: 16px 0 6px 0; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        table.items td { padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
        .r, table.items th.r, table.items td.r { text-align: right; }
        .note { margin-top: 18px; font-size: 9px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .two { width: 100%; }
        .two > tbody > tr > td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
    @if (!empty($preview))
        <div style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:6px 10px;margin-bottom:10px;font-weight:bold;">
            PREVIEW — report not approved yet. Amounts can still change. Not sent to the owner.
        </div>
    @endif
    <table class="top">
        <tr>
            <td>
                @if ($branding['logo'])
                    <img src="{{ $branding['logo'] }}" style="height: 34px;">
                @else
                    <span class="brand">{{ $branding['name'] }}</span>
                @endif
            </td>
            <td class="doc">
                <div class="t">Royalty Statement</div>
                <div class="muted">{{ $month_label }}@if ($statement->id) · Statement #{{ $statement->id }}@endif</div>
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td style="width: 50%;">
                <div class="label">{{ $owner['type'] === 'label' ? 'Label' : 'Artist' }}</div>
                <div class="big">{{ $owner['title'] }}</div>
                @if ($owner['name'])
                    <div>{{ $owner['name'] }}</div>
                @endif
                @if ($owner['email'])
                    <div class="muted">{{ $owner['email'] }}</div>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="label">Statement month</div>
                <div class="big">{{ $month_label }}</div>
                <div class="muted">Status: {{ ucfirst((string) $statement->status) }} · Currency: {{ $cur }}</div>
                @if (count($reports))
                    <div class="muted">Reports: @foreach ($reports as $r)#{{ $r->id }}@if (!$loop->last), @endif @endforeach</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td><div class="label">Collected revenue</div><div class="big">{{ $money($summary['collected']) }}</div></td>
            <td><div class="label">Your share</div><div class="big">{{ $summary['rate'] ?? '—' }}</div></td>
            <td><div class="label">Earnings</div><div class="big">{{ $money($summary['gross']) }}</div></td>
            <td><div class="label">Deductions</div><div class="big">{{ $money($summary['deductions']) }}</div></td>
            <td class="net"><div class="label">Net payable</div><div class="big">{{ $money($summary['net']) }}</div></td>
        </tr>
    </table>
    <div class="muted">Streams / units: {{ $num($summary['streams']) }} · Report lines: {{ $num($summary['rows']) }}</div>

    <h3>By store</h3>
    <table class="items">
        <thead><tr><th>Store</th><th class="r">Streams</th><th class="r">Collected</th><th class="r">Earnings</th></tr></thead>
        <tbody>
            @forelse ($by_store as $s)
                <tr><td>{{ $s->name }}</td><td class="r">{{ $num($s->streams) }}</td><td class="r">{{ $money($s->collected) }}</td><td class="r">{{ $money($s->earnings) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No report lines.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="two">
        <tr>
            <td style="padding-right: 8px;">
                <h3>Top countries</h3>
                <table class="items">
                    <thead><tr><th>Country</th><th class="r">Streams</th><th class="r">Earnings</th></tr></thead>
                    <tbody>
                        @forelse ($by_country as $c)
                            <tr><td>{{ $c->name }}</td><td class="r">{{ $num($c->streams) }}</td><td class="r">{{ $money($c->earnings) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="muted">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="padding-left: 8px;">
                <h3>How it is calculated</h3>
                <div class="muted" style="line-height: 1.5;">
                    Earnings = collected revenue × your share for each report line.
                    Negative adjustments from stores are passed through at 100%.
                    Net payable = earnings − deductions (if any).
                    Approved statements are final; later corrections appear in a later month.
                </div>
            </td>
        </tr>
    </table>

    <h3>Top tracks</h3>
    <table class="items">
        <thead><tr><th>#</th><th>Track</th><th>ISRC</th><th class="r">Streams</th><th class="r">Earnings</th></tr></thead>
        <tbody>
            @forelse ($top_tracks as $t)
                <tr><td>{{ $loop->iteration }}</td><td>{{ \Illuminate\Support\Str::limit($t->title, 60) }}</td><td>{{ $t->isrc }}</td><td class="r">{{ $num($t->streams) }}</td><td class="r">{{ $money($t->earnings) }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">No tracks.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">
        System generated statement · {{ $branding['name'] }} · Generated {{ $generated_at }} IST.
        Questions? Reply to the statement e-mail or contact support from your panel.
    </div>
</body>
</html>
