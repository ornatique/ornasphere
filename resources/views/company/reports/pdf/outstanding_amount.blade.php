<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 6px; }
        th { background: #efefef; }
        .text-right { text-align: right; }
        .title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="title">Outstanding Amount Report - {{ $company->name }}</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Voucher</th>
                <th>Date</th>
                <th>Party</th>
                <th>City</th>
                <th>Mode</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Total</th>
                <th>In</th>
                <th>Out</th>
                <th>Pending</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                @php
                    $received = (float) ($r->received_amount ?? 0);
                    $out = (float) ($r->paid_amount ?? 0);
                    $pending = max(0, (float) ($r->net_total ?? 0) - ($received - $out));
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->voucher_no }}</td>
                    <td>{{ optional($r->sale_date)?->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ optional($r->customer)->name ?? '-' }}</td>
                    <td>{{ optional($r->customer)->city ?? '-' }}</td>
                    <td>{{ $r->payment_mode ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($r->sum_gross_weight ?? 0), 3, '.', '') }}</td>
                    <td class="text-right">{{ number_format((float) ($r->sum_net_weight ?? 0), 3, '.', '') }}</td>
                    <td class="text-right">{{ number_format((float) ($r->net_total ?? 0), 2, '.', '') }}</td>
                    <td class="text-right">{{ number_format($received, 2, '.', '') }}</td>
                    <td class="text-right">{{ number_format($out, 2, '.', '') }}</td>
                    <td class="text-right">{{ number_format($pending, 2, '.', '') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" style="font-weight:700;">TOTAL</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['gross_weight'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['net_weight'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['total_amount'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['amount_in'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['amount_out'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['pending_amount'] ?? 0), 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>

