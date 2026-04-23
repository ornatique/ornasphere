<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customers Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h2 { margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #777; padding: 5px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h2>{{ $company->name }} - Customers Report</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>City</th>
                <th>Area</th>
                <th>Landmark</th>
                <th>Created Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->email }}</td>
                    <td>{{ $row->mobile_no }}</td>
                    <td>{{ $row->city }}</td>
                    <td>{{ $row->area }}</td>
                    <td>{{ $row->landmark }}</td>
                    <td>{{ optional($row->created_at)?->format('d-m-Y h:i A') }}</td>
                    <td>{{ (int) $row->is_active === 1 ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
