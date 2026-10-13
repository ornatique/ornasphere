<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 6mm 4mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8.5px; }
    </style>
</head>
<body>
    @php
        $visibleColumns = $visibleColumns ?? [
            'voucher_no' => ['label' => 'Voucher No'],
            'date' => ['label' => 'Date'],
            'customer' => ['label' => 'Customer Name'],
            'qty_pcs' => ['label' => 'Qty', 'total_key' => 'qty_pcs', 'decimals' => 0],
            'gross_weight' => ['label' => 'Gross Wt', 'total_key' => 'gross_weight', 'decimals' => 3],
            'other_weight' => ['label' => 'Other Wt', 'total_key' => 'other_weight', 'decimals' => 3],
            'net_weight' => ['label' => 'Net Wt', 'total_key' => 'net_weight', 'decimals' => 3],
            'purity' => ['label' => 'Purity', 'total_key' => 'purity', 'decimals' => 3],
            'waste_percent' => ['label' => 'Waste %', 'total_key' => 'waste_percent', 'decimals' => 3],
            'fine_weight' => ['label' => 'Fine Wt', 'total_key' => 'fine_weight', 'decimals' => 3],
            'metal_rate' => ['label' => 'Metal Rate', 'total_key' => 'metal_rate', 'decimals' => 2],
            'metal_amount' => ['label' => 'Metal Amt', 'total_key' => 'metal_amount', 'decimals' => 2],
            'labour_rate' => ['label' => 'Labour Rate', 'total_key' => 'labour_rate', 'decimals' => 2],
            'labour_amount' => ['label' => 'Labour Amt', 'total_key' => 'labour_amount', 'decimals' => 2],
            'other_amount' => ['label' => 'Other Amt', 'total_key' => 'other_amount', 'decimals' => 2],
            'net_total' => ['label' => 'Total', 'total_key' => 'net_total', 'decimals' => 2],
            'remarks' => ['label' => 'Remarks'],
            'created_by' => ['label' => 'Created By'],
        ];
    @endphp

    <h3>Sales Summary Report - {{ $company->name }}</h3>
    @if(isset($selectedCustomer))
        <div style="margin-bottom:8px;font-size:11px;"><strong>Customer:</strong> {{ $selectedCustomer->name }}</div>
        @php unset($visibleColumns['customer']); @endphp
    @endif
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
                            @case('voucher_no')
                                <td>{{ $r->voucher_no }}</td>
                                @break
                            @case('date')
                                <td>{{ optional($r->sale_date)?->format('d-m-Y') }}</td>
                                @break
                            @case('customer')
                                <td>{{ optional($r->customer)->name ?? '-' }}</td>
                                @break
                            @case('qty_pcs')
                                <td>{{ (int)($r->total_qty ?? 0) }}</td>
                                @break
                            @case('gross_weight')
                                <td>{{ number_format((float)($r->total_gross_weight ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('other_weight')
                                <td>{{ number_format((float)($r->total_other_weight ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('net_weight')
                                <td>{{ number_format((float)($r->total_net_weight ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('purity')
                                <td>{{ number_format((float)($r->total_purity ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('waste_percent')
                                <td>{{ number_format((float)($r->total_waste_percent ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('fine_weight')
                                <td>{{ number_format((float)($r->total_fine_weight ?? 0), 3, '.', '') }}</td>
                                @break
                            @case('metal_rate')
                                <td>{{ number_format((float)($r->total_metal_rate ?? 0), 2, '.', '') }}</td>
                                @break
                            @case('metal_amount')
                                <td>{{ number_format((float)($r->total_metal_amount ?? 0), 2, '.', '') }}</td>
                                @break
                            @case('labour_rate')
                                <td>{{ number_format((float)($r->total_labour_rate ?? 0), 2, '.', '') }}</td>
                                @break
                            @case('labour_amount')
                                <td>{{ number_format((float)($r->total_labour_amount ?? 0), 2, '.', '') }}</td>
                                @break
                            @case('other_amount')
                                <td>{{ number_format((float)($r->total_other_amount ?? 0), 2, '.', '') }}</td>
                                @break
                            @case('net_total')
                                <td>{{ number_format((float)($r->net_total ?? 0), 2, '.', '') }}</td>
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
                    @elseif(in_array($key, ['purity', 'waste_percent', 'metal_rate', 'labour_rate'], true))
                        <td></td>
                    @elseif(isset($column['total_key']))
                        @php
                            $decimals = (int)($column['decimals'] ?? 0);
                            $value = $totals[$column['total_key']] ?? 0;
                        @endphp
                        <td style="font-weight:700;">
                            @if(in_array($key, ['metal_amount', 'labour_amount', 'other_amount', 'net_total'], true))Rs @endif
                            {{ $decimals > 0 ? number_format((float)$value, $decimals, '.', '') : (int)$value }}
                        </td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
