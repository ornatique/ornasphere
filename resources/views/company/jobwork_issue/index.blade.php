@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Jobwork Issue List</h4>
            <a href="{{ route('company.jobwork-issue.create', $company->slug) }}" class="btn btn-primary">
                + Add Jobwork Issue
            </a>
        </div>
        <div class="card-body">
            <form class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">From Date</label>
                    <input type="date" id="from_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">To Date</label>
                    <input type="date" id="to_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" id="filterBtn" class="btn btn-info">Apply Filter</button>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" id="resetBtn" class="btn btn-secondary">Reset</button>
                </div>
                <div class="col-md-2 d-grid">
                    <a href="#" id="exportExcelBtn" class="btn btn-success">Export Excel</a>
                </div>
                <div class="col-md-2 d-grid">
                    <a href="#" id="exportPdfBtn" class="btn btn-danger">Export PDF</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered" id="jobworkIssueTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Voucher Date</th>
                            <th>Jobworker</th>
                            <th>Production Step</th>
                            <th>Gross Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Total Amt</th>
                            <th>Modified</th>
                            <th>Modified Count</th>
                            <th>Created By</th>
                            <th>Created at</th>
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
    function exportUrl(baseUrl) {
        const params = new URLSearchParams({
            from_date: $('#from_date').val() || '',
            to_date: $('#to_date').val() || ''
        });
        return `${baseUrl}?${params.toString()}`;
    }

    $('#exportExcelBtn').on('click', function(e) {
        e.preventDefault();
        window.location.href = exportUrl("{{ route('company.jobwork-issue.export.excel', $company->slug) }}");
    });

    $('#exportPdfBtn').on('click', function(e) {
        e.preventDefault();
        window.location.href = exportUrl("{{ route('company.jobwork-issue.export.pdf', $company->slug) }}");
    });

    const table = $('#jobworkIssueTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.jobwork-issue.index', $company->slug) }}",
            data: function(d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
            }
        },
        columns: [{
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
                data: 'jobwork_date_view',
                name: 'jobwork_date'
            },
            {
                data: 'jobworker_name',
                name: 'jobWorker.name',
                orderable: false
            },
            {
                data: 'production_step_name',
                name: 'productionStep.name',
                orderable: false
            },
            {
                data: 'gross_wt_sum',
                name: 'gross_wt_sum',
                searchable: false
            },
            {
                data: 'net_wt_sum',
                name: 'net_wt_sum',
                searchable: false
            },
            {
                data: 'fine_wt_sum',
                name: 'fine_wt_sum',
                searchable: false
            },
            {
                data: 'total_amt_sum',
                name: 'total_amt_sum',
                searchable: false
            },
            {
                data: 'modified_at_view',
                name: 'updated_at'
            },
            {
                data: 'modified_count',
                name: 'modified_count'
            },
            {
                data: 'user_name',
                name: 'user_name',
                orderable: false,
                searchable: false
            },
            {
                data: 'created_at_view',
                name: 'created_at'
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false
            }
        ]
    });

    $('#filterBtn').on('click', function() {
        table.ajax.reload();
    });

    $('#resetBtn').on('click', function() {
        const today = "{{ now()->toDateString() }}";
        $('#from_date').val(today);
        $('#to_date').val(today);
        table.ajax.reload();
    });

    $(document).on('click', '.deleteBtn', function() {
        if (!confirm('Are you sure to delete this record?')) return;
        const url = $(this).data('url');

        $.ajax({
            url: url,
            type: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(resp) {
                table.ajax.reload();
                alert(resp.message || 'Deleted successfully');
            },
            error: function(xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Delete failed';
                alert(msg);
            }
        });
    });
</script>
@endpush
