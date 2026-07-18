<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 7mm 5mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; line-height: 1.25; color: #111; }
        h3 { margin: 0 0 4px; font-size: 13px; }
        .meta { margin-bottom: 7px; font-size: 8px; }
        .meta span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 3px 4px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8px; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .summary-title { margin: 8px 0 3px; font-size: 10px; font-weight: bold; }
        .w-sr { width: 5%; }
        .w-date { width: 13%; }
        .w-worker { width: 12%; }
        .w-voucher { width: 10%; }
        .w-buch { width: 10%; }
        .w-stage { width: 14%; }
        .w-wt { width: 9%; }
        .total-row td { font-weight: bold; background: #f7f7f7; }
    </style>
</head>
<body>
    <h3>Worker Loss Report - {{ $company->name }}</h3>
    <div class="meta">
        <span><strong>From:</strong> {{ $request->input('from_date') ?: '-' }}</span>
        <span><strong>To:</strong> {{ $request->input('to_date') ?: '-' }}</span>
        <span><strong>Worker:</strong> {{ optional($rows->firstWhere('worker_id', (int) $request->input('worker_id')))->worker_name ?: 'All Workers' }}</span>
        <span><strong>Voucher:</strong> {{ $request->input('voucher_no') ?: 'All' }}</span>
        <span><strong>Stage:</strong> {{ $request->input('stage') ?: 'All' }}</span>
        <span><strong>Loss Type:</strong> {{ $request->input('loss_type') ?: 'All' }}</span>
        <span><strong>Only Loss:</strong> {{ in_array($request->input('only_loss'), [1, '1', true, 'true', 'on', 'yes'], true) ? 'Yes' : 'No' }}</span>
    </div>

    <div class="summary-title">Worker Summary</div>
    <table>
        <thead>
            <tr>
                <th>Worker</th>
                <th>Rows</th>
                <th>Source Wt</th>
                <th>Receive Wt</th>
                <th>Bhuko</th>
                <th>Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summary['workers'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="text-end">{{ $row['rows'] }}</td>
                <td class="text-end">{{ $row['source_wt'] }}</td>
                <td class="text-end">{{ $row['receive_wt'] }}</td>
                <td class="text-end">{{ $row['bhuko'] }}</td>
                <td class="text-end">{{ $row['loss'] }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center">No data available</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-title">Stage Summary</div>
    <table>
        <thead>
            <tr>
                <th>Stage</th>
                <th>Rows</th>
                <th>Source Wt</th>
                <th>Receive Wt</th>
                <th>Bhuko</th>
                <th>Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summary['stages'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="text-end">{{ $row['rows'] }}</td>
                <td class="text-end">{{ $row['source_wt'] }}</td>
                <td class="text-end">{{ $row['receive_wt'] }}</td>
                <td class="text-end">{{ $row['bhuko'] }}</td>
                <td class="text-end">{{ $row['loss'] }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center">No data available</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-title">Details</div>

    <table>
        <thead>
            <tr>
                <th class="w-sr">Sr No</th>
                <th class="w-date">Date Time</th>
                <th class="w-worker">Worker</th>
                <th class="w-voucher">Voucher No</th>
                <th class="w-buch">B. No</th>
                <th class="w-stage">Stage</th>
                <th class="w-wt">Source Wt</th>
                <th class="w-wt">Receive Wt</th>
                <th class="w-wt">Bhuko</th>
                <th class="w-wt">Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row->process_datetime ? \Carbon\Carbon::parse($row->process_datetime)->format('d-m-Y h:i A') : '-' }}</td>
                <td>{{ $row->worker_name ?: '-' }}</td>
                <td>{{ $row->voucher_no ?: '-' }}</td>
                <td>{{ $row->buch_no ?: '-' }}</td>
                <td>{{ $row->stage ?: '-' }}</td>
                <td class="text-end">{{ number_format((float) ($row->source_wt ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($row->receive_wt ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($row->bhuko ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($row->loss ?? 0), 3, '.', '') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">No data available</td>
            </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="6" class="text-end">Total</td>
                <td class="text-end">{{ number_format((float) ($totals['source_wt'] ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($totals['receive_wt'] ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($totals['bhuko'] ?? 0), 3, '.', '') }}</td>
                <td class="text-end">{{ number_format((float) ($totals['loss'] ?? 0), 3, '.', '') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
