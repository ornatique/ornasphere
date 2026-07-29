@extends('company_layout.admin')

@section('content')
@php
    $defaultFromDate = now()->subDays(6)->toDateString();
    $defaultToDate = now()->toDateString();
@endphp
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Jobwork Receive Voucher List</h4>
            <a href="{{ route('company.jobwork-receive.create', $company->slug) }}" class="btn btn-primary">
                + Add Received Voucher
            </a>
        </div>
        <div class="card-body">
            <form class="row g-2 mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">From Date</label>
                    <input type="date" id="from_date" class="form-control" value="{{ $defaultFromDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">To Date</label>
                    <input type="date" id="to_date" class="form-control" value="{{ $defaultToDate }}">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="button" id="filterBtn" class="btn btn-info">Apply Filter</button>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="button" id="resetBtn" class="btn btn-secondary">Reset</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered" id="jobworkReceiveTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Voucher Date</th>
                            <th>Jobworker</th>
                            <th>Production Step</th>
                            <th>Issue Net Wt</th>
                            <th>Receive Net Wt</th>
                            <th>Pending Net Wt</th>
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
    const table = $('#jobworkReceiveTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.jobwork-receive.index', $company->slug) }}",
            data: function(d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
            }
        },
        order: [],
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no', name: 'voucher_no' },
            { data: 'jobwork_date_view', name: 'jobwork_date' },
            { data: 'jobworker_name', name: 'jobWorker.name', orderable: false },
            { data: 'production_step_name', name: 'productionStep.name', orderable: false },
            { data: 'issue_net_wt_sum', name: 'issue_net_wt_sum', orderable: false, searchable: false },
            { data: 'receive_net_wt_sum', name: 'receive_net_wt_sum', orderable: false, searchable: false },
            { data: 'pending_net_wt', name: 'pending_net_wt', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $('#filterBtn').on('click', function() {
        table.ajax.reload();
    });

    $('#resetBtn').on('click', function() {
        $('#from_date').val("{{ $defaultFromDate }}");
        $('#to_date').val("{{ $defaultToDate }}");
        table.ajax.reload();
    });
</script>
@endpush
