<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: 297mm 210mm; margin: 5mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #111; margin: 0; }
        .sheet { width: 287mm; max-width: 287mm; border: 1px solid #000; }
        .title { text-align: center; font-size: 16px; font-weight: 700; padding: 6px; border-bottom: 1px solid #000; }
        .sub { padding: 4px 6px; border-bottom: 1px solid #000; font-weight: 700; }
        .meta { padding: 4px 6px; border-bottom: 1px solid #000; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td {
            border: 1px solid #000;
            padding: 2px 1.5px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        th { background: #efefef; text-align: center; line-height: 1.15; }
        td { line-height: 1.2; }
        .nowrap { white-space: nowrap; }
        .approval-no {
            white-space: normal;
            word-break: break-all;
            overflow-wrap: anywhere;
            font-size: 6.7px;
            line-height: 1.05;
        }
        td.item { white-space: normal; }
        .num { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="title">{{ $company->name ?? 'Company' }}</div>
        <div class="sub">
            {{ ($viewType ?? 'item') === 'voucher' ? 'Pending Approval Voucher List' : 'Pending Approval Item List' }}
        </div>
        <div class="meta">
            Date Range:
            {{ !empty($fromDate) ? \Carbon\Carbon::parse($fromDate)->format('d-m-Y') : '-' }}
            to
            {{ !empty($toDate) ? \Carbon\Carbon::parse($toDate)->format('d-m-Y') : '-' }}
            &nbsp; | &nbsp; Total Entries: {{ $rows->count() }}
        </div>
        <table>
            <colgroup>
                <col style="width: 2%;">
                <col style="width: 10.5%;">
                <col style="width: 6.5%;">
                <col style="width: 7.5%;">
                <col style="width: 9.5%;">
                <col style="width: 5%;">
                <col style="width: 3%;">
                <col style="width: 6%;">
                <col style="width: 6%;">
                <col style="width: 6%;">
                <col style="width: 5.8%;">
                <col style="width: 6%;">
                <col style="width: 6.4%;">
                <col style="width: 6.1%;">
                <col style="width: 6.1%;">
                <col style="width: 5.8%;">
            </colgroup>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Approval No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Item Name</th>
                    <th>QR Code</th>
                    <th>Qty</th>
                    <th>Gross Wt</th>
                    <th>Other Wt</th>
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
                    $sumOtherWeight = 0.0;
                    $sumNet = 0.0;
                    $sumFine = 0.0;
                    $sumMetal = 0.0;
                    $sumLabour = 0.0;
                    $sumOther = 0.0;
                    $sumTotal = 0.0;
                @endphp
                @forelse($rows as $i => $row)
                    @php
                        $sumQty += (int) ($row['total_qty'] ?? 0);
                        $sumGross += (float) ($row['gross_wt'] ?? 0);
                        $sumOtherWeight += (float) ($row['other_wt'] ?? 0);
                        $sumNet += (float) ($row['net_wt'] ?? 0);
                        $sumFine += (float) ($row['fine_wt'] ?? 0);
                        $sumMetal += (float) ($row['metal_amt'] ?? 0);
                        $sumLabour += (float) ($row['labour_amt'] ?? 0);
                        $sumOther += (float) ($row['other_amt'] ?? 0);
                        $sumTotal += (float) ($row['total_amt'] ?? 0);
                    @endphp
                    <tr>
                        <td class="center nowrap">{{ $i + 1 }}</td>
                        <td class="approval-no">{{ $row['approval_no'] }}</td>
                        <td class="nowrap">{{ $row['approval_date'] }}</td>
                        <td>{{ $row['customer_name'] }}</td>
                        <td class="item">{{ $row['item_names'] }}</td>
                        <td class="nowrap">{{ $row['qr_code'] ?? '-' }}</td>
                        <td class="center nowrap">{{ $row['total_qty'] }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['gross_wt'], 3) }}</td>
                        <td class="num nowrap">{{ number_format((float)($row['other_wt'] ?? 0), 3) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['net_wt'], 3) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['fine_wt'], 3) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['metal_amt'], 2) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['labour_amt'], 2) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['other_amt'], 2) }}</td>
                        <td class="num nowrap">{{ number_format((float)$row['total_amt'], 2) }}</td>
                        <td>{{ $row['created_by'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="center">No records found</td></tr>
                @endforelse
                @if($rows->count() > 0)
                    <tr>
                        <th colspan="6" class="num">Grand Total</th>
                        <th class="center nowrap">{{ $sumQty }}</th>
                        <th class="num nowrap">{{ number_format($sumGross, 3) }}</th>
                        <th class="num nowrap">{{ number_format($sumOtherWeight, 3) }}</th>
                        <th class="num nowrap">{{ number_format($sumNet, 3) }}</th>
                        <th class="num nowrap">{{ number_format($sumFine, 3) }}</th>
                        <th class="num nowrap">{{ number_format($sumMetal, 2) }}</th>
                        <th class="num nowrap">{{ number_format($sumLabour, 2) }}</th>
                        <th class="num nowrap">Rs. {{ number_format($sumOther, 2) }}</th>
                        <th class="num nowrap">Rs. {{ number_format($sumTotal, 2) }}</th>
                        <th></th>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</body>
</html>
