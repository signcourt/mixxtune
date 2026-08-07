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
