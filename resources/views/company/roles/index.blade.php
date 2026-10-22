@extends('company_layout.admin')

@section('content')
@if(count((array) request('management_branches', [])) > 1)
<p class="px-4 pt-3">New records will be created in <strong>{{ $company->name }}</strong>. To create in another branch, select only that branch and click View.</p>
@endif
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Roles List</h4>

            <a href="{{ route('company.roles.create', $company->slug) }}"
                class="btn btn-primary">
                <i class="typcn typcn-plus-outline"></i>
                Create Role
            </a>
        </div>
        <div class="card-body">

            <table class="table table-bordered dataTable no-footer" id="rolesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Role Name</th>
                        <th>Company / Branch</th>
                        <th>Users Count</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
        <div class="card-footer text-end">
           
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $('#rolesTable').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        ajax: "{!! route('company.roles.index', array_merge(['slug' => $company->slug], request()->only('management_branches'))) !!}",
        order: [
            [1, 'asc']
        ], // 👈 ORDER BY REAL COLUMN (name)

        columns: [{
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                orderable: false, // ✅ IMPORTANT
                searchable: false // ✅ IMPORTANT
            },
            {
                data: 'name',
                name: 'name'
            },
            { data: 'company_name', orderable: false, searchable: false },
            {
                data: 'users_count',
                name: 'users_count',
                searchable: false, // ✅ THIS FIX
                orderable: true
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false
            }
        ]
    });
</script>

@endpush