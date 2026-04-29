<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 6mm 4px 6mm 4px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #555; padding: 2px 3px; vertical-align: top; word-wrap: break-word; }
        th { background: #efefef; font-size: 8.5px; }
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
                    <td>{{ !empty($r['approval_history']) ? implode(' | ', \Illuminate\Support\Arr::pluck((array) $r['approval_history'], 'label')) : '-' }}</td>
                    <td>{{ !empty($r['sale_history']) ? implode(' | ', \Illuminate\Support\Arr::pluck((array) $r['sale_history'], 'label')) : '-' }}</td>
                    <td>{{ !empty($r['return_history']) ? implode(' | ', \Illuminate\Support\Arr::pluck((array) $r['return_history'], 'label')) : '-' }}</td>
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
