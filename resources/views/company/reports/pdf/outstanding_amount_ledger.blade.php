<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Outstanding Amount Ledger</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .title { font-size: 18px; font-weight: 700; text-align: center; margin-bottom: 4px; }
        .subtitle { text-align: center; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #222; padding: 6px; }
        th { background: #efefef; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="title">{{ $company->company_name ?? $company->name ?? 'Company' }}</div>
    <div class="subtitle">Outstanding Amount Ledger Report</div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 12%;">Voucher No</th>
                <th style="width: 9%;">Date</th>
                <th style="width: 16%;">Party</th>
                <th style="width: 10%;">City</th>
                <th style="width: 9%;">Mode</th>
                <th class="text-right" style="width: 9%;">Total Amount</th>
                <th class="text-right" style="width: 9%;">Amount In</th>
                <th class="text-right" style="width: 9%;">Amount Out</th>
                <th class="text-right" style="width: 9%;">Pending</th>
                <th class="text-right" style="width: 8%;">Net Wt</th>
                <th class="text-right" style="width: 8%;">Gross Wt</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                @php
                    $in = (float) ($row->received_amount ?? 0);
                    $out = (float) ($row->paid_amount ?? 0);
                    $pending = max(0, (float) ($row->net_total ?? 0) - ($in - $out));
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row->voucher_no ?? '-' }}</td>
                    <td>{{ optional($row->sale_date)?->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ optional($row->customer)->name ?? '-' }}</td>
                    <td>{{ optional($row->customer)->city ?? '-' }}</td>
                    <td>{{ ucfirst((string) ($row->payment_mode ?? '-')) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->net_total ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($in, 2) }}</td>
                    <td class="text-right">{{ number_format($out, 2) }}</td>
                    <td class="text-right">{{ number_format($pending, 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_net_weight ?? 0), 3) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_gross_weight ?? 0), 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center">No records found</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="text-right">TOTAL</th>
                <th class="text-right">{{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['amount_in'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['amount_out'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['pending_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['net_weight'] ?? 0), 3) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['gross_weight'] ?? 0), 3) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
