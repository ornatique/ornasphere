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
            @include('company_layout.sales_exports')
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
                .sales-summary-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(160px, 1fr));
                    gap: 14px;
                    margin-bottom: 18px;
                }
                .sales-summary-box {
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    background: rgba(255, 255, 255, 0.03);
                    padding: 12px 14px;
                }
                .sales-summary-box span {
                    display: block;
                    color: #b8b8c7;
                    font-size: 13px;
                    margin-bottom: 6px;
                }
                .sales-summary-box strong {
                    color: #fff;
                    font-size: 24px;
                    line-height: 1;
                }
                @media (max-width: 767px) {
                    .sales-summary-grid {
                        grid-template-columns: 1fr;
                    }
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
                    <select id="customer_id" class="form-select searchable-customer-select">
                        <option value="">All Persons</option>
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

            <div class="sales-summary-grid">
                <div class="sales-summary-box">
                    <span>Total Qty</span>
                    <strong id="salesSummaryQty">0</strong>
                </div>
                <div class="sales-summary-box">
                    <span>Total Gross Wt</span>
                    <strong id="salesSummaryGross">0.000</strong>
                </div>
                <div class="sales-summary-box">
                    <span>Total Net Wt</span>
                    <strong id="salesSummaryNet">0.000</strong>
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
                        <th>Company / Branch</th><th>Action</th>
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

    if ($.fn.select2) {
        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Persons',
            allowClear: true
        });
    }

    function updateSalesSummary(summary) {
        const totals = summary || {};
        $('#salesSummaryQty').text(Number(totals.total_qty || 0).toLocaleString('en-IN'));
        $('#salesSummaryGross').text(Number(totals.total_gross_wt || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        }));
        $('#salesSummaryNet').text(Number(totals.total_net_wt || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        }));
    }

    var table = $('#salesTable').DataTable({

        processing: true,
        serverSide: true,

        ajax: {
            url: "{!! route('company.sales.index', array_merge(['slug'=>$company->slug], request()->only('management_branches'))) !!}",
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

            { data: 'company_name', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false }

        ]

    });

    table.on('xhr.dt', function(e, settings, json) {
        updateSalesSummary(json && json.summary);
    });

    // FILTER BUTTON
    $('#filter').click(function () {
        table.draw();
    });

    // RESET BUTTON
    $('#reset').click(function () {
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('').trigger('change.select2');
        table.draw();
    });

    $('#download_pdf').click(function () {
        const params = new URLSearchParams({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val()
        });

        @foreach((array) request('management_branches', []) as $branchId)
        params.append('management_branches[]', @json($branchId));
        @endforeach
        window.open("{{ route('company.sales.export.pdf', $company->slug) }}?" + params.toString(), '_blank');
    });

    $('#salesTable').on('submit', '.delete-sale-form', function (event) {
        if (!confirm('Delete this sale estimate and move all items back to their original position?')) {
            event.preventDefault();
        }
    });

});
</script>

@endpush
