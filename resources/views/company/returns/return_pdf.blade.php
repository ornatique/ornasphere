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

    @php
    $headerCustomer =
        optional(optional($return->sale)->customer)->name
        ?? optional(optional($return->approval)->customer)->name
        ?? optional(
            optional(
                optional($return->items->firstWhere('sale_item_id', '!=', null))->saleItem
            )->sale
        )->customer->name
        ?? '-';
    @endphp

    <p>
        <strong>Customer:</strong> {{ $headerCustomer }}
        <br>

        <strong>Return Voucher:</strong> {{ $return->return_voucher_no }} <br>
        <strong>Date:</strong> {{ $return->return_date }}
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>HUID</th>
                <th>QR</th>
                <th>Gross</th>
                <th>Net</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            @php
            $approvalReturnedItems = collect();
            if ($return->approval) {
                $approvalReturnedItems = $return->approval->items
                    ->where('status', 'returned')
                    ->values();
            }
            $approvalPointer = 0;
            @endphp

            @foreach($return->items as $key => $item)
            @php
            $saleItem = $item->saleItem;
            $approvalItem = null;

            if (!$saleItem) {
                $approvalItem = $approvalReturnedItems->get($approvalPointer);
                $approvalPointer++;
            }

            $itemSet = $saleItem->itemset ?? $item->itemSet ?? optional($approvalItem)->itemSet;
            $product = optional($itemSet)->item;
            @endphp

            <tr>
                <td>{{ $key + 1 }}</td>
                <td>{{ optional($product)->item_name ?? '-' }}</td>
                <td>{{ optional($itemSet)->HUID ?? '-' }}</td>
                <td>{{ optional($itemSet)->qr_code ?? optional($approvalItem)->qr_code ?? '-' }}</td>
                <td>{{ $saleItem->gross_weight ?? optional($approvalItem)->gross_weight ?? '-' }}</td>
                <td>{{ $saleItem->net_weight ?? optional($approvalItem)->net_weight ?? '-' }}</td>
                <td class="text-right">{{ number_format($item->return_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h4 class="text-right">
        Return Total: Rs {{ number_format($return->return_total, 2) }}
    </h4>

</body>

</html>
