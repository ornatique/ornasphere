@extends('layout.admin')
@section('content')

<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Company List</h4>

             <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary float-end">
                    + Add Company
                </a>
        </div>
      
        <div class="card-body">
            <h4 class="card-title">List Of Companies</h4>

            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table id="companyTable" class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Users</th>
                                    <th> Reset Password URL</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $(function() {
        $('#companyTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false, // ✅ IMPORTANT
            scrollX: false, // ✅ IMPORTANT
            responsive: true, // ✅ IMPORTANT
            ajax: "{{ route('superadmin.companies.index') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'name'
                },
                {
                    data: 'email'
                },
                {
                    data: 'users_count'
                },
                {
                    data:'password_set_url'
                },
                {
                    data: 'status',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                },
            ]
        });
    });

    $(document).on('change', '.toggleStatus', function() {
        let companyId = $(this).data('id');

        $.ajax({
            url: "{{ url('superadmin/companies') }}/" + companyId + "/toggle-status",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(res) {
                if (res.success) {
                    toastr.success('Status updated successfully');
                }
            },
            error: function() {
                toastr.error('Something went wrong');
            }
        });
    });
</script>
<script>

$(document).on('click', '.copyBtn', function() {

    var url = $(this).data('url');

    navigator.clipboard.writeText(url).then(function() {

       

    }).catch(function(err) {

        console.error('Copy failed', err);

    });

});

</script>

@endpush