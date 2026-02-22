@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header d-flex justify-content-between">

            <h4 class="card-title">Sales List</h4>

            <a href="{{ route('company.sales.create',$company->slug) }}"
                class="btn btn-primary">
                Create Sale
            </a>

        </div>


        <div class="card-body">

            <table class="table table-bordered" id="salesTable">

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Voucher No</th>
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
    $(document).ready(function() {

        $('#salesTable').DataTable({

            processing: true,
            serverSide: true,

            ajax: "{{ route('company.sales.index',$company->slug) }}",

            columns: [

                {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },

                {
                    data: 'voucher_no',
                    name: 'voucher_no'
                },

                {
                    data: 'sale_date',
                    name: 'sale_date'
                },

                {
                    data: 'net_total',
                    name: 'net_total'
                },

                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }

            ]

        });

    });
</script>

@endpush