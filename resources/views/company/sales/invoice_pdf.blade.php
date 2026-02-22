<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; font-size: 11px; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #000; padding:5px; }
        .text-right { text-align:right; }
        .no-border td { border:none; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>

<h3 style="text-align:center;">Estimate</h3>

<table class="no-border">
<tr>
    <td><strong>Name:</strong> {{ $sale->customer->name }}</td>
    <td class="text-right"><strong>Estimate No:</strong> {{ $sale->voucher_no }}</td>
</tr>
<tr>
    <td><strong>Date:</strong> {{ \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y') }}</td>
    <td></td>
</tr>
</table>

<br>

@php
    $totalQty = 0;
    $taxableAmount = 0;
@endphp

<table>
<thead>
<tr>
    <th>#</th>
    <th>Item</th>
    <th>Qty</th>
    <th>Gross</th>
    <th>Net</th>
    <th>Purity</th>
    <th>Total</th>
</tr>
</thead>

<tbody>
@foreach($sale->saleItems as $key => $item)

@php
    $qty = $item->quantity ?? 1; // default 1 if not stored
    $totalQty += $qty;
    $taxableAmount += $item->total_amount;
@endphp

<tr>
    <td>{{ $key+1 }}</td>
    <td>{{ $item->itemset->item->item_name }} - {{ $item->itemset->qr_code }}</td>
    <td>{{ $qty }}</td>
    <td>{{ $item->gross_weight }}</td>
    <td>{{ $item->net_weight }}</td>
    <td>{{ $item->purity }}</td>
    <td class="text-right">{{ number_format($item->total_amount,2) }}</td>
</tr>

@endforeach
</tbody>

<tfoot>
<tr class="bold">
    <td colspan="2" class="text-right">Total</td>
    <td>{{ $totalQty }}</td>
    <td colspan="3"></td>
    <td class="text-right">{{ number_format($taxableAmount,2) }}</td>
</tr>
</tfoot>

</table>

<br>

<table width="40%" align="right">
<tr>
    <td class="text-right"><strong>Taxable Amount :</strong></td>
    <td class="text-right">{{ number_format($taxableAmount,2) }}</td>
</tr>
<tr>
    <td class="text-right"><strong>Total Amount :</strong></td>
    <td class="text-right">₹ {{ number_format($sale->net_total,2) }}</td>
</tr>
</table>

</body>
</html>