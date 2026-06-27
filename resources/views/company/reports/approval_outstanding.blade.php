@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Approval Outstanding Report</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
                    <button id="filter" class="btn btn-success">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    <button id="export_excel" class="btn btn-info">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 d-flex flex-wrap gap-4">
                    <div><strong>Voucher Count:</strong> <span id="summary_voucher_count">0</span></div>
                    <div><strong>Pending Pcs:</strong> <span id="summary_pending_pcs">0</span></div>
                    <div><strong>Pending Net Wt:</strong> <span id="summary_pending_net_wt">0.000</span></div>
                </div>
            </div>

            <table class="table table-bordered" id="approvalOutstandingTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Approval No</th>
                        <th>Date</th>
                        <th>Customer Name</th>
                        <th>Status</th>
                        <th>Pending Pcs</th>
                        <th>Pending Net Wt</th>
                        <th>Pending Amount</th>
                        <th>Remarks</th>
                        <th>Created By</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="approvalDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approval Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><strong>Approval No:</strong> <span id="modal_approval_no">-</span></div>
                    <div class="col-md-3"><strong>Date:</strong> <span id="modal_approval_date">-</span></div>
                    <div class="col-md-3"><strong>Customer:</strong> <span id="modal_customer_name">-</span></div>
                    <div class="col-md-3"><strong>Status:</strong> <span id="modal_status">-</span></div>
                    <div class="col-md-3"><strong>Pending Pcs:</strong> <span id="modal_pending_pcs">0</span></div>
                    <div class="col-md-3"><strong>Pending Net Wt:</strong> <span id="modal_pending_net_wt">0.000</span></div>
                    <div class="col-md-3"><strong>Pending Amount:</strong> <span id="modal_pending_amount">0.00</span></div>
                    <div class="col-md-3"><strong>Created By:</strong> <span id="modal_created_by">-</span></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>QR Code</th>
                                <th>HUID</th>
                                <th>Item</th>
                                <th>Gross Wt</th>
                                <th>Other Wt</th>
                                <th>Net Wt</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="approvalDetailsRows">
                            <tr><td colspan="9" class="text-center">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#from_date').val('');
    $('#to_date').val('');

    const table = $('#approvalOutstandingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.approval-outstanding.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            {
                data: 'approval_no',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return `<a href="#" class="approval-detail-link" data-id="${row.id}">${data}</a>`;
                }
            },
            { data: 'approval_date_fmt', orderable: false, searchable: false },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'status' },
            { data: 'pending_items', orderable: false, searchable: false },
            { data: 'pending_net_weight_fmt', orderable: false, searchable: false },
            { data: 'pending_total_amount_fmt', orderable: false, searchable: false },
            { data: 'remarks', orderable: false, searchable: false },
            { data: 'created_by', orderable: false, searchable: false },
        ]
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#from_date').val('');
        $('#to_date').val('');
        $('#customer_id').val('');
        table.draw();
    });

    table.on('xhr.dt', function (e, settings, json) {
        const summary = json && json.summary ? json.summary : {};
        $('#summary_voucher_count').text(summary.voucher_count ?? 0);
        $('#summary_pending_pcs').text(summary.pending_pcs ?? 0);
        const netWt = parseFloat(summary.pending_net_weight ?? 0);
        $('#summary_pending_net_wt').text(netWt.toFixed(3));
    });

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.approval-outstanding.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.approval-outstanding.export.pdf', $company->slug) }}?" + queryParams();
    });

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    $('#approvalOutstandingTable').on('click', '.approval-detail-link', function (e) {
        e.preventDefault();

        const approvalId = $(this).data('id');
        const url = "{{ route('company.reports.approval-outstanding.details', [$company->slug, ':id']) }}".replace(':id', approvalId);

        $('#approvalDetailsRows').html('<tr><td colspan="9" class="text-center">Loading...</td></tr>');
        $('#approvalDetailsModal').modal('show');

        $.get(url)
            .done(function (res) {
                const approval = res.approval || {};
                const summary = res.summary || {};
                const items = res.items || [];

                $('#modal_approval_no').text(approval.approval_no || '-');
                $('#modal_approval_date').text(approval.approval_date || '-');
                $('#modal_customer_name').text(approval.customer_name || '-');
                $('#modal_status').text(approval.status || '-');
                $('#modal_created_by').text(approval.created_by || '-');
                $('#modal_pending_pcs').text(summary.pending_pcs || 0);
                $('#modal_pending_net_wt').text(summary.pending_net_weight || '0.000');
                $('#modal_pending_amount').text(summary.pending_amount || '0.00');

                if (!items.length) {
                    $('#approvalDetailsRows').html('<tr><td colspan="9" class="text-center">No pending items found</td></tr>');
                    return;
                }

                const rows = items.map(function (item, index) {
                    return `<tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(item.qr_code || '-')}</td>
                        <td>${escapeHtml(item.huid || '-')}</td>
                        <td>${escapeHtml(item.item_name || '-')}</td>
                        <td>${escapeHtml(item.gross_weight || '0.000')}</td>
                        <td>${escapeHtml(item.other_weight || '0.000')}</td>
                        <td>${escapeHtml(item.net_weight || '0.000')}</td>
                        <td>${escapeHtml(item.total_amount || '0.00')}</td>
                        <td>${escapeHtml(item.status || '-')}</td>
                    </tr>`;
                }).join('');

                $('#approvalDetailsRows').html(rows);
            })
            .fail(function () {
                $('#approvalDetailsRows').html('<tr><td colspan="9" class="text-center text-danger">Unable to load approval details</td></tr>');
            });
    });
});
</script>
@endpush
