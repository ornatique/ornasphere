<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tree Cutting Issue Office {{ $voucher->voucher_no }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }
        .title { text-align: center; font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .company { text-align: center; font-size: 11px; font-weight: 700; margin-bottom: 12px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { width: 25%; padding: 4px 5px; border: 1px solid #444; vertical-align: top; }
        .label { font-weight: 700; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th, .items td { border: 1px solid #444; padding: 5px 4px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        .items th { background: #f0f0f0; font-size: 8px; font-weight: 700; line-height: 1.15; text-align: left; }
        .center { text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .items th.num { white-space: normal; }
        .total-row td { font-weight: 700; background: #f7f7f7; }
    </style>
</head>
<body>
    @php
        $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
        $pdfRows = [];
        $treeWtTotal = 0;
        $treeBhukoTotal = 0;
        $remainingTreeWtTotal = 0;

        foreach ($releaseItems as $rowKey => $releaseItem) {
            $officeItem = $officeItems->get($rowKey);
            $buchNo = (bool) $releaseItem->is_custom
                ? $releaseItem->custom_buch_no
                : ($releaseItem->voucherItem?->buch_no ?? $rowKey);
            $itemName = (bool) $releaseItem->is_custom ? null : $releaseItem->voucherItem?->item_name;
            $remarks = (bool) $releaseItem->is_custom ? null : $releaseItem->voucherItem?->remarks;
            $treeWt = (float) ($releaseItem->release_tree_wt ?? 0);
            $treeBhuko = (float) ($officeItem?->office_cut_wt ?? 0);
            $remainingTreeWt = max($treeWt - $treeBhuko, 0);
            $groupKey = $officeItem?->issue_group_key;
            $pdfRowKey = $groupKey ? 'group_' . $groupKey : 'item_' . $rowKey;

            if (!isset($pdfRows[$pdfRowKey])) {
                $pdfRows[$pdfRowKey] = [
                    'buch_nos' => [],
                    'item_names' => [],
                    'remarks' => [],
                    'tree_wt' => 0,
                    'tree_bhuko' => 0,
                    'remaining_tree_wt' => 0,
                ];
            }

            $pdfRows[$pdfRowKey]['buch_nos'][] = $buchNo;
            if ($itemName) {
                $pdfRows[$pdfRowKey]['item_names'][] = $itemName;
            }
            if ($remarks) {
                $pdfRows[$pdfRowKey]['remarks'][] = $remarks;
            }
            $pdfRows[$pdfRowKey]['tree_wt'] += $treeWt;
            $pdfRows[$pdfRowKey]['tree_bhuko'] += $treeBhuko;
            $pdfRows[$pdfRowKey]['remaining_tree_wt'] += $remainingTreeWt;
        }
    @endphp

    <div class="title">Tree Cutting Issue Office</div>
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
            <col style="width: 8%;">
            <col style="width: 14%;">
            <col style="width: 18%;">
            <col style="width: 18%;">
            <col style="width: 14%;">
            <col style="width: 14%;">
            <col style="width: 14%;">
        </colgroup>
        <thead>
            <tr>
                <th>Sr.<br>No</th>
                <th>B. No</th>
                <th>Item<br>Name</th>
                <th>Remarks</th>
                <th class="num">Tree<br>Wt</th>
                <th class="num">Tree<br>Bhuko</th>
                <th class="num">Remaining<br>Tree Wt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pdfRows as $row)
            @php
                $treeWtTotal += (float) ($row['tree_wt'] ?? 0);
                $treeBhukoTotal += (float) ($row['tree_bhuko'] ?? 0);
                $remainingTreeWtTotal += (float) ($row['remaining_tree_wt'] ?? 0);
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ implode(', ', $row['buch_nos']) }}</td>
                <td>{{ implode(', ', array_unique($row['item_names'] ?? [])) ?: '-' }}</td>
                <td>{{ implode(', ', array_unique($row['remarks'] ?? [])) ?: '-' }}</td>
                <td class="num">{{ number_format((float) ($row['tree_wt'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) ($row['tree_bhuko'] ?? 0), 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) ($row['remaining_tree_wt'] ?? 0), 3, '.', '') }}</td>
            </tr>
            @endforeach
            @if(count($pdfRows) === 0)
            <tr>
                <td colspan="7" class="center">No tree cutting office rows found</td>
            </tr>
            @endif
            @if(count($pdfRows) > 0)
            <tr class="total-row">
                <td colspan="4">Total</td>
                <td class="num">{{ number_format($treeWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($treeBhukoTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($remainingTreeWtTotal, 3, '.', '') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
