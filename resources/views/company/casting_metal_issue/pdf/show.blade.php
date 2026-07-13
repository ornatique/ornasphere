<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Casting Metal Issue {{ $voucher->voucher_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 18px;
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
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: left;
        }

        .center {
            text-align: center;
        }

        .num {
            text-align: right;
            white-space: nowrap;
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
        <thead>
            <tr>
                <th style="width: 5%;">Sr. No</th>
                <th style="width: 9%;">B. No</th>
                <th class="center" style="width: 11%;">Status</th>
                <th class="num" style="width: 11%;">Silver Weight</th>
                <th class="center" style="width: 5%;">I/F</th>
                <th class="num" style="width: 11%;">Pure Fine</th>
                <th class="num" style="width: 7%;">%</th>
                <th class="num" style="width: 10%;">O/M</th>
                <th class="num" style="width: 11%;">Metal Weight</th>
                <th class="num" style="width: 11%;">Issue Silver Wt</th>
                <th style="width: 9%;">Remark</th>
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
                $pureFine = $issueItem?->pure_fine;
                $ifPercentage = $issueItem?->if_percentage;
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
                <td class="center">
                    <span class="status {{ $inBhati ? 'status-in' : 'status-out' }}">
                        {{ $inBhati ? 'In Bhati' : 'Not In Bhati' }}
                    </span>
                </td>
                <td class="num">{{ number_format((float) $item->silver_wt, 3, '.', '') }}</td>
                <td class="center">{{ $isIf ? 'Yes' : 'No' }}</td>
                <td class="num">{{ $isIf && $pureFine !== null ? number_format((float) $pureFine, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $ifPercentage !== null ? number_format((float) $ifPercentage, 2, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $otherMetal !== null ? number_format((float) $otherMetal, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $isIf && $metalWeight !== null ? number_format((float) $metalWeight, 3, '.', '') : '-' }}</td>
                <td class="num">{{ $issueSilverWt !== null ? number_format((float) $issueSilverWt, 3, '.', '') : '-' }}</td>
                <td>{{ $issueItem?->remarks ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="11" class="center">No Buch rows found</td>
            </tr>
            @endforelse
            @if($voucher->items->count() > 0)
            <tr class="total-row">
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($silverWeightTotal, 3, '.', '') }}</td>
                <td></td>
                <td class="num">{{ number_format($pureFineTotal, 3, '.', '') }}</td>
                <td></td>
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
