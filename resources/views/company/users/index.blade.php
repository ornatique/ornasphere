@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Users List</h4>

            <a href="{{ route('company.users.create', $company->slug) }}"
                class="btn btn-primary">
                <i class="typcn typcn-plus-outline"></i>
                Create User
            </a>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
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
@push("scripts")
<script>
    $(function() {
        $('#users-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('company.users.index', $company->slug) }}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'email',
                    name: 'email'
                },
                {
                    data: 'role',
                    name: 'role'
                },
                {
                    data: 'status',
                    name: 'status'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                },
            ]
        });
    });
</script>


<script>
document.addEventListener("click", function(e) {

    if(e.target.classList.contains("toggle-status-btn")) {

        let btn = e.target;
        let url = btn.dataset.url;

        fetch(url, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                "Content-Type": "application/json"
            }
        })
        .then(response => response.json())
        .then(data => {

            if(data.status === true) {

                // ✅ Reload DataTable only
                $('#users-table').DataTable().ajax.reload(null, false);

            } else {
                alert(data.message);
            }

        });

    }

});

</script>
@endpush
