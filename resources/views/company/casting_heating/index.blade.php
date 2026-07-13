@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Casting Heating Voucher List</h4>
        </div>
        <div class="card-body">
            <div class="casting-heating-filters">
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

            <div class="table-responsive casting-heating-list-scroll">
                <table class="table table-bordered table-striped" id="castingHeatingTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Voucher Number</th>
                            <th>Date & Time</th>
                            <th>Process</th>
                            <th>Worker Name</th>
                            <th>Total Pcs</th>
                            <th>In Bhati Pcs</th>
                            <th>Created At</th>
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
    .casting-heating-list-scroll {
        max-height: calc(100vh - 330px);
        overflow-y: auto;
    }

    .casting-heating-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) minmax(220px, 320px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }

    .casting-heating-filters label {
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

    #castingHeatingTable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
    }

    @media (max-width: 767px) {
        .casting-heating-filters {
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
    const castingHeatingTable = $('#castingHeatingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.casting-heating.index', $company->slug) }}",
            data: function (data) {
                data.from_date = $('#fromDate').val();
                data.to_date = $('#toDate').val();
                data.worker_id = $('#workerFilter').val();
            }
        },
        order: [[7, 'desc']],
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no_view', name: 'voucher_no' },
            { data: 'date_time_view', name: 'created_at' },
            { data: 'process_name', name: 'process.name', orderable: false },
            { data: 'worker_name', name: 'jobWorker.name', orderable: false },
            { data: 'total_pcs', name: 'items_count', searchable: false },
            { data: 'in_bhati_pcs', name: 'in_bhati_count', searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#applyFilter').on('click', function () {
        castingHeatingTable.ajax.reload();
    });

    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        $('#workerFilter').val('');
        castingHeatingTable.ajax.reload();
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
        castingHeatingTable.ajax.reload();
    });
</script>
@endpush
