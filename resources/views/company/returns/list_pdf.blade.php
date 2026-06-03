<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; margin: 0; }
        .sheet { width: 100%; border: 1px solid #000; }
        .title { text-align: center; font-size: 20px; font-weight: 700; padding: 8px; border-bottom: 1px solid #000; }
        .sub { padding: 6px 8px; border-bottom: 1px solid #000; font-weight: 700; }
        .meta { padding: 5px 8px; border-bottom: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        th { background: #efefef; text-align: center; white-space: nowrap; }
        td { white-space: nowrap; }
        td.item { white-space: normal; min-width: 180px; }
        .num { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="title">{{ $company->name ?? 'Company' }}</div>
        <div class="sub">Approval Return List</div>
        <div class="meta">
            Date Range:
            {{ \Carbon\Carbon::parse($fromDate)->format('d-m-Y') }}
            to
            {{ \Carbon\Carbon::parse($toDate)->format('d-m-Y') }}
            &nbsp; | &nbsp; Total Entries: {{ $rows->count() }}
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Voucher No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Item Name</th>
                    <th>Qty</th>
                    <th>Gross Wt</th>
                    <th>Net Wt</th>
                    <th>Fine Wt</th>
                    <th>Metal Amt</th>
                    <th>Labour Amt</th>
                    <th>Other Amt</th>
                    <th>Total Amt</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $sumQty = 0;
                    $sumGross = 0.0;
                    $sumNet = 0.0;
                    $sumFine = 0.0;
                    $sumMetal = 0.0;
                    $sumLabour = 0.0;
                    $sumOther = 0.0;
                    $sumTotal = 0.0;
                @endphp
                @forelse($rows as $i => $row)
                    @php
                        $sumQty += (int) $row['qty'];
                        $sumGross += (float) $row['gross_wt'];
                        $sumNet += (float) $row['net_wt'];
                        $sumFine += (float) $row['fine_wt'];
                        $sumMetal += (float) $row['metal_amt'];
                        $sumLabour += (float) $row['labour_amt'];
                        $sumOther += (float) $row['other_amt'];
                        $sumTotal += (float) $row['total_amt'];
                    @endphp
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $row['voucher_no'] }}</td>
                        <td>{{ $row['return_date'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td class="item">{{ $row['item_names'] }}</td>
                        <td class="center">{{ $row['qty'] }}</td>
                        <td class="num">{{ number_format((float) $row['gross_wt'], 3) }}</td>
                        <td class="num">{{ number_format((float) $row['net_wt'], 3) }}</td>
                        <td class="num">{{ number_format((float) $row['fine_wt'], 3) }}</td>
                        <td class="num">Rs {{ number_format((float) $row['metal_amt'], 2) }}</td>
                        <td class="num">Rs {{ number_format((float) $row['labour_amt'], 2) }}</td>
                        <td class="num">Rs {{ number_format((float) $row['other_amt'], 2) }}</td>
                        <td class="num">Rs {{ number_format((float) $row['total_amt'], 2) }}</td>
                        <td>{{ $row['created_by'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="center">No records found</td></tr>
                @endforelse
                @if($rows->isNotEmpty())
                    <tr>
                        <th colspan="5" class="num">Grand Total</th>
                        <th class="center">{{ $sumQty }}</th>
                        <th class="num">{{ number_format($sumGross, 3) }}</th>
                        <th class="num">{{ number_format($sumNet, 3) }}</th>
                        <th class="num">{{ number_format($sumFine, 3) }}</th>
                        <th class="num">Rs {{ number_format($sumMetal, 2) }}</th>
                        <th class="num">Rs {{ number_format($sumLabour, 2) }}</th>
                        <th class="num">Rs {{ number_format($sumOther, 2) }}</th>
                        <th class="num">Rs {{ number_format($sumTotal, 2) }}</th>
                        <th></th>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</body>
</html>
