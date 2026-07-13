<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tree Cutting Issue {{ $voucher->voucher_no }}</title>
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
        $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
        $receiveTreeWtTotal = 0;
        $rowCount = 0;
    @endphp

    <div class="title">Tree Cutting Issue</div>
    <div class="company">{{ $company->name }}</div>

    <table class="meta">
        <tr>
            <td><span class="label">Voucher No:</span><br>{{ $voucher->voucher_no }}</td>
            <td><span class="label">Date:</span><br>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
            <td><span class="label">Process:</span><br>{{ $voucher->process?->name ?? '-' }}</td>
            <td><span class="label">Worker:</span><br>{{ $voucher->jobWorker?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Total Pcs:</span><br>{{ $totalPcs }}</td>
            <td><span class="label">Created At:</span><br>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed At:</span><br>{{ now()->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed By:</span><br>{{ auth()->user()->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 10%;">Sr. No</th>
                <th style="width: 30%;">B. No</th>
                <th class="num" style="width: 25%;">Receive Tree Wt</th>
                <th style="width: 35%;">Worker</th>
            </tr>
        </thead>
        <tbody>
            @foreach($voucher->items as $item)
            @php
                $receiveItem = $receiveItems->get($item->id);
                if (!$receiveItem) {
                    continue;
                }
                $treeCuttingItem = $treeCuttingItems->get($item->id);
                $receiveTreeWt = $treeCuttingItem?->receive_tree_wt ?? $receiveItem?->release_tree_wt;
                $workerName = $treeCuttingItem?->jobWorker?->name ?? $voucher->jobWorker?->name ?? '-';
                $receiveTreeWtTotal += $receiveTreeWt !== null ? (float) $receiveTreeWt : 0;
                $rowCount++;
            @endphp
            <tr>
                <td>{{ $rowCount }}</td>
                <td>{{ $item->buch_no }}</td>
                <td class="num">{{ $receiveTreeWt !== null ? number_format((float) $receiveTreeWt, 3, '.', '') : '-' }}</td>
                <td>{{ $workerName }}</td>
            </tr>
            @endforeach
            @foreach($customTreeCuttingItems as $customItem)
            @php
                $receiveTreeWt = $customItem->receive_tree_wt;
                $receiveTreeWtTotal += $receiveTreeWt !== null ? (float) $receiveTreeWt : 0;
                $rowCount++;
            @endphp
            <tr>
                <td>{{ $rowCount }}</td>
                <td>{{ $customItem->custom_buch_no ?: '-' }}</td>
                <td class="num">{{ $receiveTreeWt !== null ? number_format((float) $receiveTreeWt, 3, '.', '') : '-' }}</td>
                <td>{{ $customItem->jobWorker?->name ?? '-' }}</td>
            </tr>
            @endforeach
            @if($rowCount === 0)
            <tr>
                <td colspan="4" class="center">No tree cutting issue rows found</td>
            </tr>
            @endif
            @if($rowCount > 0)
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num">{{ number_format($receiveTreeWtTotal, 3, '.', '') }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
