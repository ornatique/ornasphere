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
            <style>
                .sales-list-grid-wrap {
                    overflow: auto;
                    position: relative;
                    border: 0;
                    border-radius: 0;
                    scrollbar-width: thin;
                    scrollbar-color: transparent transparent;
                }
                .sales-list-grid-wrap:hover {
                    scrollbar-color: rgba(125, 145, 255, 0.7) rgba(255, 255, 255, 0.08);
                }
                .sales-list-grid-wrap::-webkit-scrollbar {
                    width: 10px;
                    height: 10px;
                }
                .sales-list-grid-wrap::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sales-list-grid-wrap::-webkit-scrollbar-thumb {
                    background: transparent;
                    border-radius: 10px;
                }
                .sales-list-grid-wrap:hover::-webkit-scrollbar-thumb {
                    background: rgba(125, 145, 255, 0.7);
                }
                .sales-list-grid-wrap:hover::-webkit-scrollbar-track {
                    background: rgba(255, 255, 255, 0.08);
                }
            </style>

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

                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button id="filter" class="btn btn-success me-2">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    <button type="button" id="download_pdf" class="btn btn-danger ms-2">PDF</button>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="table-responsive sales-list-grid-wrap">
            <table class="table table-bordered" id="salesTable">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Qty</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
                        <th>Fine Wt</th>
                        <th>Metal Amt</th>
                        <th>Labour Amt</th>
                        <th>Other Amt</th>
                        <th>Total</th>
                        <th>Received</th>
                        <th>Refund Paid</th>
                        <th>Pending</th>
                        <th>Created By</th>
                        <th>Modified At</th>
                        <th>Modified Count</th>
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
$(document).ready(function() {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);

    var table = $('#salesTable').DataTable({

        processing: true,
        serverSide: true,

        ajax: {
            url: "{{ route('company.sales.index',$company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date   = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },

        columns: [

            { data: 'DT_RowIndex', orderable: false, searchable: false },

            { data: 'voucher_no', name: 'voucher_no' },

            { data: 'sale_date', name: 'sale_date' },

            { data: 'customer_name', name: 'customer_name', orderable: false, searchable: false },

            { data: 'total_qty', name: 'total_qty', orderable: false, searchable: false },

            { data: 'total_gross_weight', name: 'total_gross_weight', orderable: false, searchable: false },

            { data: 'total_net_weight', name: 'total_net_weight', orderable: false, searchable: false },

            { data: 'total_fine_weight', name: 'total_fine_weight', orderable: false, searchable: false },

            { data: 'total_metal_amount', name: 'total_metal_amount', orderable: false, searchable: false },

            { data: 'total_labour_amount', name: 'total_labour_amount', orderable: false, searchable: false },

            { data: 'total_other_amount', name: 'total_other_amount', orderable: false, searchable: false },

            { data: 'net_total', name: 'net_total' },
            { data: 'received_amount_fmt', name: 'received_amount_fmt', orderable: false, searchable: false },
            { data: 'refund_paid_amount_fmt', name: 'refund_paid_amount_fmt', orderable: false, searchable: false },
            { data: 'pending_amount_fmt', name: 'pending_amount_fmt', orderable: false, searchable: false },

            { data: 'creator_name', name: 'creator_name', orderable: false, searchable: false },

            { data: 'modified_at', name: 'modified_at', orderable: false, searchable: false },

            { data: 'modified_count', name: 'modified_count', orderable: false, searchable: false },

            { data: 'action', orderable: false, searchable: false }

        ]

    });

    // FILTER BUTTON
    $('#filter').click(function () {
        table.draw();
    });

    // RESET BUTTON
    $('#reset').click(function () {
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('');
        table.draw();
    });

    $('#download_pdf').click(function () {
        const params = new URLSearchParams({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val()
        });

        window.open("{{ route('company.sales.export.pdf', $company->slug) }}?" + params.toString(), '_blank');
    });

});
</script>

@endpush
