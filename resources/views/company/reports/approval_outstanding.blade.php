@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Approval Outstanding Report</h4>
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

            <table class="table table-bordered" id="approvalOutstandingTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Approval No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Pending Pcs</th>
                        <th>Pending Net Wt</th>
                        <th>Pending Amount</th>
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

    const table = $('#approvalOutstandingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.approval-outstanding.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'approval_no' },
            { data: 'approval_date_fmt', orderable: false, searchable: false },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'status' },
            { data: 'pending_items', orderable: false, searchable: false },
            { data: 'pending_net_weight_fmt', orderable: false, searchable: false },
            { data: 'pending_total_amount_fmt', orderable: false, searchable: false },
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
        window.location.href = "{{ route('company.reports.approval-outstanding.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.approval-outstanding.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
