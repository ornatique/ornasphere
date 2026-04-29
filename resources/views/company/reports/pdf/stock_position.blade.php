<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 6mm 4px 6mm 4px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8.5px; }
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
