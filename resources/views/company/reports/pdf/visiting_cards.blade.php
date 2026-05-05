<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Visiting Cards Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
        .meta { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; vertical-align: top; }
        th { background: #efefef; text-align: left; }
    </style>
</head>
<body>
    <div class="title">Visiting Cards Report</div>
    <div class="meta">
        <strong>Company:</strong> {{ $company->name ?? 'Company' }}<br>
        <strong>From:</strong> {{ \Carbon\Carbon::parse($fromDate)->format('d-m-Y') }}
        &nbsp;&nbsp;
        <strong>To:</strong> {{ \Carbon\Carbon::parse($toDate)->format('d-m-Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Mobile</th>
                <th>Email</th>
                <th>City</th>
                <th>Pincode</th>
                <th>Address</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td>{{ $r->name ?: '-' }}</td>
                <td>{{ $r->mobile_no ?: '-' }}</td>
                <td>{{ $r->email ?: '-' }}</td>
                <td>{{ $r->city ?: '-' }}</td>
                <td>{{ $r->pincode ?: '-' }}</td>
                <td>{{ $r->address ?: '-' }}</td>
                <td>{{ optional($r->created_at)->format('d-m-Y h:i A') ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;">No data found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
