@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Vacuum Voucher List</h4>
            <a href="{{ route('company.vacuum-vouchers.create', $company->slug) }}" class="btn btn-primary">
                + Add Voucher
            </a>
        </div>
        <div class="card-body">
            <div class="vacuum-voucher-filters">
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

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="vacuumVoucherTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Process</th>
                            <th>Worker</th>
                            <th>Gross Wt</th>
                            <th>Buch Wt</th>
                            <th>Net Wt</th>
                            <th>Silver Wt</th>
                            <th>Modified</th>
                            <th>Modified Count</th>
                            <th>Created By</th>
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
    .vacuum-voucher-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) minmax(220px, 320px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }

    .vacuum-voucher-filters label {
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

    @media (max-width: 767px) {
        .vacuum-voucher-filters {
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
    const vacuumVoucherTable = $('#vacuumVoucherTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.vacuum-vouchers.index', $company->slug) }}",
            data: function (data) {
                data.from_date = $('#fromDate').val();
                data.to_date = $('#toDate').val();
                data.worker_id = $('#workerFilter').val();
            }
        },
        order: [[12, 'desc']],
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no', name: 'voucher_no' },
            { data: 'voucher_date_view', name: 'voucher_date' },
            { data: 'process_name', name: 'process.name', orderable: false },
            { data: 'worker_name', name: 'jobWorker.name', orderable: false },
            { data: 'gross_wt_total_view', name: 'gross_wt_total' },
            { data: 'buch_wt_total_view', name: 'buch_wt_total' },
            { data: 'net_wt_total_view', name: 'net_wt_total' },
            { data: 'silver_wt_total_view', name: 'silver_wt_total' },
            { data: 'modified_at_view', name: 'updated_at' },
            { data: 'modified_count', name: 'modified_count' },
            { data: 'user_name', name: 'createdByUser.name', orderable: false, searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#applyFilter').on('click', function () {
        vacuumVoucherTable.ajax.reload();
    });

    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        $('#workerFilter').val('');
        vacuumVoucherTable.ajax.reload();
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
        vacuumVoucherTable.ajax.reload();
    });

    $(document).on('click', '.deleteBtn', function () {
        if (!confirm('Are you sure to delete this voucher?')) {
            return;
        }

        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function (response) {
                vacuumVoucherTable.ajax.reload();
                alert(response.message);
            },
            error: function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Delete failed';
                alert(message);
            }
        });
    });
</script>
@endpush
