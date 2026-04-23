@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Customers List</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('company.customers.export.excel', $company->slug) }}" class="btn btn-success">Export Excel</a>
                <a href="{{ route('company.customers.export.pdf', $company->slug) }}" class="btn btn-danger">Export PDF</a>
                <a href="{{ route('company.customers.create', $company->slug) }}" class="btn btn-primary">
                    <i class="typcn typcn-plus-outline"></i>
                    Create Customer
                </a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="customers-table">
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
    const table = $('#customers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('company.customers.index', $company->slug) }}",
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
        if (!confirm('Are you sure? Customer will be set inactive (not deleted).')) return;

        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function (resp) {
                table.ajax.reload();
                alert(resp.message || 'Customer updated successfully');
            },
            error: function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Action failed';
                alert(msg);
            }
        });
    });
});
</script>
@endpush
