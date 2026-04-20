<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h3 { margin: 0 0 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 6px; vertical-align: top; }
        th { background: #efefef; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h3>Barcode History Report - {{ $company->company_name ?? $company->name ?? 'Company' }}</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Label Code</th>
                <th>Label Created</th>
                <th>Label Printed</th>
                <th>Approval History</th>
                <th>Sale History</th>
                <th>Return History</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['item_name'] ?? '-' }}</td>
                    <td>{{ $r['label_code'] ?? '-' }}</td>
                    <td>{{ $r['label_created_at_fmt'] ?? '-' }}</td>
                    <td>{{ $r['label_printed_at_fmt'] ?? '-' }}</td>
                    <td>{{ !empty($r['approval_history']) ? implode(' | ', $r['approval_history']) : '-' }}</td>
                    <td>{{ !empty($r['sale_history']) ? implode(' | ', $r['sale_history']) : '-' }}</td>
                    <td>{{ !empty($r['return_history']) ? implode(' | ', $r['return_history']) : '-' }}</td>
                    <td>{{ $r['current_status'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="muted">No data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

