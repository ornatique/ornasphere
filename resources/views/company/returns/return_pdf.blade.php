<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: DejaVu Sans;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>

    <h3 style="text-align:center;">Sales Return Voucher</h3>

    <p>
        Customer: {{ $return->sale->customer->name }} <br>
        Return Voucher: {{ $return->return_voucher_no }} <br>
        Date: {{ $return->return_date }}
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Gross</th>
                <th>Net</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            @foreach($return->items as $key => $item)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>
                    {{ $item->saleItem->itemset->item->item_name }}
                    -
                    {{ $item->saleItem->itemset->qr_code }}
                </td>
                <td>{{ $item->saleItem->gross_weight }}</td>
                <td>{{ $item->saleItem->net_weight }}</td>
                <td class="text-right">
                    {{ number_format($item->return_amount,2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h4 class="text-right">
        Return Total: ₹ {{ number_format($return->return_total,2) }}
    </h4>

</body>

</html>