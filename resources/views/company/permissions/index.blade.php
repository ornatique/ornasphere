@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Permissions List</h4>

            <a href="{{ route('company.permissions.create', $company->slug) }}"
                class="btn btn-primary">
                <i class="typcn typcn-plus-outline"></i>
                Create Permission
            </a>
        </div>

        <div class="card-body">


            <table class="table table-bordered dataTable no-footer" id="permissions-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Used In Roles</th>
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
    $(function() {
        $('#permissions-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('company.permissions.index', $company->slug) }}",
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'name'
                },
                {
                    data: 'roles_count'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });
    });
</script>
@endpush