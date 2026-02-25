@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between right-end">

            <h4>Select Sale For Return</h4>

        </div>
        <div class="card-body">


            <div class="row mb-3">

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Customer</label>
                        <select id="filter_customer" class="form-select">
                            <option value="">All Customers</option>

                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">
                                {{ $customer->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" id="filter_from_date" class="form-select">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" id="filter_to_date" class="form-select">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label>Item</label>
                        <select id="filter_item" class="form-select">
                            <option value="">All Items</option>
                            @foreach(\App\Models\Item::where('company_id',$company->id)->get() as $item)
                            <option value="{{ $item->id }}">
                                {{ $item->item_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

            </div>
            <table class="table table-bordered yajra-datatable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>

        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
    $(function() {

        var table = $('.yajra-datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('company.returns.selectSaleData',$company->slug) }}",
                data: function(d) {
                    d.customer_id = $('#filter_customer').val();
                    d.from_date = $('#filter_from_date').val();
                    d.to_date = $('#filter_to_date').val();
                    d.item_id = $('#filter_item').val();
                }
            },
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'voucher_no'
                },
                {
                    data: 'customer_name'
                },
                {
                    data: 'sale_date'
                },
                {
                    data: 'net_total'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        // Reload when filter changes
        $('#filter_customer, #filter_from_date, #filter_to_date, #filter_item')
            .change(function() {
                table.draw();
            });

    });
</script>
@endpush