@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h4 class="card-title mb-0">Sales List</h4>

            <div class="d-flex gap-2">
                <a href="{{ route('company.sales.create',$company->slug) }}" class="btn btn-primary">
                    Create Sale
                </a>

               
            </div>

        </div>

        <div class="card-body">

            {{-- 🔍 DATE FILTER --}}
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button id="filter" class="btn btn-success me-2">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                </div>
            </div>

            {{-- TABLE --}}
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

    var table = $('#salesTable').DataTable({

        processing: true,
        serverSide: true,

        ajax: {
            url: "{{ route('company.sales.index',$company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date   = $('#to_date').val();
            }
        },

        columns: [

            { data: 'DT_RowIndex', orderable: false, searchable: false },

            { data: 'voucher_no', name: 'voucher_no' },

            { data: 'sale_date', name: 'sale_date' },

            { data: 'net_total', name: 'net_total' },

            { data: 'action', orderable: false, searchable: false }

        ]

    });

    // FILTER BUTTON
    $('#filter').click(function () {
        table.draw();
    });

    // RESET BUTTON
    $('#reset').click(function () {
        $('#from_date').val('');
        $('#to_date').val('');
        table.draw();
    });

});
</script>

@endpush