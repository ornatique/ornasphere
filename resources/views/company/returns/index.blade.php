@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center text-end">
            <h4 class="card-title">Sales Return List</h4>
            <a href="{{ route('company.returns.selectSale',$company->slug) }}"
                class="btn btn-primary ">
                Create Return
            </a>
        </div>
        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-success" id="filterBtn">Show</button>
                    <button class="btn btn-secondary" id="resetBtn">Reset</button>
                </div>

            </div>

            <table class="table table-bordered yajra-datatable">
                <thead>
                    <tr>
                        <th>#</th>
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

        let table = $('.yajra-datatable').DataTable({
            processing: true,
            serverSide: true,

            ajax: {
                url: "{{ route('company.returns.index',$company->slug) }}",
                data: function(d) {
                    d.from_date = $('#from_date').val();
                    d.to_date = $('#to_date').val();
                }
            },

            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'customer_name',
                    name: 'sale.customer.name'
                },
                {
                    data: 'return_date',
                    name: 'return_date'
                },
                {
                    data: 'return_total',
                    name: 'return_total'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        // 🔍 FILTER
        $('#filterBtn').click(function() {
            table.draw();
        });

        // 🔄 RESET
        $('#resetBtn').click(function() {
            $('#from_date').val('');
            $('#to_date').val('');
            table.draw();
        });

    });
</script>
@endpush