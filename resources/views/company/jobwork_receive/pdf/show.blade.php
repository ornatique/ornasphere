<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Receive {{ $row->voucher_no }}</title>
    <style>
        @page {
            margin: 8px;
        }

        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; margin: 0; }
        h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
        h4 { text-align: center; margin: 0 0 6px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 3px 4px; vertical-align: top; }
        th { background: #eee; font-weight: 700; }
        .meta td { width: 25%; }
        .right { text-align: right; }
        .total td { font-weight: bold; background: #f2f2f2; }
        .mb { margin-bottom: 8px; }
        .items th:nth-child(1), .items td:nth-child(1) { width: 6%; }
        .items th:nth-child(2), .items td:nth-child(2) { width: 14%; }
        .items th:nth-child(3), .items td:nth-child(3) { width: 11%; }
        .items th:nth-child(4), .items td:nth-child(4) { width: 13%; }
        .items th:nth-child(5), .items td:nth-child(5) { width: 10%; }
        .items th:nth-child(6), .items td:nth-child(6) { width: 12%; }
        .items th:nth-child(7), .items td:nth-child(7) { width: 12%; }
        .items th:nth-child(8), .items td:nth-child(8) { width: 8%; }
        .items th:nth-child(9), .items td:nth-child(9) { width: 12%; }
        .items th:nth-child(10), .items td:nth-child(10) { width: 10%; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $totalReceiveGross = 0;
    $totalOtherWt = 0;
    $totalReceiveNet = 0;
    $totalReceiveFine = 0;
    $totalReceiveQty = 0;
    $totalIssueNet = (float) ($row->net_wt_sum ?? 0);
@endphp
<h2>Jobwork Receive</h2>
<h4>{{ $company->name }}</h4>

<table class="meta mb">
    <tr>
        <td><strong>Voucher No:</strong><br>{{ $row->voucher_no }}</td>
        <td><strong>Issue Date:</strong><br>{{ optional($row->jobwork_date)->format('d-m-Y') }}</td>
        <td><strong>Receive Date:</strong><br>{{ optional($receive->receive_date)->format('d-m-Y') }}</td>
        <td><strong>Jobworker:</strong><br>{{ $row->jobWorker?->name ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Production Step:</strong><br>{{ $row->productionStep?->name ?? '-' }}</td>
        <td><strong>Issue Net Wt:</strong><br>{{ number_format($totalIssueNet, 3, '.', '') }}</td>
        <td><strong>Printed At:</strong><br>{{ now()->format('d-m-Y h:i A') }}</td>
        <td><strong>Printed By:</strong><br>{{ auth()->user()->name ?? '-' }}</td>
    </tr>
</table>

<table class="meta mb">
    <tr>
        <td><strong>Voucher Name:</strong><br>{{ $row->voucher_no }}</td>
        <td colspan="3"><strong>Worker Vouchers:</strong><br>{{ $workerIssueVouchers->pluck('voucher_no')->implode(', ') ?: '-' }}</td>
    </tr>
</table>

<h4>Jobwork Receive</h4>
<table class="items">
    <thead>
        <tr>
            <th>Sr. No</th>
            <th>Item</th>
            <th class="right">Issue Net</th>
            <th class="right">Receive Gross</th>
            <th class="right">Other Wt</th>
            <th class="right">Receive Net</th>
            <th class="right">Receive Fine</th>
            <th class="right">Qty</th>
            <th class="right">Pending Net</th>
            <th>Remark</th>
        </tr>
    </thead>
    <tbody>
        @foreach($receive->items as $index => $saved)
            @php
                $issueItem = $saved->jobworkIssueItem;
                $item = $saved->item ?: $issueItem?->item;
                $receiveGross = (float) ($saved?->receive_gross_wt ?? 0);
                $receiveNet = (float) ($saved?->receive_net_wt ?? 0);
                $otherWt = (float) ($saved?->other_wt ?? max(0, $receiveGross - $receiveNet));
                $receiveFine = (float) ($saved?->receive_fine_wt ?? 0);
                $receiveQty = (int) ($saved?->receive_qty_pcs ?? 0);
                $pendingNet = max(0, (float) ($saved?->loss_wt ?? ((float) ($issueItem->net_wt ?? 0) - $receiveNet)));
                $totalReceiveGross += $receiveGross;
                $totalOtherWt += $otherWt;
                $totalReceiveNet += $receiveNet;
                $totalReceiveFine += $receiveFine;
                $totalReceiveQty += $receiveQty;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item?->item_name ?? '-' }}</td>
                <td class="right">{{ number_format((float) ($issueItem->net_wt ?? 0), 3, '.', '') }}</td>
                <td class="right">{{ number_format($receiveGross, 3, '.', '') }}</td>
                <td class="right">{{ number_format($otherWt, 3, '.', '') }}</td>
                <td class="right">{{ number_format($receiveNet, 3, '.', '') }}</td>
                <td class="right">{{ number_format($receiveFine, 3, '.', '') }}</td>
                <td class="right">{{ $receiveQty }}</td>
                <td class="right">{{ number_format($pendingNet, 3, '.', '') }}</td>
                <td>{{ $saved?->remarks ?? '' }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="2" class="right">Total</td>
            <td class="right">{{ number_format($totalIssueNet, 3, '.', '') }}</td>
            <td class="right">{{ number_format($totalReceiveGross, 3, '.', '') }}</td>
            <td class="right">{{ number_format($totalOtherWt, 3, '.', '') }}</td>
            <td class="right">{{ number_format($totalReceiveNet, 3, '.', '') }}</td>
            <td class="right">{{ number_format($totalReceiveFine, 3, '.', '') }}</td>
            <td class="right">{{ $totalReceiveQty }}</td>
            <td class="right">
                {{ number_format(max(0, $totalIssueNet - $totalReceiveNet), 3, '.', '') }}
                @if($totalReceiveNet > $totalIssueNet)
                    <br>Extra: {{ number_format($totalReceiveNet - $totalIssueNet, 3, '.', '') }}
                @endif
            </td>
            <td></td>
        </tr>
    </tbody>
</table>

@if(!empty($receive->remarks))
    <p><strong>Remarks:</strong> {{ $receive->remarks }}</p>
@endif
</body>
</html>
