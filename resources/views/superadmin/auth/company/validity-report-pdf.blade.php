<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Company Users & Plan Validity</title>
    <style>
        @page { margin: 24px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 5px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #777; padding: 4px 3px; vertical-align: top; }
        th { background: #e9eaf0; font-weight: bold; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Company Users & Plan Validity</h1>
    <div class="meta">
        Generated: {{ $generatedAt->format('d-m-Y h:i A') }} |
        Companies: {{ $rows->where('type', 'Company')->count() }} |
        Branches: {{ $rows->where('type', 'Branch')->count() }} |
        Total locations: {{ $rows->count() }}
    </div>
    @if(!empty($filters['plan']) || !empty($filters['validity']) || !empty($filters['expiry_from']) || !empty($filters['expiry_to']))
        <div class="meta">
            Filters:
            @if(!empty($filters['plan'])) Plan {{ ucwords(str_replace('_', ' ', $filters['plan'])) }}; @endif
            @if(!empty($filters['validity'])) Validity {{ ucfirst($filters['validity']) }}; @endif
            @if(!empty($filters['expiry_from'])) Expiry from {{ $filters['expiry_from'] }}; @endif
            @if(!empty($filters['expiry_to'])) Expiry to {{ $filters['expiry_to'] }}; @endif
        </div>
    @endif
    <table>
        <thead><tr>
            <th>Company / Branch</th><th>Type</th><th>Parent</th><th>Branches</th><th>Company Email</th><th>Admin</th><th>Admin Email</th>
            <th>Users</th><th>Active</th><th>Limit</th><th>Plan</th><th>Start</th><th>Expiry</th><th>Validity</th>
            <th>Access</th><th>City</th><th>State</th><th>Postcode</th><th>Country</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['name'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['parent_name'] }}</td><td>{{ $row['branch_count'] }}</td>
                <td>{{ $row['email'] }}</td><td>{{ $row['admin_name'] }}</td><td>{{ $row['admin_email'] }}</td>
                <td>{{ $row['users'] }}</td><td>{{ $row['active_users'] }}</td><td>{{ $row['user_limit'] }}</td>
                <td>{{ $row['plan'] }}</td><td>{{ $row['started_at'] }}</td><td>{{ $row['expires_at'] }}</td>
                <td>{{ $row['validity'] }}</td><td>{{ $row['company_access'] }}</td><td>{{ $row['city'] }}</td>
                <td>{{ $row['state'] }}</td><td>{{ $row['postcode'] }}</td><td>{{ $row['country'] }}</td>
            </tr>
        @empty
            <tr><td colspan="19">No companies match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
