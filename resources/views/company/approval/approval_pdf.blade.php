<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
        }

        .sheet {
            width: 100%;
            border: 1px solid #000;
        }

        .sheet-title {
            border-bottom: 1px solid #000;
            padding: 6px 8px;
            font-weight: 700;
            font-size: 14px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #000;
        }

        .meta td {
            border-right: 1px solid #000;
            padding: 4px 6px;
            vertical-align: top;
        }

        .meta td:last-child {
            border-right: none;
        }

        .voucher-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .voucher-grid th,
        .voucher-grid td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .voucher-grid th {
            background: #efefef;
            font-weight: 700;
            text-align: center;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .grand-total {
            text-align: right;
            font-weight: 700;
            padding: 6px 8px 8px;
        }
    </style>
</head>
<body>

@php
    $name = optional($approval->customer)->name ?? '-';
    $city = optional($approval->customer)->city ?? '-';
    $contact = optional($approval->customer)->mobile ?? optional($approval->customer)->phone ?? '-';

    $billableItems = $approval->items->where('status', '!=', 'returned');
    $sumGross = 0; $sumLess = 0; $sumNet = 0; $sumOther = 0; $sumTotal = 0;
@endphp

<div class="sheet">
    <div class="sheet-title">Approval Estimate</div>

    <table class="meta">
        <tr>
            <td style="width:56%;">
                <div><strong>Name</strong> : {{ $name }}</div>
                <div style="margin-top:4px;"><strong>City</strong> : {{ $city }}</div>
            </td>
            <td style="width:44%;">
                <div><strong>Estimate No</strong> : {{ $approval->approval_no }}</div>
                <div style="margin-top:4px;"><strong>Date</strong> : {{ optional($approval->approval_date)->format('d-m-Y') ?? \Carbon\Carbon::parse($approval->approval_date)->format('d-m-Y') }}</div>
                <div style="margin-top:4px;"><strong>Contact No</strong> : {{ $contact }}</div>
            </td>
        </tr>
    </table>

    <table class="voucher-grid">
        <thead>
            <tr>
                <th style="width:4%;">Sr</th>
                <th style="width:30%; text-align:left;">Item</th>
                <th style="width:6%;">Carat</th>
                <th style="width:5%;">Qty</th>
                <th style="width:9%;">Gross Wt</th>
                <th style="width:9%;">Less Wt</th>
                <th style="width:9%;">Net Wt</th>
                <th style="width:8%;">Rate</th>
                <th style="width:10%;">Labour Rate</th>
                <th style="width:10%;">Other</th>
                <th style="width:10%;">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($billableItems as $index => $row)
                @php
                    $itemSet = $row->itemSet ?? $row->legacyItemSet;
                    $item = optional($itemSet)->item;
                    $labelCode = $row->qr_code ?? optional($itemSet)->qr_code ?? '';
                    $itemName = optional($item)->item_name ?? '-';
                    $itemDisplay = trim(($labelCode ? ($labelCode . ' - ') : '') . $itemName);
                    $carat = (float) (optional($item)->outward_carat ?? 0);
                    $qty = 1;
                    $gross = (float) ($row->gross_weight ?? 0);
                    $less = (float) ($row->other_weight ?? 0);
                    $net = (float) ($row->net_weight ?? ($gross - $less));
                    $rate = (float) ($row->metal_rate ?? 0);
                    $labourRate = (float) ($row->labour_rate ?? 0);
                    $other = (float) ($row->other_amount ?? 0);
                    $total = (float) ($row->total_amount ?? 0);

                    $sumGross += $gross;
                    $sumLess += $less;
                    $sumNet += $net;
                    $sumOther += $other;
                    $sumTotal += $total;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $itemDisplay }}</td>
                    <td class="text-right">{{ number_format($carat, 0) }}</td>
                    <td class="text-center">{{ $qty }}</td>
                    <td class="text-right">{{ number_format($gross, 3) }}</td>
                    <td class="text-right">{{ number_format($less, 3) }}</td>
                    <td class="text-right">{{ number_format($net, 3) }}</td>
                    <td class="text-right">{{ number_format($rate, 2) }}</td>
                    <td class="text-right">{{ number_format($labourRate, 2) }}</td>
                    <td class="text-right">{{ number_format($other, 2) }}</td>
                    <td class="text-right">{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
            @if($billableItems->isEmpty())
                <tr>
                    <td colspan="11" class="text-center">No billable items</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">Total</th>
                <th class="text-right">{{ number_format($sumGross, 3) }}</th>
                <th class="text-right">{{ number_format($sumLess, 3) }}</th>
                <th class="text-right">{{ number_format($sumNet, 3) }}</th>
                <th></th>
                <th></th>
                <th class="text-right">{{ number_format($sumOther, 2) }}</th>
                <th class="text-right">{{ number_format($sumTotal, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="grand-total">Grand Total : {{ number_format($sumTotal, 2) }}</div>
</div>

</body>
</html>
