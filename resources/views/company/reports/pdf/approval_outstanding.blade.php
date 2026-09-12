<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 6mm 4px 6mm 4px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8.5px; }
    </style>
</head>
<body>
    <h3>Approval Outstanding Report - {{ $company->name }}</h3>
    <table>
        <thead>
            <tr>
                @foreach($visibleColumns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                @foreach($visibleColumns as $key => $column)
                    @switch($key)
                        @case('approval_no')
                            <td>{{ $r->approval_no }}</td>
                            @break
                        @case('date')
                            <td>{{ optional($r->approval_date)?->format('d-m-Y') }}</td>
                            @break
                        @case('customer')
                            <td>{{ optional($r->customer)->name ?? '-' }}</td>
                            @break
                        @case('status')
                            <td>{{ ucfirst((string)$r->status) }}</td>
                            @break
                        @case('pending_pcs')
                            <td>{{ (int)($r->pending_items_count ?? 0) }}</td>
                            @break
                        @case('gross_weight')
                            <td>{{ number_format((float)($r->pending_gross_weight ?? 0), 3, '.', '') }}</td>
                            @break
                        @case('other_weight')
                            <td>{{ number_format((float)($r->pending_other_weight ?? 0), 3, '.', '') }}</td>
                            @break
                        @case('net_weight')
                            <td>{{ number_format((float)($r->pending_net_weight ?? 0), 3, '.', '') }}</td>
                            @break
                        @case('other_amount')
                            <td>{{ number_format((float)($r->pending_other_amount ?? 0), 2, '.', '') }}</td>
                            @break
                        @case('pending_amount')
                            <td>{{ number_format((float)($r->pending_total_amount ?? 0), 2, '.', '') }}</td>
                            @break
                        @case('remarks')
                            <td>{{ $r->remarks ?? '-' }}</td>
                            @break
                        @case('created_by')
                            <td>{{ optional($r->creator)->name ?? '-' }}</td>
                            @break
                    @endswitch
                @endforeach
            </tr>
            @endforeach
            <tr>
                @php $printedTotal = false; @endphp
                @foreach($visibleColumns as $key => $column)
                    @if(!$printedTotal)
                        <td style="font-weight:700;">TOTAL</td>
                        @php $printedTotal = true; @endphp
                    @elseif($key === 'pending_pcs')
                        <td style="font-weight:700;">{{ (int)($summary['pending_pcs'] ?? 0) }}</td>
                    @elseif($key === 'gross_weight')
                        <td style="font-weight:700;">{{ number_format((float)($summary['pending_gross_weight'] ?? 0), 3, '.', '') }}</td>
                    @elseif($key === 'other_weight')
                        <td style="font-weight:700;">{{ number_format((float)($summary['pending_other_weight'] ?? 0), 3, '.', '') }}</td>
                    @elseif($key === 'net_weight')
                        <td style="font-weight:700;">{{ number_format((float)($summary['pending_net_weight'] ?? 0), 3, '.', '') }}</td>
                    @elseif($key === 'other_amount')
                        <td style="font-weight:700;">{{ number_format((float)($summary['pending_other_amount'] ?? 0), 2, '.', '') }}</td>
                    @elseif($key === 'pending_amount')
                        <td style="font-weight:700;">{{ number_format((float)($summary['pending_amount'] ?? 0), 2, '.', '') }}</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
