@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center text-end">
            <h4 class="card-title">Sales Return  List</h4>
            <a href="{{ route('company.returns.selectSale',$company->slug) }}"
                class="btn btn-primary ">
                Create Return
            </a>

        </div>
        <div class="card-body">

            

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

        $('.yajra-datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('company.returns.index',$company->slug) }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
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
                    name: 'action',
                    orderable: false,
                    searchable: false
                },
            ]
        });

    });
</script>
@endpush