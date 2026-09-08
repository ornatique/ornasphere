@extends('company_layout.admin')

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
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="vacuumBuchTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Buch No</th>
                            <th>Size (Inch)</th>
                            <th>Weight</th>
                            <th>Weight Status</th>
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

<div class="modal fade" id="weightHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buch Weight History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="historyBuchNo" class="mb-3"></h6>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Old Weight</th>
                                <th>New Weight</th>
                                <th>Changed By</th>
                                <th>Changed At</th>
                            </tr>
                        </thead>
                        <tbody id="weightHistoryBody">
                            <tr>
                                <td colspan="5" class="text-center">No history found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const vacuumBuchTable = $('#vacuumBuchTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.vacuum-buchs.index', $company->slug) }}"
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'buch_no', name: 'buch_no' },
            { data: 'size_inch', name: 'size_inch' },
            { data: 'weight', name: 'weight' },
            { data: 'weight_change_view', name: 'weight_histories_count', orderable: false, searchable: false },
            { data: 'modified_at_view', name: 'updated_at' },
            { data: 'modified_count', name: 'modified_count' },
            { data: 'user_name', name: 'createdByUser.name', orderable: false, searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
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

    $(document).on('click', '.historyBtn', function () {
        const url = $(this).data('url');
        const buchNo = $(this).data('buch') || '';
        const $body = $('#weightHistoryBody');

        $('#historyBuchNo').text(buchNo ? `Buch No: ${buchNo}` : '');
        $body.html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
        $('#weightHistoryModal').modal('show');

        $.get(url)
            .done(function (response) {
                const rows = Array.isArray(response.data) ? response.data : [];

                if (!rows.length) {
                    $body.html('<tr><td colspan="5" class="text-center">No history found</td></tr>');
                    return;
                }

                $body.html(rows.map(function (row, index) {
                    return `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${row.old_weight}</td>
                            <td>${row.new_weight}</td>
                            <td>${row.changed_by}</td>
                            <td>${row.changed_at}</td>
                        </tr>
                    `;
                }).join(''));
            })
            .fail(function () {
                $body.html('<tr><td colspan="5" class="text-center text-danger">Unable to load history</td></tr>');
            });
    });
</script>
@endpush
