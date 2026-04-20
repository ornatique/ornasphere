@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Sales Summary Report</h4>
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
                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
                    <button id="filter" class="btn btn-success">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    <button id="export_excel" class="btn btn-info">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <table class="table table-bordered" id="salesSummaryTable">
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
                        <th>Created By</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);

    const table = $('#salesSummaryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.sales-summary.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no' },
            { data: 'sale_date' },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'qty_pcs', orderable: false, searchable: false },
            { data: 'gross_weight', orderable: false, searchable: false },
            { data: 'net_weight', orderable: false, searchable: false },
            { data: 'fine_weight', orderable: false, searchable: false },
            { data: 'metal_amount', orderable: false, searchable: false },
            { data: 'labour_amount', orderable: false, searchable: false },
            { data: 'other_amount', orderable: false, searchable: false },
            { data: 'net_total' },
            { data: 'created_by', orderable: false, searchable: false },
        ]
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('');
        table.draw();
    });

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.sales-summary.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.sales-summary.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
