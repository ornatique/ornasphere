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

@push('scripts')
<script>
    $('#vacuumBuchTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('company.vacuum-buchs.index', $company->slug) }}",
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
                $('#vacuumBuchTable').DataTable().ajax.reload();
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
