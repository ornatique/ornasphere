<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Casting Sorting {{ $voucher->voucher_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 18px; }
        .title { text-align: center; font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .company { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { width: 25%; padding: 4px 6px; border: 1px solid #444; vertical-align: top; }
        .label { font-weight: 700; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th, .items td { border: 1px solid #444; padding: 6px; vertical-align: top; word-wrap: break-word; }
        .items th { background: #f0f0f0; font-weight: 700; text-align: left; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .total-row td { font-weight: 700; background: #f7f7f7; }
    </style>
</head>
<body>
    @php
        $weightTotal = 0;
        $quantityTotal = 0;
    @endphp

    <div class="title">Casting Sorting</div>
    <div class="company">{{ $company->name }}</div>

    <table class="meta">
        <tr>
            <td><span class="label">Voucher No:</span><br>{{ $voucher->voucher_no }}</td>
            <td><span class="label">Date:</span><br>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
            <td><span class="label">Process:</span><br>{{ $voucher->process?->name ?? '-' }}</td>
            <td><span class="label">Worker:</span><br>{{ $voucher->jobWorker?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Total Pcs:</span><br>{{ (int) $treeReceiveCount }}</td>
            <td><span class="label">Created At:</span><br>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed At:</span><br>{{ now()->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed By:</span><br>{{ auth()->user()->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 10%;">Sr. No</th>
                <th style="width: 50%;">Item Selected</th>
                <th class="num" style="width: 20%;">Weight</th>
                <th class="num" style="width: 20%;">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sortingItems as $sortingItem)
            @php
                $weight = $sortingItem->weight;
                $quantity = $sortingItem->quantity;
                $weightTotal += $weight !== null ? (float) $weight : 0;
                $quantityTotal += $quantity !== null ? (int) $quantity : 0;
                $itemName = $sortingItem->item?->item_name ?? '-';
                $itemCode = $sortingItem->item?->item_code;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $itemName }}{{ $itemCode ? ' - ' . $itemCode : '' }}</td>
                <td class="num">{{ $weight !== null ? number_format((float) $weight, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $quantity !== null ? (int) $quantity : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="center">No casting sorting rows found</td>
            </tr>
            @endforelse
            @if($sortingItems->isNotEmpty())
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num">{{ number_format($weightTotal, 3, '.', '') }}</td>
                <td class="num">{{ $quantityTotal }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
