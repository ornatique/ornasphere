<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->voucher_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px;
            vertical-align: top;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            padding: 8px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            background: #f7f7f7;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="8" class="title">{{ $company->name }}</td>
        </tr>
        <tr>
            <td colspan="8" class="section-title">Receive / Return / Purchase Voucher</td>
        </tr>
        <tr>
            <td><strong>Voucher No</strong></td>
            <td>{{ $voucher->voucher_no }}</td>
            <td><strong>Date</strong></td>
            <td>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
            <td><strong>Party</strong></td>
            <td colspan="3">{{ optional($voucher->customer)->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Entry Type</strong></td>
            <td>{{ ucwords(str_replace('_', ' ', $voucher->entry_type)) }}</td>
            <td><strong>Mode</strong></td>
            <td>{{ $voucher->payment_mode ? ucfirst($voucher->payment_mode) : '-' }}</td>
            <td><strong>Amount</strong></td>
            <td class="text-right">{{ number_format((float) $voucher->amount, 2) }}</td>
            <td><strong>Rate</strong></td>
            <td class="text-right">{{ number_format((float) $voucher->rate, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Cash In</strong></td>
            <td class="text-right">{{ number_format((float) $voucher->cash_in, 2) }}</td>
            <td><strong>Cash Out</strong></td>
            <td class="text-right">{{ number_format((float) $voucher->cash_out, 2) }}</td>
            <td><strong>Metal Type</strong></td>
            <td>{{ $voucher->metal_type ? ucfirst($voucher->metal_type) : '-' }}</td>
            <td><strong>Metal In / Out</strong></td>
            <td class="text-right">{{ number_format((float) $voucher->metal_in, 3) }} / {{ number_format((float) $voucher->metal_out, 3) }}</td>
        </tr>
        <tr>
            <td><strong>Remarks</strong></td>
            <td colspan="7">{{ $voucher->remarks ?? '-' }}</td>
        </tr>
    </table>

    <br>

    <table>
        <thead>
            <tr>
                <th>Sr</th>
                <th>Label</th>
                <th>Item</th>
                <th>Gross Wt</th>
                <th>Other Wt</th>
                <th>Net Wt</th>
                <th>Purity</th>
                <th>Fine Wt</th>
                <th>Metal Amt</th>
                <th>Labour Amt</th>
                <th>Other Amt</th>
                <th>Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucher->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->label_code ?? $item->huid ?? '-' }}</td>
                    <td>{{ $item->item_name ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) $item->gross_weight, 3) }}</td>
                    <td class="text-right">{{ number_format((float) $item->other_weight, 3) }}</td>
                    <td class="text-right">{{ number_format((float) $item->net_weight, 3) }}</td>
                    <td class="text-right">{{ number_format((float) $item->purity, 3) }}</td>
                    <td class="text-right">{{ number_format((float) $item->fine_weight, 3) }}</td>
                    <td class="text-right">{{ number_format((float) $item->metal_amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->labour_amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->other_amount, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center">No item selected.</td>
                </tr>
            @endforelse
        </tbody>
        @if($voucher->items->count())
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('gross_weight'), 3) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('other_weight'), 3) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('net_weight'), 3) }}</th>
                    <th></th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('fine_weight'), 3) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('metal_amount'), 2) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('labour_amount'), 2) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('other_amount'), 2) }}</th>
                    <th class="text-right">{{ number_format((float) $voucher->items->sum('total_amount'), 2) }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
