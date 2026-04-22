<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Issue {{ $row->voucher_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #777; padding: 4px; text-align: left; }
        th { background: #f1f1f1; }
    </style>
</head>
<body>
    <h2>{{ $company->name }} - Jobwork Issue {{ $row->voucher_no }}</h2>
    <div class="meta">
        Voucher Date: {{ optional($row->jobwork_date)->format('d-m-Y') }} |
        Jobworker: {{ $row->jobWorker?->name ?? '-' }} |
        Production Step: {{ $row->productionStep?->name ?? '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Other Charge</th>
                <th>Gross Wt</th>
                <th>Other Wt</th>
                <th>Net Wt</th>
                <th>Fine Wt</th>
                <th>Qty</th>
                <th>Purity</th>
                <th>Net Purity</th>
                <th>Total Amt</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($row->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item?->item_name ?? '-' }}</td>
                <td>{{ $item->otherCharge?->other_charge ?? '-' }}</td>
                <td>{{ number_format((float) ($item->gross_wt ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($item->other_wt ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($item->net_wt ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($item->fine_wt ?? 0), 3, '.', '') }}</td>
                <td>{{ (int) ($item->qty_pcs ?? 0) }}</td>
                <td>{{ number_format((float) ($item->purity ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($item->net_purity ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($item->total_amt ?? 0), 2, '.', '') }}</td>
                <td>{{ $item->remarks ?? '' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" style="text-align:center;">No item rows found</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="meta" style="margin-top:8px;">
        Totals -> Gross: {{ number_format((float) ($row->gross_wt_sum ?? 0), 3, '.', '') }},
        Net: {{ number_format((float) ($row->net_wt_sum ?? 0), 3, '.', '') }},
        Fine: {{ number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', '') }},
        Amount: {{ number_format((float) ($row->total_amt_sum ?? 0), 2, '.', '') }}
    </div>
</body>
</html>
