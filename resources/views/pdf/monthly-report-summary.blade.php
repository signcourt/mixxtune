<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <title>
        Royalty Report - {{ $periodLabel }}
    </title>

    <style>
        @page {
            margin: 28px 30px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 10px;
            line-height: 1.45;
        }

        .header {
            border-bottom: 3px solid #7c3aed;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .brand {
            font-size: 12px;
            font-weight: bold;
            color: #7c3aed;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title {
            margin-top: 5px;
            font-size: 24px;
            font-weight: bold;
            color: #111827;
        }

        .subtitle {
            margin-top: 4px;
            color: #64748b;
            font-size: 10px;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-left: -8px;
            margin-bottom: 22px;
        }

        .summary-table td {
            width: 25%;
            border: 1px solid #e2e8f0;
            padding: 11px;
            vertical-align: top;
        }

        .metric-label {
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .metric-value {
            margin-top: 5px;
            color: #111827;
            font-size: 14px;
            font-weight: bold;
        }

        .section-title {
            margin: 18px 0 8px 0;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th {
            padding: 7px 8px;
            border: 1px solid #dbe2ea;
            background: #f1f5f9;
            color: #334155;
            font-size: 8px;
            text-align: left;
            text-transform: uppercase;
        }

        table.items td {
            padding: 7px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        table.items td.number,
        table.items th.number {
            text-align: right;
        }

        .unmapped {
            color: #b45309;
            font-weight: bold;
        }

        .total-row td {
            background: #f8fafc;
            font-weight: bold;
        }

        .note {
            margin-top: 16px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #64748b;
            font-size: 8px;
        }

        .footer {
            margin-top: 18px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            color: #94a3b8;
            font-size: 8px;
        }

        .page-number:after {
            content: counter(page);
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="brand">
            {{ config('app.name', 'Mixx Tune') }}
        </div>

        <div class="title">
            Royalty Report Summary
        </div>

        <div class="subtitle">
            Reporting Period:
            <strong>{{ $periodLabel }}</strong>
            &nbsp;•&nbsp;
            Currency: {{ $currency }}
        </div>
    </div>


    <table class="summary-table">
        <tr>
            <td>
                <div class="metric-label">
                    Gross Revenue
                </div>

                <div class="metric-value">
                    {{ $currency }}
                    {{ number_format($grossRevenue, 2) }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Mapped Revenue
                </div>

                <div class="metric-value">
                    {{ $currency }}
                    {{ number_format($mappedRevenue, 2) }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Unmapped Revenue
                </div>

                <div class="metric-value">
                    {{ $currency }}
                    {{ number_format($unmappedRevenue, 2) }}
                </div>
            </td>

            <td>
                <div class="metric-label">
                    Mapping Coverage
                </div>

                <div class="metric-value">
                    {{ number_format($mappingPercentage, 2) }}%
                </div>
            </td>
        </tr>
    </table>


    <div class="section-title">
        Account / Label Revenue Summary
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">
                    #
                </th>

                <th>
                    Account / Label
                </th>

                <th style="width: 15%;">
                    Mapping
                </th>

                <th class="number" style="width: 13%;">
                    Rows
                </th>

                <th class="number" style="width: 22%;">
                    Revenue
                </th>
            </tr>
        </thead>

        <tbody>

            @forelse($summaryRows as $row)
                <tr>
                    <td>
                        {{ $loop->iteration }}
                    </td>

                    <td>
                        @if($row->mapping_status === 'unmapped')
                            <span class="unmapped">
                                Unmapped Catalogue
                            </span>
                        @else
                            {{ $row->owner_name ?: 'Unknown Account' }}
                        @endif
                    </td>

                    <td>
                        {{ ucfirst($row->mapping_status ?: 'unmapped') }}
                    </td>

                    <td class="number">
                        {{ number_format((int) $row->rows_count) }}
                    </td>

                    <td class="number">
                        {{ $currency }}
                        {{ number_format((float) $row->gross, 2) }}
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="5">
                        No revenue data found for this reporting month.
                    </td>
                </tr>
            @endforelse


            <tr class="total-row">
                <td colspan="3">
                    Grand Total
                </td>

                <td class="number">
                    {{ number_format($totalRows) }}
                </td>

                <td class="number">
                    {{ $currency }}
                    {{ number_format($grossRevenue, 2) }}
                </td>
            </tr>

        </tbody>
    </table>


    <div class="note">
        This PDF is a financial summary for the selected
        <strong>Reporting Month</strong>.
        Track-level, ISRC, platform, country, sales-month and other
        detailed transaction data remain available in the downloadable
        Excel report.
    </div>


    <div class="footer">
        Generated:
        {{ $generatedAt }}
        &nbsp;•&nbsp;
        {{ config('app.name', 'Mixx Tune') }}
    </div>

</body>
</html>
