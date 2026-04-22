@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Production Step List</h4>
            <a href="{{ route('company.production-step.create', $company->slug) }}" class="btn btn-primary">
                + Add Production Step
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="productionStepTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Step Name</th>
                            <th>Labour Formula</th>
                            <th>Receivable Loss</th>
                            <th>Production Cost</th>
                            <th>Remarks</th>
                            
                            <th>Assigned Users</th>
                            <th>Modified</th>
                            <th>Modified Count</th>
                            <th>Created By</th>
                            <th>Created at</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#productionStepTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('company.production-step.index', $company->slug) }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'labour_formula_name', name: 'labourFormula.name', orderable: false },
            { data: 'receivable_loss_badge', name: 'receivable_loss', orderable: false, searchable: false },
            { data: 'production_cost_name', name: 'productionCost.name', orderable: false },
            { data: 'remarks', name: 'remarks' },
            { data: 'assigned_users_count', name: 'assigned_jobworkers_count', searchable: false },
            { data: 'modified_at_view', name: 'updated_at' },
            { data: 'modified_count', name: 'modified_count' },
             { data: 'user_name', name: 'createdByUser.name', orderable: false, searchable: false },
            { data: 'created_at_view', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    $(document).on('click', '.deleteBtn', function () {
        if (!confirm('Are you sure to delete this record?')) {
            return;
        }

        const url = $(this).data('url');

        $.ajax({
            url: url,
            type: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function (response) {
                $('#productionStepTable').DataTable().ajax.reload();
                alert(response.message);
            },
            error: function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Delete failed';
                alert(message);
            }
        });
    });
</script>
@endpush
