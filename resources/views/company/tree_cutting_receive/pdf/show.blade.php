<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tree Cutting Receive {{ $voucher->voucher_no }}</title>
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
        $issueTreeWtTotal = 0;
        $receivePcWtTotal = 0;
        $receiveTreeBhukoTotal = 0;
        $lossTotal = 0;
        $rowCount = 0;
    @endphp

    <div class="title">Tree Cutting Receive</div>
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
                <th style="width: 8%;">Sr. No</th>
                <th style="width: 15%;">B. No</th>
                <th style="width: 19%;">Worker</th>
                <th class="num" style="width: 15%;">Issue Tree Wt</th>
                <th class="num" style="width: 15%;">Receive Pc Wt</th>
                <th class="num" style="width: 16%;">Receive Tree Bhuko</th>
                <th class="num" style="width: 12%;">Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($issueItems as $issueItem)
            @php
                $receiveItem = $receiveItems->get($issueItem->id);
                $buchNo = $issueItem->is_custom ? $issueItem->custom_buch_no : ($issueItem->voucherItem?->buch_no ?? '-');
                $issueTreeWt = (float) ($issueItem->receive_tree_wt ?? 0);
                $receivePcWt = $receiveItem?->receive_pc_wt;
                $receiveTreeBhuko = $receiveItem?->receive_tree_bhuko;
                $loss = $receiveItem?->loss;
                $issueTreeWtTotal += $issueTreeWt;
                $receivePcWtTotal += $receivePcWt !== null ? (float) $receivePcWt : 0;
                $receiveTreeBhukoTotal += $receiveTreeBhuko !== null ? (float) $receiveTreeBhuko : 0;
                $lossTotal += $loss !== null ? (float) $loss : 0;
                $rowCount++;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $buchNo }}</td>
                <td>{{ $issueItem->jobWorker?->name ?? '-' }}</td>
                <td class="num">{{ number_format($issueTreeWt, 3, '.', '') }}</td>
                <td class="num">{{ $receivePcWt !== null ? number_format((float) $receivePcWt, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $receiveTreeBhuko !== null ? number_format((float) $receiveTreeBhuko, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $loss !== null ? number_format((float) $loss, 3, '.', '') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="center">No tree cutting issue rows found</td>
            </tr>
            @endforelse
            @if($rowCount > 0)
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($issueTreeWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($receivePcWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($receiveTreeBhukoTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($lossTotal, 3, '.', '') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
