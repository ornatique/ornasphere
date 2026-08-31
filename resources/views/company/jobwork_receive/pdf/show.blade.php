<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Receive {{ $row->voucher_no }}</title>
    <style>
        @page {
            margin: 5px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            margin: 0;
            color: #111;
        }
        .sheet-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .sheet-table > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .sheet-table > tbody > tr > td:first-child {
            padding-right: 2.5px;
        }
        .sheet-table > tbody > tr > td:last-child {
            padding-left: 2.5px;
        }
        .copy {
            width: 100%;
            border: 1px solid #111;
            overflow: hidden;
        }
        .title {
            text-align: center;
            font-weight: 700;
            border-bottom: 1px solid #111;
            padding: 4px 0;
            font-size: 11px;
            letter-spacing: 0;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-grid td {
            border-bottom: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 8px;
        }
        .meta-grid td.right {
            text-align: left;
            width: 38%;
            border-left: 1px solid #111;
        }
        .kv {
            font-weight: 700;
            display: inline-block;
            min-width: 24px;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items th, .items td {
            border: 1px solid #111;
            padding: 3px 3px;
            text-align: left;
            vertical-align: top;
            font-size: 7px;
            box-sizing: border-box;
            overflow: hidden;
            word-break: break-word;
        }
        .items th {
            font-weight: 700;
            background: #f2f2f2;
        }
        .num {
            text-align: right;
            white-space: nowrap;
        }
        .totals td {
            font-weight: 700;
        }
        .footer-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-grid td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 8px;
        }
        .left-col {
            width: 49%;
            white-space: nowrap;
        }
        .right-col {
            width: 51%;
        }
        .item-remark {
            margin-top: 1px;
            font-size: 7px;
            font-weight: 700;
        }
        .f-label {
            font-weight: 700;
            display: inline-block;
            min-width: 66px;
        }
    </style>
</head>
<body>
@php
    $totalReceiveGross = 0;
    $totalOtherWt = 0;
    $totalReceiveNet = 0;
    $totalReceiveFine = 0;
    $totalIssueNet = (float) ($row->net_wt_sum ?? 0);
    $issueTime = optional($row->created_at)->format('h:i A');
    $receiveSavedAt = $receive->updated_at ?: $receive->created_at;
    $receiveTime = optional($receiveSavedAt)->format('h:i A');
    $issueDateTime = $row->jobwork_date
        ? trim($row->jobwork_date->format('d-m-Y') . ' ' . $issueTime)
        : optional($row->created_at)->format('d-m-Y h:i A');
    $receiveDateTime = $receive->receive_date
        ? trim($receive->receive_date->format('d-m-Y') . ' ' . $receiveTime)
        : optional($receiveSavedAt)->format('d-m-Y h:i A');

    $receiveRows = collect($receive->items ?? [])->map(function ($saved) use (&$totalReceiveGross, &$totalOtherWt, &$totalReceiveNet, &$totalReceiveFine) {
        $issueItem = $saved->jobworkIssueItem;
        $item = $saved->item ?: ($issueItem?->item);
        $issueNet = (float) ($issueItem?->net_wt ?? 0);
        $receiveGross = (float) ($saved?->receive_gross_wt ?? 0);
        $receiveNet = (float) ($saved?->receive_net_wt ?? 0);
        $otherWt = (float) ($saved?->other_wt ?? max(0, $receiveGross - $receiveNet));
        $receiveFine = (float) ($saved?->receive_fine_wt ?? 0);
        $pendingNet = max(0, (float) ($saved?->loss_wt ?? ($issueNet - $receiveNet)));

        $totalReceiveGross += $receiveGross;
        $totalOtherWt += $otherWt;
        $totalReceiveNet += $receiveNet;
        $totalReceiveFine += $receiveFine;

        return [
            'item_name' => $item?->item_name ?? '-',
            'issue_net' => $issueNet,
            'receive_gross' => $receiveGross,
            'other_wt' => $otherWt,
            'receive_net' => $receiveNet,
            'receive_fine' => $receiveFine,
            'pending_net' => $pendingNet,
            'remarks' => $saved?->remarks ?? '',
        ];
    });

    $totalPendingNet = max(0, $totalIssueNet - $totalReceiveNet);
    $workerVoucherText = $workerIssueVouchers->pluck('voucher_no')->implode(', ') ?: $row->voucher_no;
@endphp
<table class="sheet-table">
    <tr>
        @for($c = 1; $c <= 2; $c++)
            <td>
                <div class="copy">
                    <div class="title">Jobwork Receive</div>

                    <table class="meta-grid">
                        <tr>
                            <td><span class="kv">M/S</span> {{ strtoupper((string) ($row->jobWorker?->name ?? '-')) }}</td>
                            <td class="right"><span class="kv">No</span> : {{ $row->voucher_no }}</td>
                        </tr>
                        <tr>
                            <td><span class="kv">Step</span> : {{ $row->productionStep?->name ?? '-' }}</td>
                            <td class="right"><span class="kv">Date</span> : {{ $receiveDateTime ?: '-' }}</td>
                        </tr>
                    </table>

                    <table class="items">
                        <thead>
                            <tr>
                                <th style="width:6%;">Sr</th>
                                <th style="width:24%;">Item</th>
                                <th style="width:13%;" class="num">Issue</th>
                                <th style="width:13%;" class="num">R Gross</th>
                                <th style="width:11%;" class="num">Other</th>
                                <th style="width:12%;" class="num">R Net</th>
                                <th style="width:11%;" class="num">Fine</th>
                                <th style="width:10%;" class="num">Pend</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receiveRows as $index => $saved)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @php
                                            $itemName = (string) $saved['item_name'];
                                        @endphp
                                        {{ strlen($itemName) > 15 ? substr($itemName, 0, 15) . '...' : $itemName }}
                                        @if(!empty($saved['remarks']))
                                            <div class="item-remark">Remark: {{ $saved['remarks'] }}</div>
                                        @endif
                                    </td>
                                    <td class="num">{{ number_format($saved['issue_net'], 3, '.', '') }}</td>
                                    <td class="num">{{ number_format($saved['receive_gross'], 3, '.', '') }}</td>
                                    <td class="num">{{ number_format($saved['other_wt'], 3, '.', '') }}</td>
                                    <td class="num">{{ number_format($saved['receive_net'], 3, '.', '') }}</td>
                                    <td class="num">{{ number_format($saved['receive_fine'], 3, '.', '') }}</td>
                                    <td class="num">{{ number_format($saved['pending_net'], 3, '.', '') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align:center;">No receive rows found</td>
                                </tr>
                            @endforelse
                            <tr class="totals">
                                <td colspan="2" class="num">Total</td>
                                <td class="num">{{ number_format($totalIssueNet, 3, '.', '') }}</td>
                                <td class="num">{{ number_format($totalReceiveGross, 3, '.', '') }}</td>
                                <td class="num">{{ number_format($totalOtherWt, 3, '.', '') }}</td>
                                <td class="num">{{ number_format($totalReceiveNet, 3, '.', '') }}</td>
                                <td class="num">{{ number_format($totalReceiveFine, 3, '.', '') }}</td>
                                <td class="num">{{ number_format($totalPendingNet, 3, '.', '') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="footer-grid">
                        <tr>
                            <td class="left-col"><span class="f-label">Issue Net</span>: {{ number_format($totalIssueNet, 3, '.', '') }} gram</td>
                            <td class="right-col">Receive fine : {{ number_format($totalReceiveFine, 3, '.', '') }} gram</td>
                        </tr>
                        <tr>
                            <td class="left-col"><span class="f-label">Receive Net</span>: {{ number_format($totalReceiveNet, 3, '.', '') }} gram</td>
                            <td class="right-col">Remarks : {{ $receive->remarks ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="left-col"><span class="f-label">Pending Net</span>: {{ number_format($totalPendingNet, 3, '.', '') }} gram</td>
                            <td class="right-col">Worker vouchers : {{ $workerVoucherText }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        @endfor
    </tr>
</table>
</body>
</html>
