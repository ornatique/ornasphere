@extends('company_layout.admin')

@php
    $fromDate = request()->filled('from_date') ? request('from_date') : now()->subDays(6)->toDateString();
    $toDate = request()->filled('to_date') ? request('to_date') : now()->toDateString();
@endphp

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Vacuum Buch List</h4>
            <a href="{{ route('company.vacuum-buchs.create', $company->slug) }}" class="btn btn-primary">
                + Add Buch
            </a>
        </div>
        <div class="card-body">
            <div class="vacuum-buch-filters">
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

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="vacuumBuchTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Buch No</th>
                            <th>Size (Inch)</th>
                            <th>Weight</th>
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
    .vacuum-buch-filters {
        display: grid;
        grid-template-columns: minmax(160px, 200px) minmax(160px, 200px) auto;
        gap: 12px;
        align-items: end;
        margin-bottom: 16px;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.025);
    }

    .vacuum-buch-filters label {
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
        .vacuum-buch-filters {
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
    const vacuumBuchTable = $('#vacuumBuchTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.vacuum-buchs.index', $company->slug) }}",
            data: function (data) {
                data.from_date = $('#fromDate').val();
                data.to_date = $('#toDate').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'buch_no', name: 'buch_no' },
            { data: 'size_inch', name: 'size_inch' },
            { data: 'weight', name: 'weight' },
            { data: 'modified_at_view', name: 'updated_at' },
            { data: 'modified_count', name: 'modified_count' },
            { data: 'user_name', name: 'createdByUser.name', orderable: false, searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $('#applyFilter').on('click', function () {
        vacuumBuchTable.ajax.reload();
    });

    $('#resetFilter').on('click', function () {
        $('#fromDate').val(defaultFromDate);
        $('#toDate').val(defaultToDate);
        vacuumBuchTable.ajax.reload();
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
        vacuumBuchTable.ajax.reload();
    });

    $(document).on('click', '.deleteBtn', function () {
        if (!confirm('Are you sure to delete this record?')) {
            return;
        }

        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function (response) {
                vacuumBuchTable.ajax.reload();
                alert(response.message);
            },
            error: function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Delete failed';
                alert(message);
            }
        });
    });
</script>
@endpush
