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
                        <option value="">All Persons</option>
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
                    <div><strong>Pending Gross Wt:</strong> <span id="summary_pending_gross_wt">0.000</span></div>
                    <div><strong>Pending Other Wt:</strong> <span id="summary_pending_other_wt">0.000</span></div>
                    <div><strong>Pending Net Wt:</strong> <span id="summary_pending_net_wt">0.000</span></div>
                    <div><strong>Pending Other Amount:</strong> <span id="summary_pending_other_amount">0.00</span></div>
                    <div><strong>Pending Amount:</strong> <span id="summary_pending_amount">0.00</span></div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="custom-column-panel">
                        <div class="custom-column-title">Custom Report Columns</div>
                        <div class="custom-column-options">
                            <label><input type="checkbox" class="report-column-toggle" value="approval_no" checked disabled> Approval No</label>
                            <label><input type="checkbox" class="report-column-toggle" value="date" checked> Date</label>
                            <label><input type="checkbox" class="report-column-toggle" value="customer" checked> Customer</label>
                            <label><input type="checkbox" class="report-column-toggle" value="status" checked> Status</label>
                            <label><input type="checkbox" class="report-column-toggle" value="pending_pcs" checked> Pending Pcs</label>
                            <label><input type="checkbox" class="report-column-toggle" value="gross_weight"> Gross Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="other_weight"> Other Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="net_weight" checked> Net Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="other_amount"> Other Amount</label>
                            <label><input type="checkbox" class="report-column-toggle" value="pending_amount" checked> Pending Amount</label>
                            <label><input type="checkbox" class="report-column-toggle" value="remarks" checked> Remarks</label>
                            <label><input type="checkbox" class="report-column-toggle" value="created_by" checked> Created By</label>
                        </div>
                    </div>
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
                        <th>Gross Wt</th>
                        <th>Other Wt</th>
                        <th>Pending Net Wt</th>
                        <th>Other Amount</th>
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
            <div class="modal-header approval-details-header">
                <h5 class="modal-title mb-0">Approval Details</h5>
                <div class="approval-details-actions">
                    <button type="button" class="btn btn-info btn-sm" id="modal_export_excel">Excel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="modal_export_pdf">PDF</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3" id="approvalDetailsSummary">
                    <div class="col-md-3 detail-summary-field" data-column="approval_no"><strong>Approval No:</strong> <span id="modal_approval_no">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="date"><strong>Date:</strong> <span id="modal_approval_date">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="customer"><strong>Customer:</strong> <span id="modal_customer_name">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="status"><strong>Status:</strong> <span id="modal_status">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="pending_pcs"><strong>Pending Pcs:</strong> <span id="modal_pending_pcs">0</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="gross_weight"><strong>Gross Wt:</strong> <span id="modal_pending_gross_wt">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="other_weight"><strong>Other Wt:</strong> <span id="modal_pending_other_wt">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="net_weight"><strong>Pending Net Wt:</strong> <span id="modal_pending_net_wt">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="other_amount"><strong>Other Amount:</strong> <span id="modal_pending_other_amount">0.00</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="pending_amount"><strong>Pending Amount:</strong> <span id="modal_pending_amount">0.00</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="created_by"><strong>Created By:</strong> <span id="modal_created_by">-</span></div>
                </div>

                <div class="approval-details-tools">
                    <input type="text" id="approvalDetailsSearch" class="form-control" placeholder="Search QR, HUID, item, status...">
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead id="approvalDetailsHead">
                            <tr><th>#</th><th>QR Code</th><th>HUID</th><th>Item</th></tr>
                        </thead>
                        <tbody id="approvalDetailsRows">
                            <tr><td colspan="4" class="text-center">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .custom-column-panel {
        border: 1px solid rgba(255,255,255,0.08);
        background: rgba(255,255,255,0.03);
        padding: 12px 14px;
        border-radius: 8px;
    }

    .custom-column-title {
        font-weight: 700;
        margin-bottom: 10px;
    }

    .custom-column-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 18px;
    }

    .custom-column-options label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        white-space: nowrap;
    }

    #approvalDetailsModal .modal-dialog {
        margin-top: 12px;
        margin-bottom: 12px;
        height: calc(100vh - 24px);
    }

    #approvalDetailsModal .modal-content {
        max-height: calc(100vh - 24px);
        overflow: hidden;
    }

    #approvalDetailsModal .modal-body {
        overflow-y: auto;
    }

    #approvalDetailsModal .approval-details-header {
        align-items: center;
        gap: 12px;
    }

    #approvalDetailsModal .approval-details-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    #approvalDetailsModal .modal-title {
        font-size: 22px;
        font-weight: 800;
        color: #ffffff;
    }

    #approvalDetailsModal .approval-details-tools {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 12px;
    }

    #approvalDetailsModal #approvalDetailsSearch {
        max-width: 360px;
    }

    #approvalDetailsModal #approvalDetailsHead th {
        font-size: 16px;
        font-weight: 800;
    }

    #approvalDetailsModal .table-responsive {
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 6px;
        background: #242842;
    }

    #approvalDetailsModal .table {
        color: #f8fafc;
        margin-bottom: 0;
    }

    #approvalDetailsModal .table th,
    #approvalDetailsModal .table td {
        border-color: rgba(148, 163, 184, 0.24);
        vertical-align: middle;
    }

    #approvalDetailsModal .table thead th {
        background: #2b3154;
        color: #ffffff;
        padding: 14px 16px;
    }

    #approvalDetailsModal .table tbody td {
        background: #30354f;
        color: #eef2ff;
        padding: 13px 16px;
        font-weight: 600;
    }

    #approvalDetailsModal .table tbody tr:nth-child(even) td {
        background: #2a2f49;
    }

    #approvalDetailsModal .table tbody tr:hover td {
        background: #35406a;
    }

    #approvalDetailsModal #approvalDetailsSearch {
        background: #2f3157;
        border-color: #4a5390;
        color: #ffffff;
    }

    #approvalDetailsModal #approvalDetailsSearch::placeholder {
        color: #b8bfd7;
    }

    #export_excel:disabled,
    #export_pdf:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    $('#from_date').val('');
    $('#to_date').val('');
    if ($.fn.select2) {
        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Persons',
            allowClear: true
        });
    }

    const columnMap = {
        approval_no: 1,
        date: 2,
        customer: 3,
        status: 4,
        pending_pcs: 5,
        gross_weight: 6,
        other_weight: 7,
        net_weight: 8,
        other_amount: 9,
        pending_amount: 10,
        remarks: 11,
        created_by: 12,
    };

    function selectedReportColumns() {
        const columns = [];
        $('.report-column-toggle').each(function () {
            if ($(this).is(':checked')) {
                columns.push($(this).val());
            }
        });
        return columns;
    }

    function setExportButtonsEnabled(enabled) {
        $('#export_excel, #export_pdf')
            .prop('disabled', !enabled)
            .toggleClass('disabled', !enabled);
    }

    setExportButtonsEnabled(false);

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
            { data: 'pending_gross_weight_fmt', orderable: false, searchable: false },
            { data: 'pending_other_weight_fmt', orderable: false, searchable: false },
            { data: 'pending_net_weight_fmt', orderable: false, searchable: false },
            { data: 'pending_other_amount_fmt', orderable: false, searchable: false },
            { data: 'pending_total_amount_fmt', orderable: false, searchable: false },
            { data: 'remarks', orderable: false, searchable: false },
            { data: 'created_by', orderable: false, searchable: false },
        ]
    });

    function applyColumnVisibility() {
        const selected = selectedReportColumns();
        Object.entries(columnMap).forEach(function ([key, index]) {
            table.column(index).visible(selected.includes(key), false);
        });
        table.columns.adjust().draw(false);
    }

    $('.report-column-toggle').on('change', applyColumnVisibility);
    applyColumnVisibility();

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#from_date').val('');
        $('#to_date').val('');
        $('#customer_id').val('').trigger('change.select2');
        table.draw();
    });

    table.on('xhr.dt', function (e, settings, json) {
        const summary = json && json.summary ? json.summary : {};
        $('#summary_voucher_count').text(summary.voucher_count ?? 0);
        $('#summary_pending_pcs').text(summary.pending_pcs ?? 0);
        const grossWt = parseFloat(summary.pending_gross_weight ?? 0);
        const otherWt = parseFloat(summary.pending_other_weight ?? 0);
        const netWt = parseFloat(summary.pending_net_weight ?? 0);
        const otherAmount = parseFloat(summary.pending_other_amount ?? 0);
        const pendingAmount = parseFloat(summary.pending_amount ?? 0);
        $('#summary_pending_gross_wt').text(grossWt.toFixed(3));
        $('#summary_pending_other_wt').text(otherWt.toFixed(3));
        $('#summary_pending_net_wt').text(netWt.toFixed(3));
        $('#summary_pending_other_amount').text(otherAmount.toFixed(2));
        $('#summary_pending_amount').text(pendingAmount.toFixed(2));

        const rowCount = Number(json?.recordsFiltered ?? json?.recordsTotal ?? 0);
        setExportButtonsEnabled(rowCount > 0);
    });

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val(),
            columns: selectedReportColumns().join(',')
        });
    }

    const detailColumnLabels = {
        qr_code: 'QR Code',
        huid: 'HUID',
        item: 'Item',
        status: 'Status',
        gross_weight: 'Gross Wt',
        other_weight: 'Other Wt',
        net_weight: 'Net Wt',
        other_amount: 'Other Amount',
        pending_amount: 'Amount',
    };

    function selectedDetailColumns() {
        const selected = selectedReportColumns();
        const columns = ['qr_code', 'huid', 'item'];
        ['status', 'gross_weight', 'other_weight', 'net_weight', 'other_amount', 'pending_amount'].forEach(function (key) {
            if (selected.includes(key)) {
                columns.push(key);
            }
        });
        return columns;
    }

    function updateModalSummaryVisibility() {
        const selected = selectedReportColumns();
        $('#approvalDetailsSummary .detail-summary-field').each(function () {
            const column = $(this).data('column');
            $(this).toggle(column === 'approval_no' || selected.includes(column));
        });
    }

    let activeDetailItems = [];

    function detailSearchText(item) {
        return [
            item.qr_code,
            item.huid,
            item.item_name,
            item.status,
            item.gross_weight,
            item.other_weight,
            item.net_weight,
            item.other_amount,
            item.total_amount,
        ].join(' ').toLowerCase();
    }

    function filteredDetailItems() {
        const search = ($('#approvalDetailsSearch').val() || '').toLowerCase().trim();
        if (!search) {
            return activeDetailItems;
        }

        return activeDetailItems.filter(function (item) {
            return detailSearchText(item).includes(search);
        });
    }

    function renderDetailsTable(items) {
        const columns = selectedDetailColumns();
        const head = '<tr><th>#</th>' + columns.map(key => `<th>${detailColumnLabels[key]}</th>`).join('') + '</tr>';
        $('#approvalDetailsHead').html(head);

        if (!items.length) {
            $('#approvalDetailsRows').html(`<tr><td colspan="${columns.length + 1}" class="text-center">No pending items found</td></tr>`);
            return;
        }

        const rows = items.map(function (item, index) {
            const values = {
                qr_code: item.qr_code || '-',
                huid: item.huid || '-',
                item: item.item_name || '-',
                status: item.status || '-',
                gross_weight: item.gross_weight || '0.000',
                other_weight: item.other_weight || '0.000',
                net_weight: item.net_weight || '0.000',
                other_amount: item.other_amount || '0.00',
                pending_amount: item.total_amount || '0.00',
            };

            return '<tr><td>' + (index + 1) + '</td>' +
                columns.map(key => `<td>${escapeHtml(values[key])}</td>`).join('') +
                '</tr>';
        }).join('');

        $('#approvalDetailsRows').html(rows);
    }

    $('#export_excel').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }

        window.location.href = "{{ route('company.reports.approval-outstanding.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }

        window.location.href = "{{ route('company.reports.approval-outstanding.export.pdf', $company->slug) }}?" + queryParams();
    });

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    let activeApprovalId = null;

    $('#approvalOutstandingTable').on('click', '.approval-detail-link', function (e) {
        e.preventDefault();

        const approvalId = $(this).data('id');
        activeApprovalId = approvalId;
        const url = "{{ route('company.reports.approval-outstanding.details', [$company->slug, ':id']) }}".replace(':id', approvalId);

        updateModalSummaryVisibility();
        activeDetailItems = [];
        $('#approvalDetailsSearch').val('');
        $('#approvalDetailsRows').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
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
                $('#modal_pending_gross_wt').text(summary.pending_gross_weight || '0.000');
                $('#modal_pending_other_wt').text(summary.pending_other_weight || '0.000');
                $('#modal_pending_net_wt').text(summary.pending_net_weight || '0.000');
                $('#modal_pending_other_amount').text(summary.pending_other_amount || '0.00');
                $('#modal_pending_amount').text(summary.pending_amount || '0.00');

                activeDetailItems = items;
                renderDetailsTable(filteredDetailItems());
            })
            .fail(function () {
                $('#approvalDetailsRows').html('<tr><td colspan="4" class="text-center text-danger">Unable to load approval details</td></tr>');
            });
    });

    $('#approvalDetailsSearch').on('input', function () {
        renderDetailsTable(filteredDetailItems());
    });

    $('#modal_export_excel').on('click', function () {
        if (!activeApprovalId) return;
        const url = "{{ route('company.reports.approval-outstanding.details.excel', [$company->slug, ':id']) }}".replace(':id', activeApprovalId);
        window.location.href = url + '?' + $.param({ columns: selectedReportColumns().join(',') });
    });

    $('#modal_export_pdf').on('click', function () {
        if (!activeApprovalId) return;
        const url = "{{ route('company.reports.approval-outstanding.details.pdf', [$company->slug, ':id']) }}".replace(':id', activeApprovalId);
        window.location.href = url + '?' + $.param({ columns: selectedReportColumns().join(',') });
    });
});
</script>
@endpush
