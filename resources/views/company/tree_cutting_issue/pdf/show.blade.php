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
        $officeCutWtTotal = 0;
        $receiveTreeWtTotal = 0;
        $issuePdfRows = [];

        foreach ($voucher->items as $item) {
            $receiveItem = $receiveItems->get($item->id);
            if (!$receiveItem) {
                continue;
            }

            $treeCuttingItem = $treeCuttingItems->get($item->id);
            $groupKey = $treeCuttingItem?->issue_group_key;
            $rowKey = $groupKey ? 'group_' . $groupKey : 'item_' . $item->id;
            $officeCutWt = (float) ($receiveItem->office_cut_wt ?? 0);
            $receiveTreeWt = (float) ($receiveItem->remaining_tree_wt ?? $receiveItem->release_tree_wt ?? 0);

            if (!isset($issuePdfRows[$rowKey])) {
                $issuePdfRows[$rowKey] = [
                    'buch_nos' => [],
                    'office_cut_wt' => 0,
                    'receive_tree_wt' => 0,
                    'worker_name' => $treeCuttingItem?->jobWorker?->name ?? '-',
                ];
            }

            $issuePdfRows[$rowKey]['buch_nos'][] = $item->buch_no;
            $issuePdfRows[$rowKey]['office_cut_wt'] += $officeCutWt;
            $issuePdfRows[$rowKey]['receive_tree_wt'] += $receiveTreeWt;
        }

        foreach ($customTreeCuttingItems as $customItem) {
            $issuePdfRows['custom_' . $customItem->id] = [
                'buch_nos' => [$customItem->custom_buch_no ?: '-'],
                'office_cut_wt' => 0,
                'receive_tree_wt' => (float) ($customItem->receive_tree_wt ?? 0),
                'worker_name' => $customItem->jobWorker?->name ?? '-',
            ];
        }
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
            <td><span class="label">Office Cut Tree Wt:</span><br>{{ number_format(array_sum(array_column($issuePdfRows, 'office_cut_wt')), 3, '.', '') }}</td>
            <td><span class="label">Issue Tree Wt:</span><br>{{ number_format(array_sum(array_column($issuePdfRows, 'receive_tree_wt')), 3, '.', '') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 10%;">Sr. No</th>
                <th style="width: 28%;">B. No</th>
                <th class="num" style="width: 20%;">Office Cut Wt</th>
                <th class="num" style="width: 22%;">Receive Tree Wt</th>
                <th style="width: 20%;">Worker</th>
            </tr>
        </thead>
        <tbody>
            @foreach($issuePdfRows as $row)
            @php
                $officeCutWtTotal += (float) ($row['office_cut_wt'] ?? 0);
                $receiveTreeWtTotal += (float) ($row['receive_tree_wt'] ?? 0);
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ implode(', ', $row['buch_nos']) }}</td>
                <td class="num">{{ number_format((float) ($row['office_cut_wt'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) ($row['receive_tree_wt'] ?? 0), 3, '.', '') }}</td>
                <td>{{ $row['worker_name'] }}</td>
            </tr>
            @endforeach
            @if(count($issuePdfRows) === 0)
            <tr>
                <td colspan="5" class="center">No tree cutting issue rows found</td>
            </tr>
            @endif
            @if(count($issuePdfRows) > 0)
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num">{{ number_format($officeCutWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($receiveTreeWtTotal, 3, '.', '') }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
