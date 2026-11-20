@extends('layout.admin')
@section('content')

<div class="content-wrapper company-list-page">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div><h4 class="card-title">Companies</h4><p class="company-list-intro">Manage company access, branches, users and subscriptions.</p></div>

             <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('superadmin.companies.validity-report') }}" class="btn btn-info">Company Validity Report</a>
                <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary float-end">
                    + Add Company
                </a>
             </div>
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
                <div class="form-group mt-3">
                    <label for="renewPlan">Plan <span class="text-danger">*</span></label>
                    <select class="form-control" id="renewPlan" name="plan" required>
                        <option value="trial">10-day Trial</option>
                        <option value="yearly" selected>Yearly</option>
                        @foreach([2, 3, 4, 5] as $years)
                            <option value="{{ $years }}_years">{{ $years }} Years</option>
                        @endforeach
                        <option value="custom">Custom Dates</option>
                    </select>
                    <small class="text-muted" id="renewPlanHint">For an active paid plan, selected duration is added to the current expiry date.</small>
                </div>
                <div class="row mt-3" id="renewCustomPlanDates" style="display:none">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="renewPlanStartDate">Plan From Date</label>
                            <input type="date" class="form-control" id="renewPlanStartDate" name="plan_start_date">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="renewPlanEndDate">Plan To Date</label>
                            <input type="date" class="form-control" id="renewPlanEndDate" name="plan_end_date">
                        </div>
                    </div>
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
    #companyTable td:nth-child(7) {min-width:165px}
    #companyTable td:last-child {min-width:245px;white-space:normal}
    #companyTable td:last-child .btn {margin:3px 2px}
    #companyTable .form-switch {margin-left:0!important;padding-left:2.5em}
    #companyTable .form-check-input {width:36px;height:20px;cursor:pointer}
    #companyTable .plan-status-cell small {font-size:11px;font-weight:400}
    .login-setup-actions {display:flex;flex-direction:column;gap:6px;width:150px;max-width:100%}
    .login-setup-actions form {display:block;width:100%;margin:0}
    .login-setup-actions .btn {display:block;width:100%;min-height:36px;margin:0!important;white-space:nowrap}
    .setup-link-expiry {font-size:10px;color:#c8c9d5;line-height:1.3}
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
        $('#renewPlan').val('yearly');
        $('#renewPlanStartDate, #renewPlanEndDate').val('');
        syncRenewPlanFields();
        $('#renewPlanModal').modal('show');
    });

    function syncRenewPlanFields() {
        const isCustom = $('#renewPlan').val() === 'custom';
        $('#renewCustomPlanDates').toggle(isCustom);
        $('#renewPlanStartDate, #renewPlanEndDate').prop('required', isCustom);
        $('#renewPlanHint').text(isCustom
            ? 'The selected From and To dates will be used exactly; the To date includes the full day.'
            : ($('#renewPlan').val() === 'trial'
                ? 'The trial starts today and expires after 10 days.'
                : 'For an active paid plan, selected duration is added to the current expiry date. If expired or on trial, it starts today.'));
    }

    $(document).on('change', '#renewPlan', syncRenewPlanFields);

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
            text: companyName + ' is active. This step only deactivates the company. Click Delete again after deactivation to permanently remove it.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Deactivate Company',
            confirmButtonColor: '#ff1a1a',
            cancelButtonText: 'Cancel'
        };

        const deletePrompt = {
            title: 'Delete company permanently?',
            text: 'This permanently deletes the company and its related data. Type the company name to confirm.',
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

                // Preserve the entered company name as SweetAlert's result.value;
                // returning true here replaces the text input value with a boolean.
                return value;
            }
        };

        Swal.fire(isActive ? deactivatePrompt : deletePrompt).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: isActive ? 'Deactivating company…' : 'Deleting company…',
                text: isActive ? 'Please wait while access is disabled.' : 'Please wait while company data is removed.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() { Swal.showLoading(); }
            });

            $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: 'DELETE',
                    confirm_name: isActive ? '' : result.value
                }
            }).done(function(response) {
                $('#companyTable').DataTable().ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: response.action === 'deactivated' ? 'Company deactivated' : 'Company deleted',
                    text: response.message || 'The action completed successfully.',
                    confirmButtonText: 'OK'
                });
            }).fail(function(xhr) {
                const message = xhr.responseJSON?.message || 'The request failed. Please try again.';
                Swal.fire({ icon: 'error', title: 'Action failed', text: message });
            });
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
