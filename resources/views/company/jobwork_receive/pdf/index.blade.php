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
                <th style="width:16%;">Jobworker</th>
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
                    $issueWt = (float) ($row->issue_net_wt_sum ?? 0);
                    $receiveWt = (float) ($row->receive?->receive_net_wt_sum ?? 0);
                    $pendingWt = max(0, $issueWt - $receiveWt);
                    $status = 'Pending';

                    if ($issueWt > 0 && $pendingWt <= 0.0005) {
                        $status = 'Completed';
                    } elseif ($receiveWt > 0) {
                        $status = 'Partial';
                    }
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row->voucher_no }}</td>
                    <td>{{ optional($row->jobwork_date)->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $row->jobWorker?->name ?? '-' }}</td>
                    <td>{{ $row->productionStep?->name ?? '-' }}</td>
                    <td class="num">{{ number_format($issueWt, 3, '.', '') }}</td>
                    <td class="num">{{ number_format($receiveWt, 3, '.', '') }}</td>
                    <td class="num">{{ number_format($pendingWt, 3, '.', '') }}</td>
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
