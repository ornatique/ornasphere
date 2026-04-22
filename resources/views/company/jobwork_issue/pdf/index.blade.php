<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Issue Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h2 { margin: 0 0 8px 0; }
        .meta { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #777; padding: 5px; text-align: left; }
        th { background: #f1f1f1; }
    </style>
</head>
<body>
    <h2>{{ $company->name }} - Jobwork Issue Report</h2>
    <div class="meta">Generated: {{ now()->format('d-m-Y h:i A') }}</div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Voucher No</th>
                <th>Voucher Date</th>
                <th>Jobworker</th>
                <th>Production Step</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Fine Wt</th>
                <th>Total Amt</th>
                <th>Created By</th>
                <th>Modified</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->voucher_no }}</td>
                    <td>{{ optional($row->jobwork_date)->format('d-m-Y') }}</td>
                    <td>{{ $row->jobWorker?->name ?? '-' }}</td>
                    <td>{{ $row->productionStep?->name ?? '-' }}</td>
                    <td>{{ number_format((float) ($row->gross_wt_sum ?? 0), 3, '.', '') }}</td>
                    <td>{{ number_format((float) ($row->net_wt_sum ?? 0), 3, '.', '') }}</td>
                    <td>{{ number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', '') }}</td>
                    <td>{{ number_format((float) ($row->total_amt_sum ?? 0), 2, '.', '') }}</td>
                    <td>{{ $row->createdByUser?->name ?? '-' }}</td>
                    <td>{{ optional($row->updated_at)->format('d-m-Y h:i A') }}</td>
                    <td>{{ optional($row->created_at)->format('d-m-Y h:i A') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align:center;">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
