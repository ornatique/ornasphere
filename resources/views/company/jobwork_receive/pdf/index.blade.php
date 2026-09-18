<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Receive Report</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }
        h2 { margin: 0 0 8px 0; font-size: 16px; }
        .meta { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #777; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f1f1f1; font-weight: 700; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2>{{ $company->name }} - Jobwork Receive Report</h2>
    <div class="meta">Generated: {{ now()->format('d-m-Y h:i A') }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:13%;">Voucher No</th>
                <th style="width:12%;">Voucher Date</th>
                <th style="width:16%;">Customer / Jobworker</th>
                <th style="width:16%;">Production Step</th>
                <th style="width:12%;" class="num">Issue Net Wt</th>
                <th style="width:12%;" class="num">Receive Net Wt</th>
                <th style="width:12%;" class="num">Pending Net Wt</th>
                <th style="width:10%;" class="center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                @php
                    $status = strip_tags((string) ($row['status'] ?? '-'));
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ strip_tags((string) ($row['voucher_no'] ?? '-')) }}</td>
                    <td>{{ $row['jobwork_date_view'] ?? '-' }}</td>
                    <td>{{ $row['jobworker_name'] ?? '-' }}</td>
                    <td>{{ $row['production_step_name'] ?? '-' }}</td>
                    <td class="num">{{ $row['issue_net_wt_sum'] ?? '0.000' }}</td>
                    <td class="num">{{ $row['receive_net_wt_sum'] ?? '0.000' }}</td>
                    <td class="num">{{ $row['pending_net_wt'] ?? '0.000' }}</td>
                    <td class="center">{{ $status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">No records found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
