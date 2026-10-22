@extends('company_layout.admin')

@section('content')
@if(count((array) request('management_branches', [])) > 1)
<p class="px-4 pt-3">New records will be created in <strong>{{ $company->name }}</strong>. Select only another branch and click View to create there.</p>
@endif
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Vacuum Process List</h4>
            <a href="{{ route('company.vacuum-processes.create', $company->slug) }}" class="btn btn-primary">
                + Add Process
            </a>
        </div>
        <div class="card-body">
            @include('company_layout.casting_exports')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="vacuumProcessTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Modified</th>
                            <th>Modified Count</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Company / Branch</th>
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
    $('#vacuumProcessTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{!! route('company.vacuum-processes.index', array_merge(['slug' => $company->slug], request()->only('management_branches'))) !!}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'modified_at_view', name: 'updated_at' },
            { data: 'modified_count', name: 'modified_count' },
            { data: 'user_name', name: 'createdByUser.name', orderable: false, searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'company_name', name: 'company_name', orderable: false, searchable: false },
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
                $('#vacuumProcessTable').DataTable().ajax.reload();
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
