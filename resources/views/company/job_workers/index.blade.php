@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Job Workers List</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('company.job-workers.export.excel', $company->slug) }}" class="btn btn-success">Export Excel</a>
                <a href="{{ route('company.job-workers.export.pdf', $company->slug) }}" class="btn btn-danger">Export PDF</a>
                <a href="{{ route('company.job-workers.create', $company->slug) }}" class="btn btn-primary">
                    <i class="typcn typcn-plus-outline"></i>
                    Create Job Worker
                </a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="job-workers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push("scripts")
<script>
$(function () {
    const table = $('#job-workers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('company.job-workers.index', $company->slug) }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'mobile_no', name: 'mobile_no' },
            { data: 'city', name: 'city' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $(document).on('click', '.deleteBtn', function () {
        if (!confirm('Are you sure to delete this Job Worker?')) return;

        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function (resp) {
                table.ajax.reload();
                alert(resp.message || 'Deleted successfully');
            },
            error: function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Delete failed';
                alert(msg);
            }
        });
    });
});
</script>
@endpush
