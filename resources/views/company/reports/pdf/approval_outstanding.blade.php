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
    <h3>Approval Outstanding Report - {{ $company->name }}</h3>
    <table>
        <thead>
            <tr>
                <th>Approval No</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Pending Pcs</th>
                <th>Pending Net Wt</th>
                <th>Pending Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td>{{ $r->approval_no }}</td>
                <td>{{ optional($r->approval_date)?->format('d-m-Y') }}</td>
                <td>{{ optional($r->customer)->name ?? '-' }}</td>
                <td>{{ ucfirst((string)$r->status) }}</td>
                <td>{{ (int)($r->pending_items_count ?? 0) }}</td>
                <td>{{ number_format((float)($r->pending_net_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float)($r->pending_total_amount ?? 0), 2, '.', '') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

