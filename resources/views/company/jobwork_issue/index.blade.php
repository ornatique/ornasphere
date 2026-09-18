@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <style>
        .jobwork-page-header {
            gap: 16px;
            flex-wrap: wrap;
        }

        .jobwork-page-header .card-title {
            margin-bottom: 0;
        }

        .jobwork-header-actions,
        .jobwork-filter-actions {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .jobwork-filter-panel {
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.02);
            margin-bottom: 16px;
        }

        .jobwork-filter-panel .form-control {
            min-height: 48px;
        }

        .jobwork-action-btn {
            min-height: 48px;
            min-width: 120px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            padding-left: 18px;
            padding-right: 18px;
        }

        .jobwork-add-btn {
            min-width: 178px;
        }

        @media (max-width: 767.98px) {
            .jobwork-filter-actions,
            .jobwork-header-actions {
                width: 100%;
            }

            .jobwork-action-btn,
            .jobwork-add-btn {
                width: 100%;
            }
        }
    </style>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center jobwork-page-header">
            <h4 class="card-title">Jobwork Issue List</h4>
            <div class="jobwork-header-actions">
                <a href="{{ route('company.jobwork-issue.create', $company->slug) }}" class="btn btn-primary jobwork-action-btn jobwork-add-btn">
                + Add Jobwork Issue
                </a>
            </div>
        </div>
        <div class="card-body">
            <form class="jobwork-filter-panel">
                <div class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label mb-1">From Date</label>
                    <input type="date" id="from_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label mb-1">To Date</label>
                    <input type="date" id="to_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label mb-1">Worker Name</label>
                    <select id="job_worker_id" class="form-control jobwork-search-select">
                        <option value="">All Workers</option>
                        @foreach($jobWorkers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label mb-1">Production Step</label>
                    <select id="production_step_id" class="form-control jobwork-search-select">
                        <option value="">All Steps</option>
                        @foreach($productionSteps as $step)
                            <option value="{{ $step->id }}">{{ $step->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="jobwork-filter-actions">
                        <button type="button" id="filterBtn" class="btn btn-info jobwork-action-btn">Apply Filter</button>
                        <button type="button" id="resetBtn" class="btn btn-secondary jobwork-action-btn">Reset</button>
                        <a href="#" id="exportExcelBtn" class="btn btn-success jobwork-action-btn">Export Excel</a>
                        <a href="#" id="exportPdfBtn" class="btn btn-danger jobwork-action-btn">Export PDF</a>
                    </div>
                </div>
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
    let table;

    if ($.fn.select2) {
        $('#job_worker_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Workers',
            allowClear: true,
            minimumResultsForSearch: 0
        });

        $('#production_step_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Steps',
            allowClear: true,
            minimumResultsForSearch: 0
        });
    }

    function exportUrl(baseUrl) {
        const params = new URLSearchParams({
            from_date: $('#from_date').val() || '',
            to_date: $('#to_date').val() || '',
            job_worker_id: $('#job_worker_id').val() || '',
            production_step_id: $('#production_step_id').val() || '',
            search_text: table ? table.search() : ''
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

    table = $('#jobworkIssueTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.jobwork-issue.index', $company->slug) }}",
            data: function(d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.job_worker_id = $('#job_worker_id').val();
                d.production_step_id = $('#production_step_id').val();
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
        ],
        order: [
            [12, 'desc']
        ]
    });

    $('#filterBtn').on('click', function() {
        table.ajax.reload();
    });

    $('#resetBtn').on('click', function() {
        const today = "{{ now()->toDateString() }}";
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#job_worker_id').val('').trigger('change.select2');
        $('#production_step_id').val('').trigger('change.select2');
        table.search('');
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
