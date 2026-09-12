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
    <h3>Sale Details - {{ $company->name }}</h3>

    <table class="meta">
        <tr>
            <td><strong>Voucher No:</strong> {{ $sale['voucher_no'] ?? '-' }}</td>
            <td><strong>Date:</strong> {{ $sale['sale_date'] ?? '-' }}</td>
            <td><strong>Customer:</strong> {{ $sale['customer_name'] ?? '-' }}</td>
            <td><strong>Created By:</strong> {{ $sale['created_by'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Qty:</strong> {{ $summary['qty_pcs'] ?? 0 }}</td>
            <td><strong>Gross Wt:</strong> {{ $summary['gross_weight'] ?? '0.000' }}</td>
            <td><strong>Other Wt:</strong> {{ $summary['other_weight'] ?? '0.000' }}</td>
            <td><strong>Net Wt:</strong> {{ $summary['net_weight'] ?? '0.000' }}</td>
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
                            @case('voucher_no')
                                <td>{{ $item['voucher_no'] ?? '-' }}</td>
                                @break
                            @case('date')
                                <td>{{ $item['date'] ?? '-' }}</td>
                                @break
                            @case('customer')
                                <td>{{ $item['customer'] ?? '-' }}</td>
                                @break
                            @case('label')
                                <td>{{ $item['label'] ?? '-' }}</td>
                                @break
                            @case('huid')
                                <td>{{ $item['huid'] ?? '-' }}</td>
                                @break
                            @case('item')
                            @case('item_name')
                                <td>{{ $item['item_name'] ?? '-' }}</td>
                                @break
                            @case('qty_pcs')
                                <td>{{ $item['qty_pcs'] ?? 0 }}</td>
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
                            @case('purity')
                                <td>{{ $item['purity'] ?? '0.000' }}</td>
                                @break
                            @case('waste_percent')
                                <td>{{ $item['waste_percent'] ?? '0.000' }}</td>
                                @break
                            @case('net_purity')
                                <td>{{ $item['net_purity'] ?? '0.000' }}</td>
                                @break
                            @case('fine_weight')
                                <td>{{ $item['fine_weight'] ?? '0.000' }}</td>
                                @break
                            @case('metal_rate')
                                <td>{{ $item['metal_rate'] ?? '0.00' }}</td>
                                @break
                            @case('metal_amount')
                                <td>{{ $item['metal_amount'] ?? '0.00' }}</td>
                                @break
                            @case('labour_rate')
                                <td>{{ $item['labour_rate'] ?? '0.00' }}</td>
                                @break
                            @case('labour_amount')
                                <td>{{ $item['labour_amount'] ?? '0.00' }}</td>
                                @break
                            @case('other_amount')
                                <td>{{ $item['other_amount'] ?? '0.00' }}</td>
                                @break
                            @case('total_amount')
                                <td>{{ $item['total_amount'] ?? '0.00' }}</td>
                                @break
                            @case('net_total')
                                <td>{{ $item['net_total'] ?? $item['total_amount'] ?? '0.00' }}</td>
                                @break
                            @case('remarks')
                                <td>{{ $item['remarks'] ?? '-' }}</td>
                                @break
                        @endswitch
                    @endforeach
                </tr>
            @endforeach
            <tr>
                <td style="font-weight:700;">TOTAL</td>
                @foreach($columns as $key => $column)
                    @if(isset($column['total_key']))
                        <td style="font-weight:700;">{{ $summary[$column['total_key']] ?? '' }}</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
