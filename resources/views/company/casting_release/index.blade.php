@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Casting Receive Voucher List</h4>
        </div>
        <div class="card-body">
            <div class="casting-release-filters">
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

            <div class="table-responsive casting-release-list-scroll">
                <table class="table table-bordered table-striped" id="castingReleaseTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Voucher Number</th>
                            <th>Date Time</th>
                            <th>Process</th>
                            <th>Worker Name</th>
                            <th>Assigned Receive</th>
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
    .casting-release-list-scroll {
        max-height: calc(100vh - 360px);
        overflow-y: auto;
    }

    .casting-release-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) minmax(220px, 320px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }

    .casting-release-filters label {
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

    #castingReleaseTable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
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
        .casting-release-filters {
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
    const castingReleaseTable = $('#castingReleaseTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.casting-release.index', $company->slug) }}",
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
            { data: 'assigned_receive_view', name: 'assigned_receive_count', orderable: false, searchable: false },
            { data: 'pending_receive_view', name: 'pending_receive_count', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#applyFilter').on('click', function () {
        castingReleaseTable.ajax.reload();
    });

    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        $('#workerFilter').val('');
        castingReleaseTable.ajax.reload();
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
        castingReleaseTable.ajax.reload();
    });
</script>
@endpush
