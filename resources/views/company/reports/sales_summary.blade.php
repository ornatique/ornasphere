@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Sales Summary Report</h4>
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
                <div class="col-md-12">
                    <div class="custom-column-panel">
                        <div class="custom-column-title">Custom Report Columns</div>
                        <div class="custom-column-options">
                            <label><input type="checkbox" class="report-column-toggle" value="voucher_no" checked disabled> Voucher No</label>
                            <label><input type="checkbox" class="report-column-toggle" value="date" checked> Date</label>
                            <label><input type="checkbox" class="report-column-toggle" value="customer" checked> Customer</label>
                            <label><input type="checkbox" class="report-column-toggle" value="qty_pcs" checked> Qty</label>
                            <label><input type="checkbox" class="report-column-toggle" value="gross_weight" checked> Gross Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="other_weight"> Other Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="net_weight" checked> Net Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="fine_weight" checked> Fine Wt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="metal_amount" checked> Metal Amt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="labour_amount" checked> Labour Amt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="other_amount" checked> Other Amt</label>
                            <label><input type="checkbox" class="report-column-toggle" value="net_total" checked> Total</label>
                            <label><input type="checkbox" class="report-column-toggle" value="remarks" checked> Remarks</label>
                            <label><input type="checkbox" class="report-column-toggle" value="created_by" checked> Created By</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="report-table-scroll">
                <table class="table table-bordered" id="salesSummaryTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Qty</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Metal Amt</th>
                            <th>Labour Amt</th>
                            <th>Other Amt</th>
                            <th>Total</th>
                            <th>Remarks</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th>Total</th>
                            <th></th>
                            <th></th>
                            <th id="t_qty_pcs">0</th>
                            <th id="t_gross_weight">0.000</th>
                            <th id="t_other_weight">0.000</th>
                            <th id="t_net_weight">0.000</th>
                            <th id="t_fine_weight">0.000</th>
                            <th id="t_metal_amount">0.00</th>
                            <th id="t_labour_amount">0.00</th>
                            <th id="t_other_amount">0.00</th>
                            <th id="t_net_total">0.00</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header sale-details-header">
                <h5 class="modal-title mb-0">Sale Details</h5>
                <div class="sale-details-actions">
                    <button type="button" class="btn btn-info btn-sm" id="modal_export_excel">Excel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="modal_export_pdf">PDF</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3" id="saleDetailsSummary">
                    <div class="col-md-3 detail-summary-field" data-column="voucher_no"><strong>Voucher No:</strong> <span id="modal_voucher_no">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="date"><strong>Date:</strong> <span id="modal_sale_date">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="customer"><strong>Customer:</strong> <span id="modal_customer_name">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="created_by"><strong>Created By:</strong> <span id="modal_created_by">-</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="qty_pcs"><strong>Qty:</strong> <span id="modal_qty_pcs">0</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="gross_weight"><strong>Gross Wt:</strong> <span id="modal_gross_weight">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="other_weight"><strong>Other Wt:</strong> <span id="modal_other_weight">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="net_weight"><strong>Net Wt:</strong> <span id="modal_net_weight">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="fine_weight"><strong>Fine Wt:</strong> <span id="modal_fine_weight">0.000</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="metal_amount"><strong>Metal Amt:</strong> <span id="modal_metal_amount">0.00</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="labour_amount"><strong>Labour Amt:</strong> <span id="modal_labour_amount">0.00</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="other_amount"><strong>Other Amt:</strong> <span id="modal_other_amount">0.00</span></div>
                    <div class="col-md-3 detail-summary-field" data-column="net_total"><strong>Total:</strong> <span id="modal_total_amount">0.00</span></div>
                </div>

                <div class="sale-details-tools">
                    <input type="text" id="saleDetailsSearch" class="form-control" placeholder="Search label, HUID, item, value...">
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead id="saleDetailsHead">
                            <tr><th>#</th><th>Label</th><th>HUID</th><th>Item</th></tr>
                        </thead>
                        <tbody id="saleDetailsRows">
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

    .report-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid rgba(148, 163, 184, 0.18);
    }

    #salesSummaryTable {
        width: 100% !important;
        min-width: 1320px;
        margin-bottom: 0;
    }

    #salesSummaryTable th,
    #salesSummaryTable td {
        white-space: nowrap;
    }

    #salesSummaryTable_wrapper {
        max-width: 100%;
        overflow: visible;
    }

    #salesSummaryTable_wrapper .row {
        margin-left: 0;
        margin-right: 0;
    }

    #salesSummaryTable_wrapper .dataTables_info,
    #salesSummaryTable_wrapper .dataTables_paginate {
        padding-top: 14px;
    }

    #saleDetailsModal .modal-dialog {
        margin-top: 12px;
        margin-bottom: 12px;
        height: calc(100vh - 24px);
    }

    #saleDetailsModal .modal-content {
        max-height: calc(100vh - 24px);
        overflow: hidden;
    }

    #saleDetailsModal .modal-body {
        overflow-y: auto;
    }

    #saleDetailsModal .sale-details-header {
        align-items: center;
        gap: 12px;
    }

    #saleDetailsModal .sale-details-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    #saleDetailsModal .modal-title {
        font-size: 22px;
        font-weight: 800;
        color: #ffffff;
    }

    #saleDetailsModal .sale-details-tools {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 12px;
    }

    #saleDetailsModal #saleDetailsSearch {
        max-width: 360px;
        background: #2f3157;
        border-color: #4a5390;
        color: #ffffff;
    }

    #saleDetailsModal #saleDetailsSearch::placeholder {
        color: #b8bfd7;
    }

    #saleDetailsModal .table-responsive {
        border: 1px solid rgba(148, 163, 184, 0.28);
        border-radius: 6px;
        background: #242842;
    }

    #saleDetailsModal .table {
        color: #f8fafc;
        margin-bottom: 0;
    }

    #saleDetailsModal .table th,
    #saleDetailsModal .table td {
        border-color: rgba(148, 163, 184, 0.24);
        vertical-align: middle;
    }

    #saleDetailsModal .table thead th {
        background: #2b3154;
        color: #ffffff;
        padding: 14px 16px;
        font-size: 16px;
        font-weight: 800;
    }

    #saleDetailsModal .table tbody td {
        background: #30354f;
        color: #eef2ff;
        padding: 13px 16px;
        font-weight: 600;
    }

    #saleDetailsModal .table tbody tr:nth-child(even) td {
        background: #2a2f49;
    }

    #saleDetailsModal .table tbody tr:hover td {
        background: #35406a;
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
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);
    if ($.fn.select2) {
        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Persons',
            allowClear: true
        });
    }

    const columnMap = {
        voucher_no: 1,
        date: 2,
        customer: 3,
        qty_pcs: 4,
        gross_weight: 5,
        other_weight: 6,
        net_weight: 7,
        fine_weight: 8,
        metal_amount: 9,
        labour_amount: 10,
        other_amount: 11,
        net_total: 12,
        remarks: 13,
        created_by: 14,
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

    const table = $('#salesSummaryTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('company.reports.sales-summary.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        drawCallback: function () {
            const json = this.api().ajax.json() || {};
            const totals = json.totals || {};

            $('#t_qty_pcs').text((totals.qty_pcs ?? 0).toString());
            $('#t_gross_weight').text(Number(totals.gross_weight ?? 0).toFixed(3));
            $('#t_other_weight').text(Number(totals.other_weight ?? 0).toFixed(3));
            $('#t_net_weight').text(Number(totals.net_weight ?? 0).toFixed(3));
            $('#t_fine_weight').text(Number(totals.fine_weight ?? 0).toFixed(3));
            $('#t_metal_amount').text(Number(totals.metal_amount ?? 0).toFixed(2));
            $('#t_labour_amount').text(Number(totals.labour_amount ?? 0).toFixed(2));
            $('#t_other_amount').text(Number(totals.other_amount ?? 0).toFixed(2));
            $('#t_net_total').text(Number(totals.net_total ?? 0).toFixed(2));

            const rowCount = Number(json.recordsFiltered ?? json.recordsTotal ?? 0);
            setExportButtonsEnabled(rowCount > 0);
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            {
                data: 'voucher_no',
                render: function (data, type, row) {
                    if (type !== 'display') {
                        return data;
                    }
                    return `<a href="#" class="sale-detail-link" data-id="${row.sale_id}">${data}</a>`;
                }
            },
            { data: 'sale_date' },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'qty_pcs', orderable: false, searchable: false },
            { data: 'gross_weight', orderable: false, searchable: false },
            { data: 'other_weight', orderable: false, searchable: false },
            { data: 'net_weight', orderable: false, searchable: false },
            { data: 'fine_weight', orderable: false, searchable: false },
            { data: 'metal_amount', orderable: false, searchable: false },
            { data: 'labour_amount', orderable: false, searchable: false },
            { data: 'other_amount', orderable: false, searchable: false },
            { data: 'net_total' },
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
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('').trigger('change.select2');
        table.draw();
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
        label: 'Label',
        huid: 'HUID',
        item: 'Item',
        qty_pcs: 'Qty',
        gross_weight: 'Gross Wt',
        other_weight: 'Other Wt',
        net_weight: 'Net Wt',
        purity: 'Purity',
        waste_percent: 'Waste %',
        net_purity: 'Net Purity',
        fine_weight: 'Fine Wt',
        metal_rate: 'Metal Rate',
        metal_amount: 'Metal Amt',
        labour_rate: 'Labour Rate',
        labour_amount: 'Labour Amt',
        other_amount: 'Other Amt',
        total_amount: 'Total',
        remarks: 'Remarks',
    };

    function selectedDetailColumns() {
        const selected = selectedReportColumns();
        const columns = ['label', 'huid', 'item'];
        ['qty_pcs', 'gross_weight', 'other_weight', 'net_weight', 'purity', 'waste_percent', 'net_purity', 'fine_weight', 'metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount', 'total_amount', 'remarks'].forEach(function (key) {
            if (selected.includes(key) || (key === 'total_amount' && selected.includes('net_total'))) {
                columns.push(key);
            }
        });
        return columns;
    }

    function updateModalSummaryVisibility() {
        const selected = selectedReportColumns();
        $('#saleDetailsSummary .detail-summary-field').each(function () {
            const column = $(this).data('column');
            $(this).toggle(column === 'voucher_no' || selected.includes(column));
        });
    }

    let activeSaleId = null;
    let activeDetailItems = [];

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function detailSearchText(item) {
        return [
            item.label,
            item.huid,
            item.item_name,
            item.qty_pcs,
            item.gross_weight,
            item.other_weight,
            item.net_weight,
            item.purity,
            item.waste_percent,
            item.net_purity,
            item.fine_weight,
            item.metal_rate,
            item.metal_amount,
            item.labour_rate,
            item.labour_amount,
            item.other_amount,
            item.total_amount,
            item.remarks,
        ].join(' ').toLowerCase();
    }

    function filteredDetailItems() {
        const search = ($('#saleDetailsSearch').val() || '').toLowerCase().trim();
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
        $('#saleDetailsHead').html(head);

        if (!items.length) {
            $('#saleDetailsRows').html(`<tr><td colspan="${columns.length + 1}" class="text-center">No sale items found</td></tr>`);
            return;
        }

        const rows = items.map(function (item, index) {
            const values = {
                label: item.label || '-',
                huid: item.huid || '-',
                item: item.item_name || '-',
                qty_pcs: item.qty_pcs || 0,
                gross_weight: item.gross_weight || '0.000',
                other_weight: item.other_weight || '0.000',
                net_weight: item.net_weight || '0.000',
                purity: item.purity || '0.000',
                waste_percent: item.waste_percent || '0.000',
                net_purity: item.net_purity || '0.000',
                fine_weight: item.fine_weight || '0.000',
                metal_rate: item.metal_rate || '0.00',
                metal_amount: item.metal_amount || '0.00',
                labour_rate: item.labour_rate || '0.00',
                labour_amount: item.labour_amount || '0.00',
                other_amount: item.other_amount || '0.00',
                total_amount: item.total_amount || '0.00',
                remarks: item.remarks || '-',
            };

            return '<tr><td>' + (index + 1) + '</td>' +
                columns.map(key => `<td>${escapeHtml(values[key])}</td>`).join('') +
                '</tr>';
        }).join('');

        $('#saleDetailsRows').html(rows);
    }

    $('#export_excel').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }

        window.location.href = "{{ route('company.reports.sales-summary.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        if ($(this).prop('disabled')) {
            return;
        }

        window.location.href = "{{ route('company.reports.sales-summary.export.pdf', $company->slug) }}?" + queryParams();
    });

    $('#salesSummaryTable').on('click', '.sale-detail-link', function (e) {
        e.preventDefault();

        activeSaleId = $(this).data('id');
        const url = "{{ route('company.reports.sales-summary.details', [$company->slug, ':id']) }}".replace(':id', activeSaleId);

        updateModalSummaryVisibility();
        activeDetailItems = [];
        $('#saleDetailsSearch').val('');
        $('#saleDetailsRows').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
        $('#saleDetailsModal').modal('show');

        $.get(url)
            .done(function (res) {
                const sale = res.sale || {};
                const summary = res.summary || {};
                const items = res.items || [];

                $('#modal_voucher_no').text(sale.voucher_no || '-');
                $('#modal_sale_date').text(sale.sale_date || '-');
                $('#modal_customer_name').text(sale.customer_name || '-');
                $('#modal_created_by').text(sale.created_by || '-');
                $('#modal_qty_pcs').text(summary.qty_pcs || 0);
                $('#modal_gross_weight').text(summary.gross_weight || '0.000');
                $('#modal_other_weight').text(summary.other_weight || '0.000');
                $('#modal_net_weight').text(summary.net_weight || '0.000');
                $('#modal_fine_weight').text(summary.fine_weight || '0.000');
                $('#modal_metal_amount').text(summary.metal_amount || '0.00');
                $('#modal_labour_amount').text(summary.labour_amount || '0.00');
                $('#modal_other_amount').text(summary.other_amount || '0.00');
                $('#modal_total_amount').text(summary.total_amount || '0.00');

                activeDetailItems = items;
                renderDetailsTable(filteredDetailItems());
            })
            .fail(function () {
                $('#saleDetailsRows').html('<tr><td colspan="4" class="text-center text-danger">Unable to load sale details</td></tr>');
            });
    });

    $('#saleDetailsSearch').on('input', function () {
        renderDetailsTable(filteredDetailItems());
    });

    $('#modal_export_excel').on('click', function () {
        if (!activeSaleId) return;
        const url = "{{ route('company.reports.sales-summary.details.excel', [$company->slug, ':id']) }}".replace(':id', activeSaleId);
        window.location.href = url + '?' + $.param({ columns: selectedReportColumns().join(',') });
    });

    $('#modal_export_pdf').on('click', function () {
        if (!activeSaleId) return;
        const url = "{{ route('company.reports.sales-summary.details.pdf', [$company->slug, ':id']) }}".replace(':id', activeSaleId);
        window.location.href = url + '?' + $.param({ columns: selectedReportColumns().join(',') });
    });
});
</script>
@endpush
