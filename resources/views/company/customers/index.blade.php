@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Customers List</h4>
            <a href="{{ route('company.customers.create', $company->slug) }}" class="btn btn-primary">
                <i class="typcn typcn-plus-outline"></i>
                Create Customer
            </a>
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
    $('#customers-table').DataTable({
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
});
</script>
@endpush

