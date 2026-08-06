@extends('company_layout.admin')

@php
    $fromDate = request()->filled('from_date') ? request('from_date') : now()->subDays(6)->toDateString();
    $toDate = request()->filled('to_date') ? request('to_date') : now()->toDateString();
@endphp

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Tree Cutting Issue Office Voucher List</h4>
        </div>
        <div class="card-body">
            

            <div class="tree-office-filters">
                <div class="filter-field">
                    <label for="fromDate">From Date</label>
                    <input type="date" id="fromDate" class="form-control" value="{{ $fromDate }}">
                </div>
                <div class="filter-field">
                    <label for="toDate">To Date</label>
                    <input type="date" id="toDate" class="form-control" value="{{ $toDate }}">
                </div>
                <div class="filter-field">
                    <label for="workerFilter">Worker Name</label>
                    <select id="workerFilter" class="form-select">
                        <option value="">All Workers</option>
                        @foreach($jobWorkers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="button" id="applyFilter" class="btn btn-primary">Filter</button>
                    <button type="button" id="resetFilter" class="btn btn-secondary">Reset</button>
                </div>
            </div>

            <div class="table-responsive tree-office-list-scroll">
                <table class="table table-bordered table-striped" id="treeCuttingOfficeTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Voucher Number</th>
                            <th>Date Time</th>
                            <th>Process</th>
                            <th>Worker Name</th>
                            <th>Office Used</th>
                            <th>Tree Wt</th>
                            <th>Office Cut Wt</th>
                            <th>Remaining Tree Wt</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tree-office-list-scroll { max-height: calc(100vh - 360px); overflow-y: auto; }
    .tree-office-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) minmax(220px, 320px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }
    .tree-office-filters label { display: block; margin-bottom: 5px; color: #b8b8d4; font-size: 12px; }
    .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-actions .btn { min-width: 86px; }
    #treeCuttingOfficeTable thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        padding: 0.24rem 0.55rem;
        border-radius: 4px;
        color: #fff;
        font-weight: 700;
        line-height: 1.1;
    }
    .count-assigned { background: #16a34a; }
    .count-pending { background: #64748b; }
    @media (max-width: 767px) {
        .tree-office-filters { grid-template-columns: 1fr; }
        .filter-actions .btn { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script>
    const defaultTreeOfficeFromDate = @json($fromDate);
    const defaultTreeOfficeToDate = @json($toDate);
    const treeCuttingOfficeTable = $('#treeCuttingOfficeTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.tree-cutting-office.index', $company->slug) }}",
            data: function (data) {
                data.from_date = $('#fromDate').val();
                data.to_date = $('#toDate').val();
                data.worker_id = $('#workerFilter').val();
            }
        },
        order: [[2, 'desc']],
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no_view', name: 'voucher_no' },
            { data: 'date_time_view', name: 'created_at' },
            { data: 'process_name', name: 'process.name', orderable: false },
            { data: 'worker_name', name: 'jobWorker.name', orderable: false },
            { data: 'office_used_view', name: 'office_used_count', orderable: false, searchable: false },
            { data: 'tree_wt_view', name: 'tree_wt_total', orderable: false, searchable: false },
            { data: 'office_cut_wt_view', name: 'office_cut_wt_total', orderable: false, searchable: false },
            { data: 'remaining_tree_wt_view', name: 'remaining_tree_wt_total', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });
    $('#applyFilter').on('click', function () { treeCuttingOfficeTable.ajax.reload(); });
    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultTreeOfficeFromDate);
        $('#toDate').val(defaultTreeOfficeToDate);
        $('#workerFilter').val('');
        treeCuttingOfficeTable.ajax.reload();
    });
    function normalizeTreeOfficeDateRange() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        if (fromDate && toDate && fromDate > toDate) {
            $('#toDate').val(fromDate);
        }
    }
    $('#fromDate, #toDate, #workerFilter').on('change', function () {
        normalizeTreeOfficeDateRange();
        treeCuttingOfficeTable.ajax.reload();
    });
</script>
@endpush
