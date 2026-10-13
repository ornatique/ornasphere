<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Casting Metal Issue {{ $voucher->voucher_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 9mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
            margin: 0;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .company {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta td {
            width: 25%;
            padding: 4px 5px;
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
            padding: 4px 3px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .items th {
            background: #f0f0f0;
            font-size: 7.2px;
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

        .status {
            color: #fff;
            font-weight: 700;
            padding: 2px 5px;
        }

        .status-in {
            background: #16a34a;
        }

        .status-out {
            background: #dc2626;
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
        $silverWeightTotal = 0;
        $issueSilverWtTotal = 0;
        $pureFineTotal = 0;
        $otherMetalTotal = 0;
        $metalWeightTotal = 0;
    @endphp

    <div class="title">Casting Metal Issue</div>
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
            <col style="width: 4%;">
            <col style="width: 7%;">
            <col style="width: 11%;">
            <col style="width: 10%;">
            <col style="width: 8%;">
            <col style="width: 8%;">
            <col style="width: 4%;">
            <col style="width: 7%;">
            <col style="width: 8%;">
            <col style="width: 7%;">
            <col style="width: 8%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
        </colgroup>
        <thead>
            <tr>
                <th>Sr.<br>No</th>
                <th>B. No</th>
                <th>Item<br>Name</th>
                <th>Voucher<br>Remark</th>
                <th class="center">Status</th>
                <th class="num">Silver<br>Wt</th>
                <th class="center">I/F</th>
                <th class="num">Melting<br>%</th>
                <th class="num">Pure<br>Fine</th>
                <th class="num">O/M</th>
                <th class="num">Metal<br>Wt</th>
                <th class="num">Issue<br>Silver Wt</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucher->items as $item)
            @php
                $heatingItem = $heatingItems->get($item->id);
                $issueItem = $issueItems->get($item->id);
                $inBhati = (bool) ($heatingItem?->in_bhati);
                $issueSilverWt = $issueItem?->issue_silver_wt;
                $isIf = (bool) ($issueItem?->is_if);
                $melting = $issueItem?->if_percentage;
                $pureFine = $issueItem?->pure_fine;
                $otherMetal = $issueItem?->other_metal;
                $metalWeight = $issueItem?->metal_weight;
                $silverWeightTotal += (float) $item->silver_wt;
                $issueSilverWtTotal += $issueSilverWt !== null ? (float) $issueSilverWt : 0;
                $pureFineTotal += $pureFine !== null ? (float) $pureFine : 0;
                $otherMetalTotal += $otherMetal !== null ? (float) $otherMetal : 0;
                $metalWeightTotal += $metalWeight !== null ? (float) $metalWeight : 0;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->buch_no }}</td>
                <td>{{ $item->item_name ?: '-' }}</td>
                <td>{{ $item->remarks ?: '-' }}</td>
                <td class="center">
                    <span class="status {{ $inBhati ? 'status-in' : 'status-out' }}">
                        {{ $inBhati ? 'In Bhati' : 'Not In Bhati' }}
                    </span>
                </td>
                <td class="num">{{ number_format((float) $item->silver_wt, 3, '.', '') }}</td>
                <td class="center">{{ $isIf ? 'Yes' : 'No' }}</td>
                <td class="num">{{ $melting !== null ? number_format((float) $melting, 2, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $pureFine !== null ? number_format((float) $pureFine, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $otherMetal !== null ? number_format((float) $otherMetal, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $metalWeight !== null ? number_format((float) $metalWeight, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $issueSilverWt !== null ? number_format((float) $issueSilverWt, 3, '.', '') : '-' }}</td>
                <td>{{ $issueItem?->remarks ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="13" class="center">No Buch rows found</td>
            </tr>
            @endforelse
            @if($voucher->items->count() > 0)
            <tr class="total-row">
                <td colspan="5">Total</td>
                <td class="num">{{ number_format($silverWeightTotal, 3, '.', '') }}</td>
                <td></td>
                <td></td>
                <td class="num">{{ number_format($pureFineTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($otherMetalTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($metalWeightTotal, 3, '.', '') }}</td>
                <td class="num">{{ number_format($issueSilverWtTotal, 3, '.', '') }}</td>
                <td></td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
