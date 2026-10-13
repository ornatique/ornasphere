<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Casting Receive {{ $voucher->voucher_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .company {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta td {
            width: 25%;
            padding: 4px 6px;
            border: 1px solid #444;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th,
        .items td {
            border: 1px solid #444;
            padding: 5px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .items th {
            background: #f0f0f0;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.15;
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .items th.num {
            white-space: normal;
        }

        .total-row td {
            font-weight: 700;
            background: #f7f7f7;
        }
    </style>
</head>
<body>
    @php
        $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
        $issueSilverWtTotal = 0;
        $releaseTreeWtTotal = 0;
        $releaseTreeBhukoTotal = 0;
        $lossTotal = 0;
        $releaseRowCount = 0;
    @endphp

    <div class="title">Casting Receive</div>
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
            <col style="width: 6%;">
            <col style="width: 10%;">
            <col style="width: 16%;">
            <col style="width: 16%;">
            <col style="width: 13%;">
            <col style="width: 13%;">
            <col style="width: 16%;">
            <col style="width: 10%;">
        </colgroup>
        <thead>
            <tr>
                <th>Sr.<br>No</th>
                <th>B. No</th>
                <th>Item<br>Name</th>
                <th>Voucher<br>Remark</th>
                <th class="num">Issue<br>Silver Wt</th>
                <th class="num">Release<br>Tree Wt</th>
                <th class="num">Release Tree<br>Bhuko</th>
                <th class="num">Loss</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucher->items as $item)
            @php
                $issueItem = $issueItems->get($item->id);
                if (!$issueItem) {
                    continue;
                }
                $releaseItem = $releaseItems->get($item->id);
                $issueSilverWt = (float) ($issueItem->issue_silver_wt ?? 0);
                $releaseTreeWt = $releaseItem?->release_tree_wt;
                $releaseTreeBhuko = $releaseItem?->release_tree_bhuko;
                $loss = $releaseItem?->loss;
                $issueSilverWtTotal += $issueSilverWt;
                $releaseTreeWtTotal += $releaseTreeWt !== null ? (float) $releaseTreeWt : 0;
                $releaseTreeBhukoTotal += $releaseTreeBhuko !== null ? (float) $releaseTreeBhuko : 0;
                $lossTotal += $loss !== null ? (float) $loss : 0;
                $releaseRowCount++;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->buch_no }}</td>
                <td>{{ $item->item_name ?: '-' }}</td>
                <td>{{ $item->remarks ?: '-' }}</td>
                <td class="num">{{ number_format($issueSilverWt, 3, '.', '') }}</td>
                <td class="num">{{ $releaseTreeWt !== null ? number_format((float) $releaseTreeWt, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $releaseTreeBhuko !== null ? number_format((float) $releaseTreeBhuko, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $loss !== null ? number_format((float) $loss, 3, '.', '') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="center">No casting receive rows found</td>
            </tr>
            @endforelse
            @foreach($customReleaseItems ?? [] as $customItem)
            @php
                $releaseTreeWt = $customItem->release_tree_wt;
                $releaseTreeBhuko = $customItem->release_tree_bhuko;
                $loss = $customItem->loss;
                $releaseTreeWtTotal += $releaseTreeWt !== null ? (float) $releaseTreeWt : 0;
                $releaseTreeBhukoTotal += $releaseTreeBhuko !== null ? (float) $releaseTreeBhuko : 0;
                $lossTotal += $loss !== null ? (float) $loss : 0;
                $releaseRowCount++;
            @endphp
            <tr>
                <td>{{ $releaseRowCount }}</td>
                <td>{{ $customItem->custom_buch_no ?: 'Custom' }}</td>
                <td>-</td>
                <td>-</td>
                <td class="num">0.000</td>
                <td class="num">{{ $releaseTreeWt !== null ? number_format((float) $releaseTreeWt, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $releaseTreeBhuko !== null ? number_format((float) $releaseTreeBhuko, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $loss !== null ? number_format((float) $loss, 3, '.', '') : '-' }}</td>
            </tr>
            @endforeach
            @if($releaseRowCount > 0)
            <tr class="total-row">
                <td colspan="4">Total</td>
                <td class="num">{{ number_format($issueSilverWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($releaseTreeWtTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($releaseTreeBhukoTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($lossTotal, 3, '.', '') }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
