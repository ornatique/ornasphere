@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Casting Sorting Voucher List</h4>
        </div>
        <div class="card-body">
            <div class="casting-sorting-filters">
                <div class="filter-field">
                    <label for="fromDate">From Date</label>
                    <input type="date" id="fromDate" class="form-control" value="{{ $fromDate }}">
                </div>
                <div class="filter-field">
                    <label for="toDate">To Date</label>
                    <input type="date" id="toDate" class="form-control" value="{{ $toDate }}">
                </div>
                <div class="filter-actions">
                    <button type="button" id="applyFilter" class="btn btn-primary">Filter</button>
                    <button type="button" id="resetFilter" class="btn btn-secondary">Reset</button>
                </div>
            </div>

            <div class="table-responsive casting-sorting-list-scroll">
                <table class="table table-bordered table-striped" id="castingSortingTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Voucher Number</th>
                            <th>Date Time</th>
                            <th>Process</th>
                            <th>Worker Name</th>
                            <th>Total Pcs</th>
                            <th>Sorting Wt</th>
                            <th>Quantity</th>
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
    .casting-sorting-list-scroll { max-height: calc(100vh - 360px); overflow-y: auto; }
    .casting-sorting-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }
    .casting-sorting-filters label { display: block; margin-bottom: 5px; color: #b8b8d4; font-size: 12px; }
    .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .filter-actions .btn { min-width: 86px; }
    #castingSortingTable thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    @media (max-width: 767px) {
        .casting-sorting-filters { grid-template-columns: 1fr; }
        .filter-actions .btn { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script>
    const defaultFromDate = @json($fromDate);
    const defaultToDate = @json($toDate);
    const castingSortingTable = $('#castingSortingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.casting-sorting.index', $company->slug) }}",
            data: function (data) {
                data.from_date = $('#fromDate').val();
                data.to_date = $('#toDate').val();
            }
        },
        order: [[2, 'desc']],
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no_view', name: 'voucher_no' },
            { data: 'date_time_view', name: 'created_at' },
            { data: 'process_name', name: 'process.name', orderable: false },
            { data: 'worker_name', name: 'jobWorker.name', orderable: false },
            { data: 'total_pcs_view', name: 'tree_receive_count', orderable: false, searchable: false },
            { data: 'sorting_weight_view', name: 'sorting_weight_total', orderable: false, searchable: false },
            { data: 'sorting_quantity_view', name: 'sorting_quantity_total', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });
    $('#applyFilter').on('click', function () { castingSortingTable.ajax.reload(); });
    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        castingSortingTable.ajax.reload();
    });
    function normalizeDateRange() {
        const fromDate = $('#fromDate').val();
        const toDate = $('#toDate').val();
        if (fromDate && toDate && fromDate > toDate) {
            $('#toDate').val(fromDate);
        }
    }
    $('#fromDate, #toDate').on('change', function () {
        normalizeDateRange();
        castingSortingTable.ajax.reload();
    });
</script>
@endpush
