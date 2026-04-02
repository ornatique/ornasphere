@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        {{-- HEADER --}}
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Approval List</h4>
            <a href="{{ route('company.approval.create',$company->slug) }}"
                class="btn btn-primary ">
                Create Approval
            </a>
        </div>

        {{-- FILTER --}}
        <div class="card-body border-bottom">
            <div class="row">

                {{-- CUSTOMER --}}
                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">Select Customer</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- FROM DATE --}}
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                {{-- TO DATE --}}
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                {{-- BUTTON --}}
                <div class="col-md-3 gap-2 d-flex align-items-end">
                    <button class="btn btn-primary" id="filterBtn">
                        Show
                    </button>
                    <button class="btn btn-secondary" id="clearBtn">
                        Clear
                    </button>
                </div>


            </div>
        </div>

        {{-- TABLE --}}
        <div class="card-body">

            <table class="table table-bordered" id="approvalTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Approval No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Total Items</th>
                        <th>Total Wt</th>
                        <th>Total Amt</th>
                        <th>Status</th>
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
    let table = $('#approvalTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false, // optional

        ajax: {
            url: "{{ route('company.approval.index', $company->slug) }}",
            data: function(d) {
                d.customer_id = $('#customer_id').val();
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
                data: 'approval_no'
            },
            {
                data: 'approval_date'
            },
            {
                data: 'customer_name'
            },
            {
                data: 'total_items'
            },
            {
                data: 'total_net_weight'
            },
            {
                data: 'total_amount'
            },
            {
                data: 'status',
                orderable: false,
                searchable: false
            },
            {
                data: 'action',
                orderable: false,
                searchable: false
            }
        ],
        order: [
            [2, 'desc']
        ]
    });



    // 🚫 disable auto load
    table.clear().draw();

    // 🔘 button click
    $('#filterBtn').click(function() {

        let customer = $('#customer_id').val();

        if (!customer) {
            alert('Please select customer');
            return;
        }

        table.draw();
    });
    $('#clearBtn').click(function() {

        // 🔄 reset fields
        $('#customer_id').val('');
        $('#from_date').val('');
        $('#to_date').val('');

        // ❌ clear table
        table.clear().draw();
    });

    $('#customer_id').val(null).trigger('change');
</script>
@endpush
