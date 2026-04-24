<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jobwork Issue {{ $row->voucher_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            margin: 8px;
            color: #111;
        }
        .sheet-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sheet-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 4px;
        }
        .copy {
            width: 100%;
            border: 1px solid #111;
        }
        .title {
            text-align: center;
            font-weight: 700;
            border-bottom: 1px solid #111;
            padding: 3px 0;
            font-size: 10px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-grid td {
            border-bottom: 1px solid #111;
            padding: 2px 4px;
            vertical-align: top;
            font-size: 9px;
        }
        .meta-grid td.right {
            text-align: left;
            width: 32%;
            border-left: 1px solid #111;
        }
        .kv {
            font-weight: 700;
            display: inline-block;
            min-width: 22px;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items th, .items td {
            border: 1px solid #111;
            padding: 2px 3px;
            text-align: left;
            vertical-align: top;
            font-size: 9px;
        }
        .items th {
            font-weight: 700;
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
            padding: 2px 4px;
            vertical-align: top;
            font-size: 9px;
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
            font-size: 8px;
            font-weight: 700;
        }
        .f-label {
            font-weight: 700;
            display: inline-block;
            min-width: 78px;
        }
    </style>
</head>
<body>
    <table class="sheet-table">
        <tr>
            @for($c = 1; $c <= 2; $c++)
                <td>
                    <div class="copy">
                <div class="title">Jobwork Issue</div>

                <table class="meta-grid">
                    <tr>
                        <td><span class="kv">M/S</span> {{ strtoupper((string) ($row->jobWorker?->name ?? '-')) }}</td>
                        <td class="right"><span class="kv">No</span> : {{ $row->voucher_no }}</td>
                    </tr>
                    <tr>
                        <td><span class="kv">Step</span> : {{ $row->productionStep?->name ?? '-' }}</td>
                        <td class="right"><span class="kv">Date</span> : {{ optional($row->jobwork_date)->format('d-m-y') }}</td>
                    </tr>
                </table>

                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:6%;">Sr</th>
                            <th style="width:40%;">Item</th>
                            <th style="width:14%;" class="num">Purity</th>
                            <th style="width:14%;" class="num">Gross Wt</th>
                            <th style="width:13%;" class="num">Net Wt</th>
                            <th style="width:13%;" class="num">Fine Wt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($row->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    {{ $item->item?->item_name ?? '-' }}
                                    @if(!empty($item->remarks))
                                        <div class="item-remark">Remark: {{ $item->remarks }}</div>
                                    @endif
                                </td>
                                <td class="num">{{ number_format((float) ($item->purity ?? 0), 3, '.', '') }}</td>
                                <td class="num">{{ number_format((float) ($item->gross_wt ?? 0), 3, '.', '') }}</td>
                                <td class="num">{{ number_format((float) ($item->net_wt ?? 0), 3, '.', '') }}</td>
                                <td class="num">{{ number_format((float) ($item->fine_wt ?? 0), 3, '.', '') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center;">No item rows found</td>
                            </tr>
                        @endforelse
                        <tr class="totals">
                            <td colspan="3" class="num">Total</td>
                            <td class="num">{{ number_format((float) ($row->gross_wt_sum ?? 0), 3, '.', '') }}</td>
                            <td class="num">{{ number_format((float) ($row->net_wt_sum ?? 0), 3, '.', '') }}</td>
                            <td class="num">{{ number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', '') }}</td>
                        </tr>
                    </tbody>
                </table>

                <table class="footer-grid">
                    <tr>
                        <td class="left-col"><span class="f-label">Previous Rs</span>: 0.000</td>
                        <td class="right-col">Silver fine : {{ number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', '') }} gram</td>
                    </tr>
                    <tr>
                        <td class="left-col"><span class="f-label">Current Rs</span>: {{ number_format((float) ($row->total_amt_sum ?? 0), 2, '.', '') }}</td>
                        <td class="right-col">Remarks : {{ $row->remarks ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="left-col"><span class="f-label">Total Rs</span>: {{ number_format((float) ($row->total_amt_sum ?? 0), 3, '.', '') }}</td>
                        <td class="right-col">Net fine : {{ number_format((float) ($row->net_wt_sum ?? 0), 3, '.', '') }} gram</td>
                    </tr>
                </table>
                    </div>
                </td>
            @endfor
        </tr>
    </table>
</body>
</html>
