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
                                    <th>Logo</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Users</th>
                                    <th>Plan Status</th>
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

<div class="modal fade" id="renewPlanModal" tabindex="-1" aria-labelledby="renewPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="renewPlanForm" class="modal-content renew-plan-modal">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="renewPlanModalLabel">Renew Company Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="renew-plan-info">
                    <span>Company</span>
                    <strong id="renewCompanyName">-</strong>
                </div>
                <div class="renew-plan-info">
                    <span>Current Expire Date & Time</span>
                    <strong id="renewCurrentExpiry">-</strong>
                </div>
                <div class="renew-plan-info">
                    <span>Renew Duration</span>
                    <strong>Next 1 Year</strong>
                </div>
                <div class="form-group mt-3">
                    <label for="renewRemarks">Remarks</label>
                    <textarea class="form-control" id="renewRemarks" name="remarks" rows="3" placeholder="Optional renewal note"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Renew Plan</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('styles')
<style>
    .plan-status-cell {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 155px;
    }

    .plan-status-cell .badge {
        width: fit-content;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
    }

    .plan-status-cell small {
        color: #c8c9d5;
        font-weight: 600;
        white-space: nowrap;
    }

    .renew-plan-modal {
        background: #2b2d42;
        color: #fff;
        border: 1px solid #3a3d5d;
    }

    .renew-plan-modal .modal-header,
    .renew-plan-modal .modal-footer {
        border-color: #3a3d5d;
    }

    .renew-plan-info {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid #3a3d5d;
    }

    .renew-plan-info span,
    .renew-plan-modal label {
        color: #c8c9d5;
    }

    .renew-plan-modal textarea {
        background: #303050;
        color: #fff;
        border-color: #454875;
    }
</style>
@endpush
@push('scripts')
<script>
    $(function() {
        $('#companyTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false, // ✅ IMPORTANT
            scrollX: false, // ✅ IMPORTANT
            responsive: true, // ✅ IMPORTANT
            order: [],
            ajax: "{{ route('superadmin.companies.index') }}",
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'logo',
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
                    data: 'plan_status',
                    orderable: false,
                    searchable: false
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

    $(document).on('click', '.renewPlanBtn', function() {
        $('#renewPlanForm').attr('action', $(this).data('url'));
        $('#renewCompanyName').text($(this).data('name') || '-');
        $('#renewCurrentExpiry').text($(this).data('expiry') || '-');
        $('#renewRemarks').val('');
        $('#renewPlanModal').modal('show');
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
