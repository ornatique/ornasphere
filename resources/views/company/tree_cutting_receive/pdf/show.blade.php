<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tree Cutting Receive {{ $voucher->voucher_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 9mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; margin: 0; }
        .title { text-align: center; font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .company { text-align: center; font-size: 11px; font-weight: 700; margin-bottom: 12px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { width: 25%; padding: 4px 5px; border: 1px solid #444; vertical-align: top; }
        .label { font-weight: 700; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th, .items td { border: 1px solid #444; padding: 4px 3px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        .items th { background: #f0f0f0; font-size: 7.2px; font-weight: 700; line-height: 1.1; text-align: left; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .items th.num { text-align: right; white-space: normal; }
        .total-row td { font-weight: 700; background: #f7f7f7; }
    </style>
</head>
<body>
    @php
        $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
        $officeCutWtTotal = 0;
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
        <colgroup>
            <col style="width: 5%;">
            <col style="width: 9%;">
            <col style="width: 13%;">
            <col style="width: 13%;">
            <col style="width: 11%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
            <col style="width: 11%;">
            <col style="width: 8%;">
        </colgroup>
        <thead>
            <tr>
                <th>Sr.<br>No</th>
                <th>B. No</th>
                <th>Item<br>Name</th>
                <th>Remarks</th>
                <th>Worker</th>
                <th class="num">Office<br>Cut</th>
                <th class="num">Issue<br>Tree</th>
                <th class="num">Receive<br>Pc</th>
                <th class="num">Tree<br>Bhuko</th>
                <th class="num">Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($issueItems as $issueItem)
            @php
                $receiveItem = $receiveItems->get($issueItem->id);
                $officeCutWt = (float) ($issueItem->office_cut_wt ?? 0);
                $issueTreeWt = (float) ($issueItem->receive_tree_wt ?? 0);
                $receivePcWt = $receiveItem?->receive_pc_wt;
                $receiveTreeBhuko = $receiveItem?->receive_tree_bhuko;
                $loss = $receiveItem?->loss;
                $officeCutWtTotal += $officeCutWt;
                $issueTreeWtTotal += $issueTreeWt;
                $receivePcWtTotal += $receivePcWt !== null ? (float) $receivePcWt : 0;
                $receiveTreeBhukoTotal += $receiveTreeBhuko !== null ? (float) $receiveTreeBhuko : 0;
                $lossTotal += $loss !== null ? (float) $loss : 0;
                $rowCount++;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $issueItem->buch_no }}</td>
                <td>{{ $issueItem->item_name ?: '-' }}</td>
                <td>{{ $issueItem->remarks ?: '-' }}</td>
                <td>{{ $issueItem->jobWorker?->name ?? '-' }}</td>
                <td class="num">{{ number_format($officeCutWt, 3, '.', '') }}</td>
                <td class="num">{{ number_format($issueTreeWt, 3, '.', '') }}</td>
                <td class="num">{{ $receivePcWt !== null ? number_format((float) $receivePcWt, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $receiveTreeBhuko !== null ? number_format((float) $receiveTreeBhuko, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $loss !== null ? number_format((float) $loss, 3, '.', '') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="center">No tree cutting issue rows found</td>
            </tr>
            @endforelse
            @foreach($customReceiveItems ?? [] as $customItem)
            @php
                $receiveTreeBhuko = $customItem->receive_tree_bhuko;
                $loss = $customItem->loss;
                $receiveTreeBhukoTotal += $receiveTreeBhuko !== null ? (float) $receiveTreeBhuko : 0;
                $lossTotal += $loss !== null ? (float) $loss : 0;
                $rowCount++;
            @endphp
            <tr>
                <td>{{ $rowCount }}</td>
                <td>{{ $customItem->custom_buch_no ?: 'Custom' }}</td>
                <td>-</td>
                <td>-</td>
                <td>Custom</td>
                <td class="num">0.000</td>
                <td class="num">0.000</td>
                <td class="num">-</td>
                <td class="num">{{ $receiveTreeBhuko !== null ? number_format((float) $receiveTreeBhuko, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $loss !== null ? number_format((float) $loss, 3, '.', '') : '-' }}</td>
            </tr>
            @endforeach
            @if($rowCount > 0)
            <tr class="total-row">
                <td colspan="5">Total</td>
                <td class="num">{{ number_format($officeCutWtTotal, 3, '.', '') }}</td>
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
