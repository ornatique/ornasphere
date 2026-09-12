<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 6mm 4px 6mm 4px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        .meta { width: 100%; margin-bottom: 6px; border-collapse: collapse; }
        .meta td { border: 1px solid #333; padding: 3px 4px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8.5px; }
    </style>
</head>
<body>
    <h3>Approval Outstanding Details - {{ $company->name }}</h3>

    <table class="meta">
        <tr>
            <td><strong>Approval No:</strong> {{ $approval['approval_no'] ?? '-' }}</td>
            <td><strong>Date:</strong> {{ $approval['approval_date'] ?? '-' }}</td>
            <td><strong>Customer:</strong> {{ $approval['customer_name'] ?? '-' }}</td>
            <td><strong>Status:</strong> {{ $approval['status'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Pending Pcs:</strong> {{ $summary['pending_pcs'] ?? 0 }}</td>
            <td><strong>Gross Wt:</strong> {{ $summary['pending_gross_weight'] ?? '0.000' }}</td>
            <td><strong>Other Wt:</strong> {{ $summary['pending_other_weight'] ?? '0.000' }}</td>
            <td><strong>Net Wt:</strong> {{ $summary['pending_net_weight'] ?? '0.000' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                @foreach($columns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    @foreach($columns as $key => $column)
                        @switch($key)
                            @case('approval_no')
                                <td>{{ $item['approval_no'] ?? '-' }}</td>
                                @break
                            @case('date')
                                <td>{{ $item['date'] ?? '-' }}</td>
                                @break
                            @case('customer')
                                <td>{{ $item['customer'] ?? '-' }}</td>
                                @break
                            @case('qr_code')
                                <td>{{ $item['qr_code'] ?? '-' }}</td>
                                @break
                            @case('huid')
                                <td>{{ $item['huid'] ?? '-' }}</td>
                                @break
                            @case('item')
                                <td>{{ $item['item_name'] ?? '-' }}</td>
                                @break
                            @case('status')
                                <td>{{ $item['status'] ?? '-' }}</td>
                                @break
                            @case('gross_weight')
                                <td>{{ $item['gross_weight'] ?? '0.000' }}</td>
                                @break
                            @case('other_weight')
                                <td>{{ $item['other_weight'] ?? '0.000' }}</td>
                                @break
                            @case('net_weight')
                                <td>{{ $item['net_weight'] ?? '0.000' }}</td>
                                @break
                            @case('other_amount')
                                <td>{{ $item['other_amount'] ?? '0.00' }}</td>
                                @break
                            @case('pending_amount')
                                <td>{{ $item['total_amount'] ?? '0.00' }}</td>
                                @break
                        @endswitch
                    @endforeach
                </tr>
            @endforeach
            <tr>
                <td style="font-weight:700;">TOTAL</td>
                @foreach($columns as $key => $column)
                    @if($key === 'gross_weight')
                        <td style="font-weight:700;">{{ $summary['pending_gross_weight'] ?? '0.000' }}</td>
                    @elseif($key === 'other_weight')
                        <td style="font-weight:700;">{{ $summary['pending_other_weight'] ?? '0.000' }}</td>
                    @elseif($key === 'net_weight')
                        <td style="font-weight:700;">{{ $summary['pending_net_weight'] ?? '0.000' }}</td>
                    @elseif($key === 'other_amount')
                        <td style="font-weight:700;">{{ $summary['pending_other_amount'] ?? '0.00' }}</td>
                    @elseif($key === 'pending_amount')
                        <td style="font-weight:700;">{{ $summary['pending_amount'] ?? '0.00' }}</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
