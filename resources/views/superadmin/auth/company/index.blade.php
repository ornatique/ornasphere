@extends('layout.admin')
@section('content')

<div class="content-wrapper company-list-page">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div><h4 class="card-title">Companies</h4><p class="company-list-intro">Manage company access, branches, users and subscriptions.</p></div>

             <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary float-end">
                    + Add Company
                </a>
        </div>
      
        <div class="card-body">
            

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
                                    <th>Login setup</th>
                                    <th>Status</th>
                                    <th>Manage company</th>
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
                    <strong>1 Year</strong><small>Trial: starts today. Active paid plan: extends the current expiry.</small>
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
    .company-list-page .card {border:1px solid #41435d;border-radius:14px;overflow:hidden}
    .company-list-page .card-header {padding:24px;align-items:center;gap:16px;flex-wrap:wrap}
    .company-list-page .card-header .card-title {font-size:25px;color:#fff;margin:0 0 6px}
    .company-list-intro {color:#bec4d9;margin:0;line-height:1.5}
    .company-list-page .card-body {padding:24px}
    .company-list-page .btn {min-height:36px;padding:8px 13px!important;line-height:1.3!important;border-radius:7px;color:#fff!important;font-size:12px;font-weight:600}
    .company-list-page .btn-primary {background:#5265cf;border:1px solid #7182eb}
    .company-list-page .btn-info,.company-list-page .btn-secondary {background:#354264;border:1px solid #61729f}
    .company-list-page .btn-success {background:#215747;border:1px solid #438b73}
    .company-list-page .btn-danger {background:#522d3b;border:1px solid #a5566e;color:#ffdae4!important}
    .company-list-page .btn:hover {filter:brightness(1.18)}
    #companyTable {width:100%!important;border-collapse:separate;border-spacing:0}
    #companyTable th {background:#303249;color:#d4dcf3;font-size:12px;padding:16px 12px;white-space:nowrap}
    #companyTable td {padding:20px 12px;vertical-align:middle;border-bottom:1px solid #3c3e56;line-height:1.5}
    #companyTable tbody tr:hover {background:#2c2e44}
    #companyTable td:nth-child(3) {min-width:180px;white-space:normal}
    #companyTable td:nth-child(4) {max-width:230px;white-space:normal;overflow-wrap:anywhere}
    #companyTable td:last-child {min-width:245px;white-space:normal}
    #companyTable td:last-child .btn {margin:3px 2px}
    #companyTable .form-switch {margin-left:0!important;padding-left:2.5em}
    #companyTable .form-check-input {width:36px;height:20px;cursor:pointer}
    #companyTable .plan-status-cell small {font-size:11px;font-weight:400}
    .company-list-page .dataTables_filter input,.company-list-page .dataTables_length select {min-height:38px;border:1px solid #565a7a;border-radius:7px;background:#303149;color:#fff;padding:6px 10px}
    .company-list-page .dataTables_filter,.company-list-page .dataTables_length {margin-bottom:20px}
    .company-list-page .dataTables_info,.company-list-page .dataTables_paginate {margin-top:16px}
    .company-list-page a:focus-visible,.company-list-page button:focus-visible {outline:2px solid #aab8ff;outline-offset:3px}
    @media(max-width:767px){.company-list-page .card-header,.company-list-page .card-body{padding:16px}}

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
        const control = $(this);
        const previousStatus = !this.checked;
        control.prop('disabled', true);
        let companyId = $(this).data('id');

        $.ajax({
            url: "{{ url('superadmin/companies') }}/" + companyId + "/toggle-status",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(res) {
                control.prop('checked', res.success ? !!res.status : previousStatus);
                if (res.success) {
                    $('.deleteBtn[data-id="' + companyId + '"]')
                        .data('active', res.status ? 1 : 0)
                        .attr('data-active', res.status ? 1 : 0);
                    toastr.success('Status updated successfully');
                    $('#companyTable').DataTable().ajax.reload(null, false);
                }
            },
            error: function() {
                control.prop('checked', previousStatus);
                toastr.error('Something went wrong');
            },
            complete: function() {
                control.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.deleteBtn', function() {
        const companyName = String($(this).data('name') || '');
        const url = $(this).data('url');
        const isActive = Number($(this).data('active')) === 1;

        const deactivatePrompt = {
            title: 'Deactivate company first?',
            html: '<strong>' + companyName + '</strong> is active. First click will only deactivate this company.<br><br>After it is inactive, click Delete again for permanent delete.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Deactivate Company',
            confirmButtonColor: '#ff1a1a',
            cancelButtonText: 'Cancel'
        };

        const deletePrompt = {
            title: 'Delete company permanently?',
            html: 'This will permanently delete <strong>' + companyName + '</strong> and all related data from all tables.<br><br>Type the company name to confirm.',
            input: 'text',
            inputPlaceholder: companyName,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete Permanently',
            confirmButtonColor: '#ff1a1a',
            cancelButtonText: 'Cancel',
            preConfirm: (value) => {
                if (value !== companyName) {
                    Swal.showValidationMessage('Company name does not match.');
                    return false;
                }

                return true;
            }
        };

        Swal.fire(isActive ? deactivatePrompt : deletePrompt).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const form = $('<form>', {
                method: 'POST',
                action: url
            });

            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: "{{ csrf_token() }}"
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: '_method',
                value: 'DELETE'
            }));

            $('body').append(form);
            form.trigger('submit');
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
