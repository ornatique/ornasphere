@extends('company_layout.admin')

@section('content')
<style>
    #approvalTable {
        width: 100% !important;
        min-width: 1650px;
    }

    #approvalTable th,
    #approvalTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #approvalTable td:last-child {
        white-space: normal;
    }

    .approval-list-grid-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border: 0;
        border-radius: 0;
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    .approval-list-grid-wrap:hover {
        scrollbar-color: rgba(125, 145, 255, 0.7) rgba(255, 255, 255, 0.08);
    }

    .approval-list-grid-wrap::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .approval-list-grid-wrap::-webkit-scrollbar-track {
        background: transparent;
    }

    .approval-list-grid-wrap::-webkit-scrollbar-thumb {
        background: transparent;
        border-radius: 10px;
    }

    .approval-list-grid-wrap:hover::-webkit-scrollbar-thumb {
        background: rgba(125, 145, 255, 0.7);
    }

    .approval-list-grid-wrap:hover::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
    }
</style>

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

                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

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
            <div class="table-responsive approval-list-grid-wrap">
                <table class="table table-bordered" id="approvalTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Approval No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Item Name</th>
                            <th>Qty</th>
                            <th>Gross Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Metal Amt</th>
                            <th>Labour Amt</th>
                            <th>Other Amt</th>
                            <th>Total Amt</th>
                            <th>Created By</th>
                            <th>Modified At</th>
                            <th>Modified Count</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>

        </div>

    </div>

</div>

@endsection
@push('scripts')
<script>
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);

    let table = $('#approvalTable').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        scrollX: true,
        autoWidth: false,
        scrollCollapse: true,

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
                data: 'item_names',
                orderable: false,
                searchable: false
            },
            {
                data: 'total_qty'
            },
            {
                data: 'total_gross_weight'
            },
            {
                data: 'total_net_weight'
            },
            {
                data: 'total_fine_weight'
            },
            {
                data: 'total_metal_amount'
            },
            {
                data: 'total_labour_amount'
            },
            {
                data: 'total_other_amount'
            },
            {
                data: 'total_amount'
            },
            {
                data: 'creator_name',
                orderable: false,
                searchable: false
            },
            {
                data: 'modified_at',
                orderable: false,
                searchable: false
            },
            {
                data: 'modified_count',
                orderable: false,
                searchable: false
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
        ],
        columnDefs: [{
            targets: '_all',
            className: 'text-nowrap'
        }, {
            targets: [17],
            className: ''
        }]
    });

    $('#filterBtn').click(function() {
        table.draw();
    });

    $('#clearBtn').click(function() {
        $('#customer_id').val('');
        $('#from_date').val(today);
        $('#to_date').val(today);
        table.draw();
    });

    $('#customer_id').val('').trigger('change');
</script>
@endpush
