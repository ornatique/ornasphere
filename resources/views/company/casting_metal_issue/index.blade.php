@extends('company_layout.admin')

@php
    $fromDate = request()->filled('from_date') ? request('from_date') : now()->subDays(6)->toDateString();
    $toDate = request()->filled('to_date') ? request('to_date') : now()->toDateString();
@endphp

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Casting Metal Issue Voucher List</h4>
        </div>
        <div class="card-body">
            <div class="casting-metal-filters">
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
                    <select id="workerFilter" class="form-select d-none">
                        <option value="">All Workers</option>
                        @foreach($jobWorkers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                        @endforeach
                    </select>
                    <div class="casting-worker-combo" id="workerFilterCombo">
                        <button type="button" class="casting-worker-combo-toggle" id="workerFilterToggle">
                            <span id="workerFilterText">All Workers</span>
                            <span class="casting-worker-combo-arrow">&#9662;</span>
                        </button>
                        <div class="casting-worker-combo-menu" id="workerFilterMenu">
                            <input type="text" class="casting-worker-combo-search" id="workerFilterSearch" autocomplete="off">
                            <div class="casting-worker-combo-options" id="workerFilterOptions">
                                @foreach($jobWorkers as $worker)
                                    <button type="button" class="casting-worker-combo-option" data-value="{{ $worker->id }}">{{ $worker->name }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" id="applyFilter" class="btn btn-primary">Filter</button>
                    <button type="button" id="resetFilter" class="btn btn-secondary">Reset</button>
                </div>
            </div>

            <div class="table-responsive casting-metal-list-scroll">
                <table class="table table-bordered table-striped" id="castingMetalIssueTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Voucher Number</th>
                            <th>Date Time</th>
                            <th>Process</th>
                            <th>Worker Name</th>
                            <th>Assigned Metal</th>
                            <th>Metal Total Wt</th>
                            <th>Issue Silver Wt</th>
                            <th>Pending</th>
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
    .casting-metal-list-scroll {
        max-height: calc(100vh - 360px);
        overflow-y: auto;
    }

    .casting-metal-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) minmax(220px, 320px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }

    .casting-metal-filters label {
        display: block;
        margin-bottom: 5px;
        color: #b8b8d4;
        font-size: 12px;
    }

    .filter-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .filter-actions .btn {
        min-width: 86px;
    }

    #castingMetalIssueTable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
    }

    .casting-worker-combo {
        position: relative;
    }

    .casting-worker-combo-toggle {
        width: 100%;
        min-height: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        color: #cfd3e6;
        background-color: #2f2e55;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 4px;
        text-align: left;
    }

    .casting-worker-combo-arrow {
        color: #6b7280;
        font-size: 13px;
    }

    .casting-worker-combo-menu {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 1px);
        z-index: 30;
        display: none;
        background: #fff;
        border: 1px solid #8d91aa;
        border-radius: 0 0 3px 3px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
    }

    .casting-worker-combo.is-open .casting-worker-combo-menu {
        display: block;
    }

    .casting-worker-combo-search {
        width: calc(100% - 10px);
        height: 34px;
        margin: 5px;
        padding: 6px 8px;
        color: #111;
        background: #fff !important;
        border: 1px solid #b9c0cc !important;
        border-radius: 4px;
        outline: none;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    .casting-worker-combo-search:focus {
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
    }

    .casting-worker-combo-options {
        max-height: 180px;
        overflow-y: auto;
    }

    .casting-worker-combo-option {
        display: block;
        width: 100%;
        padding: 8px 12px;
        color: #111;
        background: #fff;
        border: 0;
        text-align: left;
    }

    .casting-worker-combo-option:hover,
    .casting-worker-combo-option.is-active {
        color: #fff;
        background: #0d6efd;
    }

    .casting-worker-combo-empty {
        padding: 8px 12px;
        color: #6b7280;
        background: #fff;
    }

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

    .count-assigned,
    .count-complete {
        background: #16a34a;
    }

    .count-pending {
        background: #dc2626;
    }

    @media (max-width: 767px) {
        .casting-metal-filters {
            grid-template-columns: 1fr;
        }

        .filter-actions .btn {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const defaultFromDate = @json($fromDate);
    const defaultToDate = @json($toDate);

    const workerCombo = document.getElementById('workerFilterCombo');
    const workerToggle = document.getElementById('workerFilterToggle');
    const workerText = document.getElementById('workerFilterText');
    const workerSearch = document.getElementById('workerFilterSearch');
    const workerOptions = Array.from(document.querySelectorAll('.casting-worker-combo-option'));

    function closeWorkerCombo() {
        workerCombo.classList.remove('is-open');
        workerSearch.value = '';
        filterWorkerOptions('');
    }

    function openWorkerCombo() {
        workerCombo.classList.add('is-open');
        if (!workerOptions.some(function (option) { return option.classList.contains('is-active'); }) && workerOptions.length) {
            workerOptions[0].classList.add('is-active');
        }
        workerSearch.focus();
    }

    function filterWorkerOptions(searchText) {
        const keyword = searchText.trim().toLowerCase();
        let visibleCount = 0;
        const existingEmpty = document.querySelector('.casting-worker-combo-empty');

        if (existingEmpty) {
            existingEmpty.remove();
        }

        workerOptions.forEach(function (option) {
            const isVisible = option.textContent.toLowerCase().includes(keyword);
            option.hidden = !isVisible;
            option.classList.remove('is-active');
            if (isVisible) {
                visibleCount++;
            }
        });

        const firstVisibleOption = workerOptions.find(function (option) {
            return !option.hidden;
        });

        if (firstVisibleOption) {
            firstVisibleOption.classList.add('is-active');
        }

        if (!visibleCount) {
            const empty = document.createElement('div');
            empty.className = 'casting-worker-combo-empty';
            empty.textContent = 'No workers found';
            document.getElementById('workerFilterOptions').appendChild(empty);
        }
    }

    workerToggle.addEventListener('click', function () {
        if (workerCombo.classList.contains('is-open')) {
            closeWorkerCombo();
        } else {
            openWorkerCombo();
        }
    });

    workerSearch.addEventListener('input', function () {
        filterWorkerOptions(workerSearch.value);
    });

    workerOptions.forEach(function (option) {
        option.addEventListener('click', function () {
            workerOptions.forEach(function (item) {
                item.classList.remove('is-active');
            });
            option.classList.add('is-active');
            $('#workerFilter').val(option.dataset.value).trigger('change');
            workerText.textContent = option.textContent;
            closeWorkerCombo();
        });
    });

    document.addEventListener('click', function (event) {
        if (!workerCombo.contains(event.target)) {
            closeWorkerCombo();
        }
    });

    const castingMetalIssueTable = $('#castingMetalIssueTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.casting-metal-issue.index', $company->slug) }}",
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
            { data: 'assigned_metal_view', name: 'assigned_metal_count', orderable: false, searchable: false },
            { data: 'metal_weight_total_view', name: 'metal_weight_total', orderable: false, searchable: false },
            { data: 'issue_silver_wt_total_view', name: 'issue_silver_wt_total', orderable: false, searchable: false },
            { data: 'pending_metal_view', name: 'pending_metal_count', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#applyFilter').on('click', function () {
        castingMetalIssueTable.ajax.reload();
    });

    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        $('#workerFilter').val('');
        $('#workerFilterText').text('All Workers');
        workerOptions.forEach(function (item) {
            item.classList.remove('is-active');
        });
        castingMetalIssueTable.ajax.reload();
    });

    function normalizeDateRange() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        if (fromDate && toDate && fromDate > toDate) {
            $('#toDate').val(fromDate);
        }
    }

    $('#fromDate, #toDate, #workerFilter').on('change', function () {
        normalizeDateRange();
        castingMetalIssueTable.ajax.reload();
    });
</script>
@endpush
