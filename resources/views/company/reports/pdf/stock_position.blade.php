<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h3>Stock Position Report - {{ $company->name }}</h3>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty Pcs</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Labour Amt</th>
                <th>Other Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td>{{ $r->item_name }}</td>
                <td>{{ (int)($r->qty_pcs ?? 0) }}</td>
                <td>{{ number_format((float)($r->gross_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->net_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->labour_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float)($r->other_amount ?? 0), 2, '.', '') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

