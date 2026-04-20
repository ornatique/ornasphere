<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h3>Sales Summary Report - {{ $company->name }}</h3>
    <table>
        <thead>
            <tr>
                <th>Voucher No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Qty</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Fine Wt</th>
                <th>Metal Amt</th>
                <th>Labour Amt</th>
                <th>Other Amt</th>
                <th>Total</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td>{{ $r->voucher_no }}</td>
                <td>{{ optional($r->sale_date)?->format('d-m-Y') }}</td>
                <td>{{ optional($r->customer)->name ?? '-' }}</td>
                <td>{{ (int)($r->total_qty ?? 0) }}</td>
                <td>{{ number_format((float)($r->total_gross_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->total_net_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->total_fine_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->total_metal_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float)($r->total_labour_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float)($r->total_other_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float)($r->net_total ?? 0), 2, '.', '') }}</td>
                <td>{{ optional($r->creator)->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

