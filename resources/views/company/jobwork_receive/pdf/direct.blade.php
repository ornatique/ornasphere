<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Direct Jobwork Receive {{ $receive->receive_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 7mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; margin: 0; }
        h2 { text-align: center; margin: 0 0 6px; font-size: 15px; }
        .sub { text-align: center; font-weight: 700; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 3px; vertical-align: top; }
        th { background: #f2f2f2; font-weight: 700; text-align: center; line-height: 1.15; }
        .meta td { width: 50%; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .wrap { white-space: normal; word-break: break-word; }
        .total td { font-weight: 700; background: #f7f7f7; }
    </style>
</head>
<body>
@php
    $totalQty = 0;
    $totalGross = 0;
    $totalOther = 0;
    $totalNet = 0;
    $totalFine = 0;
    $totalMetal = 0;
    $totalLabour = 0;
    $totalOtherAmount = 0;
    $grandTotal = 0;
@endphp
<h2>{{ $company->name }}</h2>
<div class="sub">Direct Jobwork Receive</div>

<table class="meta">
    <tr>
        <td><strong>Receive No:</strong> {{ $receive->receive_no ?: 'Direct-' . $receive->id }}</td>
        <td><strong>Date & Time:</strong> {{ optional($receive->receive_date)->format('d-m-Y') ?? '-' }} / {{ optional($receive->created_at)->format('h:i A') ?? '-' }}</td>
    </tr>
    <tr>
        <td colspan="2"><strong>Customer:</strong> {{ $receive->customer?->name ?? $receive->jobWorker?->name ?? '-' }}</td>
    </tr>
</table>

<br>

<table>
    <thead>
        <tr>
            <th style="width:4%;" class="center">Sr</th>
            <th style="width:15%;">Item</th>
            <th style="width:5%;" class="num">Qty</th>
            <th style="width:7%;" class="num">Gross<br>Wt</th>
            <th style="width:7%;" class="num">Other<br>Wt</th>
            <th style="width:7%;" class="num">Net<br>Wt</th>
            <th style="width:7%;" class="num">Fine<br>Wt</th>
            <th style="width:8%;" class="num">Metal<br>Amt</th>
            <th style="width:8%;" class="num">Labour<br>Amt</th>
            <th style="width:8%;" class="num">Other<br>Amt</th>
            <th style="width:8%;" class="num">Total<br>Amt</th>
            <th style="width:16%;">Remarks</th>
        </tr>
    </thead>
    <tbody>
        @forelse($receive->items as $index => $item)
            @php
                $qty = (int) ($item->receive_qty_pcs ?? 0);
                $gross = (float) ($item->receive_gross_wt ?? 0);
                $other = (float) ($item->other_wt ?? 0);
                $net = (float) ($item->receive_net_wt ?? 0);
                $fine = (float) ($item->receive_fine_wt ?? 0);
                $metal = (float) ($item->metal_amount ?? 0);
                $labour = (float) ($item->labour_amount ?? 0);
                $otherAmount = (float) ($item->other_amt ?? 0);
                $totalAmount = (float) ($item->total_amount ?? ($metal + $labour + $otherAmount));
                $totalQty += $qty;
                $totalGross += $gross;
                $totalOther += $other;
                $totalNet += $net;
                $totalFine += $fine;
                $totalMetal += $metal;
                $totalLabour += $labour;
                $totalOtherAmount += $otherAmount;
                $grandTotal += $totalAmount;
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>
                    {{ trim(($item->item?->item_name ?? '-') . ($item->item?->item_code ? ' - ' . $item->item->item_code : '')) }}
                </td>
                <td class="num">{{ $qty }}</td>
                <td class="num">{{ number_format($gross, 3, '.', '') }}</td>
                <td class="num">{{ number_format($other, 3, '.', '') }}</td>
                <td class="num">{{ number_format($net, 3, '.', '') }}</td>
                <td class="num">{{ number_format($fine, 3, '.', '') }}</td>
                <td class="num">{{ number_format($metal, 2, '.', '') }}</td>
                <td class="num">{{ number_format($labour, 2, '.', '') }}</td>
                <td class="num">{{ number_format($otherAmount, 2, '.', '') }}</td>
                <td class="num">{{ number_format($totalAmount, 2, '.', '') }}</td>
                <td class="wrap">{{ $item->remarks ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="center">No receive rows found</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="2" class="num">Total</td>
            <td class="num">{{ $totalQty }}</td>
            <td class="num">{{ number_format($totalGross, 3, '.', '') }}</td>
            <td class="num">{{ number_format($totalOther, 3, '.', '') }}</td>
            <td class="num">{{ number_format($totalNet, 3, '.', '') }}</td>
            <td class="num">{{ number_format($totalFine, 3, '.', '') }}</td>
            <td class="num">{{ number_format($totalMetal, 2, '.', '') }}</td>
            <td class="num">{{ number_format($totalLabour, 2, '.', '') }}</td>
            <td class="num">{{ number_format($totalOtherAmount, 2, '.', '') }}</td>
            <td class="num">{{ number_format($grandTotal, 2, '.', '') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
