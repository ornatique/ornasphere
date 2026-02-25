@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">
        <div class="card-body">

            <h4>Sales Return Voucher</h4>

            <p>
                Customer: {{ $sale->customer->name }} <br>
                Date: {{ $sale->sale_date }}
            </p>

            <form method="POST"
                action="{{ route('company.sales.return.store', [$company->slug, $sale->id]) }}">
                @csrf

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Item</th>
                            <th>Gross</th>
                            <th>Net</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($sale->saleItems as $item)
                        <tr>
                            <td>
                                <input type="checkbox"
                                    name="return_items[]"
                                    value="{{ $item->id }}">
                            </td>
                            <td>
                                {{ $item->itemset->item->item_name }}
                                -
                                {{ $item->itemset->qr_code }}
                            </td>
                            <td>{{ $item->gross_weight }}</td>
                            <td>{{ $item->net_weight }}</td>
                            <td>{{ number_format($item->total_amount,2) }}</td>
                        </tr>
                        @endforeach

                    </tbody>
                </table>

                <button class="btn btn-danger">
                    Process Return
                </button>

            </form>

        </div>
    </div>

</div>

@endsection