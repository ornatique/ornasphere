@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card worker-loss-report">
        <div class="card-header">
            <h4 class="card-title mb-0">Worker Loss Report</h4>
        </div>
        <div class="card-body">
            <div class="filter-box mb-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" id="from_date" class="form-control" value="{{ $defaultFromDate }}">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" id="to_date" class="form-control" value="{{ $defaultToDate }}">
                    </div>
                    <div class="col-md-2">
                        <label>Worker</label>
                        <select id="worker_id" class="form-select">
                            <option value="">All Workers</option>
                            @foreach($workers as $worker)
                                <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 voucher-search-wrapper">
                        <label>Voucher No</label>
                        <input type="text" id="voucher_no" class="form-control" placeholder="VV26-" autocomplete="off">
                        <div id="voucher_results" class="list-group"></div>
                    </div>
                    <div class="col-md-2">
                        <label>Stage</label>
                        <select id="stage" class="form-select">
                            <option value="">All Stages</option>
                            <option value="Casting Receive">Casting Receive</option>
                            <option value="Tree Cutting Receive">Tree Cutting Receive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Loss Type</label>
                        <select id="loss_type" class="form-select">
                            <option value="">All</option>
                            <option value="plus">Plus</option>
                            <option value="minus">Minus</option>
                            <option value="zero">Zero</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>&nbsp;</label>
                        <div class="form-check only-loss-check">
                            <input class="form-check-input" type="checkbox" id="only_loss">
                            <label class="form-check-label" for="only_loss">Show Only Loss</label>
                        </div>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end gap-2">
                        <button id="filter" class="btn btn-primary">Filter</button>
                        <button id="reset" class="btn btn-secondary">Reset</button>
                        <button id="export_excel" class="btn btn-info">Excel</button>
                        <button id="export_pdf" class="btn btn-success">PDF</button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <div class="summary-card">
                        <span>Rows</span>
                        <strong id="total_rows">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="summary-card">
                        <span>Source Wt</span>
                        <strong id="total_source_wt">0.000</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="summary-card">
                        <span>Receive Wt</span>
                        <strong id="total_receive_wt">0.000</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="summary-card">
                        <span>Bhuko</span>
                        <strong id="total_bhuko">0.000</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="summary-card">
                        <span>Loss</span>
                        <strong id="total_loss">0.000</strong>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="summary-panel">
                        <h5>Worker Summary</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Worker</th>
                                        <th>Rows</th>
                                        <th>Source Wt</th>
                                        <th>Receive Wt</th>
                                        <th>Bhuko</th>
                                        <th>Loss</th>
                                    </tr>
                                </thead>
                                <tbody id="worker_summary_rows">
                                    <tr><td colspan="6" class="text-center">No data available</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="summary-panel">
                        <h5>Stage Summary</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Stage</th>
                                        <th>Rows</th>
                                        <th>Source Wt</th>
                                        <th>Receive Wt</th>
                                        <th>Bhuko</th>
                                        <th>Loss</th>
                                    </tr>
                                </thead>
                                <tbody id="stage_summary_rows">
                                    <tr><td colspan="6" class="text-center">No data available</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered w-100" id="workerLossTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Date Time</th>
                            <th>Worker</th>
                            <th>Voucher No</th>
                            <th>B. No</th>
                            <th>Stage</th>
                            <th>Source Wt</th>
                            <th>Receive Wt</th>
                            <th>Bhuko</th>
                            <th>Loss</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .filter-box,
    .summary-card,
    .summary-panel {
        border: 1px solid #343852;
        background: #282a3f;
        padding: 12px;
    }

    .summary-panel h5 {
        color: #fff;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .summary-panel th,
    .summary-panel td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .summary-panel td:not(:first-child),
    .summary-panel th:not(:first-child) {
        text-align: right;
    }

    .summary-card span {
        display: block;
        color: #c6c8dc;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .summary-card strong {
        color: #fff;
        font-size: 15px;
    }

    #workerLossTable th,
    #workerLossTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .worker-loss-report .dataTables_wrapper,
    .worker-loss-report .dataTables_scroll,
    .worker-loss-report .dataTables_scrollHead,
    .worker-loss-report .dataTables_scrollBody {
        width: 100% !important;
    }

    .worker-loss-report .dataTables_filter {
        margin-bottom: 10px;
    }

    .worker-loss-report .dataTables_filter input {
        min-width: 190px;
    }

    .worker-loss-report #workerLossTable {
        table-layout: fixed;
    }

    .worker-loss-report #workerLossTable th:nth-child(1),
    .worker-loss-report #workerLossTable td:nth-child(1) {
        width: 70px;
    }

    .worker-loss-report #workerLossTable th:nth-child(2),
    .worker-loss-report #workerLossTable td:nth-child(2) {
        width: 150px;
    }

    .worker-loss-report #workerLossTable th:nth-child(3),
    .worker-loss-report #workerLossTable td:nth-child(3),
    .worker-loss-report #workerLossTable th:nth-child(4),
    .worker-loss-report #workerLossTable td:nth-child(4),
    .worker-loss-report #workerLossTable th:nth-child(5),
    .worker-loss-report #workerLossTable td:nth-child(5) {
        width: 110px;
    }

    .worker-loss-report #workerLossTable th:nth-child(6),
    .worker-loss-report #workerLossTable td:nth-child(6) {
        width: 155px;
    }

    .worker-loss-report #workerLossTable th:nth-child(7),
    .worker-loss-report #workerLossTable td:nth-child(7),
    .worker-loss-report #workerLossTable th:nth-child(8),
    .worker-loss-report #workerLossTable td:nth-child(8),
    .worker-loss-report #workerLossTable th:nth-child(9),
    .worker-loss-report #workerLossTable td:nth-child(9),
    .worker-loss-report #workerLossTable th:nth-child(10),
    .worker-loss-report #workerLossTable td:nth-child(10) {
        width: 95px;
        text-align: right;
    }

    .voucher-search-wrapper {
        position: relative;
    }

    #voucher_results {
        position: absolute;
        z-index: 2000;
        width: calc(100% - 24px);
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: #2b2f4a;
        display: none;
    }

    #voucher_results .list-group-item {
        background: #2b2f4a;
        color: #f5f5f7;
        border-color: rgba(255, 255, 255, 0.1);
        cursor: pointer;
        padding: 8px 12px;
    }

    #voucher_results .list-group-item:hover {
        background: #3a3f63;
    }

    .only-loss-check {
        height: 47px;
        display: flex;
        align-items: center;
        gap: 6px;
        padding-left: 28px;
        border: 1px solid #343852;
        background: #30324f;
    }

    .worker-loss-link {
        color: #9fc5ff;
        text-decoration: underline;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const defaultFromDate = "{{ $defaultFromDate }}";
    const defaultToDate = "{{ $defaultToDate }}";
    const $voucherInput = $('#voucher_no');
    const $voucherResults = $('#voucher_results');
    let voucherSuggestTimer = null;

    const table = $('#workerLossTable').DataTable({
        processing: true,
        serverSide: true,
        scrollX: false,
        autoWidth: false,
        ajax: {
            url: "{{ route('company.reports.worker-loss.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.worker_id = $('#worker_id').val();
                d.voucher_no = $('#voucher_no').val();
                d.stage = $('#stage').val();
                d.loss_type = $('#loss_type').val();
                d.only_loss = $('#only_loss').is(':checked') ? 1 : 0;
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'process_datetime', name: 'process_datetime' },
            { data: 'worker_name', name: 'worker_name' },
            { data: 'voucher_no_html', name: 'voucher_no' },
            { data: 'buch_no_html', name: 'buch_no' },
            { data: 'stage', name: 'stage' },
            { data: 'source_wt', name: 'source_wt', orderable: false, searchable: false },
            { data: 'receive_wt', name: 'receive_wt', orderable: false, searchable: false },
            { data: 'bhuko', name: 'bhuko', orderable: false, searchable: false },
            { data: 'loss', name: 'loss', orderable: false, searchable: false },
        ]
    });

    table.on('xhr', function () {
        const totals = (table.ajax.json() || {}).totals || {};
        $('#total_rows').text(totals.row_count || 0);
        $('#total_source_wt').text(formatWeight(totals.source_wt));
        $('#total_receive_wt').text(formatWeight(totals.receive_wt));
        $('#total_bhuko').text(formatWeight(totals.bhuko));
        $('#total_loss').text(formatWeight(totals.loss));
        renderSummaryRows('#worker_summary_rows', (table.ajax.json() || {}).worker_summary || [], 'Worker');
        renderSummaryRows('#stage_summary_rows', (table.ajax.json() || {}).stage_summary || [], 'Stage');
    });

    $('#filter').on('click', function () {
        table.draw();
    });

    $('#reset').on('click', function () {
        $('#from_date').val(defaultFromDate);
        $('#to_date').val(defaultToDate);
        $('#worker_id').val('');
        $('#voucher_no').val('');
        $('#stage').val('');
        $('#loss_type').val('');
        $('#only_loss').prop('checked', false);
        $voucherResults.hide().empty();
        table.draw();
    });

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.worker-loss.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.worker-loss.export.pdf', $company->slug) }}?" + queryParams();
    });

    $voucherInput.on('input focus blur', function (e) {
        clearTimeout(voucherSuggestTimer);
        voucherSuggestTimer = setTimeout(function () {
            loadVoucherSuggestions();
        }, e.type === 'blur' ? 80 : 180);
    });

    $voucherInput.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $voucherResults.hide().empty();
            table.draw();
        }
    });

    $(document).on('mousedown', '.selectVoucherNo', function (e) {
        e.preventDefault();
        const voucherNo = String($(this).data('voucher-no') || '').trim();
        if (!voucherNo) return;
        $voucherInput.val(voucherNo);
        $voucherResults.hide().empty();
        table.draw();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.voucher-search-wrapper').length) {
            $voucherResults.hide();
        }
    });

    function formatWeight(value) {
        return Number(value || 0).toFixed(3);
    }

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            worker_id: $('#worker_id').val(),
            voucher_no: $('#voucher_no').val(),
            stage: $('#stage').val(),
            loss_type: $('#loss_type').val(),
            only_loss: $('#only_loss').is(':checked') ? 1 : 0
        });
    }

    function loadVoucherSuggestions() {
        $.get("{{ route('company.reports.worker-loss.suggest', $company->slug) }}", {
            q: $voucherInput.val(),
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            worker_id: $('#worker_id').val(),
            stage: $('#stage').val(),
            loss_type: $('#loss_type').val(),
            only_loss: $('#only_loss').is(':checked') ? 1 : 0
        }, function (res) {
            const list = (res && res.data) ? res.data : [];
            if (!list.length) {
                $voucherResults.html('<div class="list-group-item">No result</div>').show();
                return;
            }

            let html = '';
            list.forEach(function (row) {
                const voucherNo = escapeHtml(row.voucher_no || '');
                const dateTime = escapeHtml(row.date_time || '');
                html += `
                    <a href="#" class="list-group-item list-group-item-action selectVoucherNo" data-voucher-no="${voucherNo}">
                        ${voucherNo}<br>
                        <small>${dateTime || '-'}</small>
                    </a>
                `;
            });
            $voucherResults.html(html).show();
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function renderSummaryRows(target, rows, emptyLabel) {
        if (!rows.length) {
            $(target).html(`<tr><td colspan="6" class="text-center">No ${emptyLabel.toLowerCase()} summary</td></tr>`);
            return;
        }

        let html = '';
        rows.forEach(function (row) {
            html += `
                <tr>
                    <td>${escapeHtml(row.label || '-')}</td>
                    <td>${row.rows || 0}</td>
                    <td>${formatWeight(row.source_wt)}</td>
                    <td>${formatWeight(row.receive_wt)}</td>
                    <td>${formatWeight(row.bhuko)}</td>
                    <td>${formatWeight(row.loss)}</td>
                </tr>
            `;
        });
        $(target).html(html);
    }
});
</script>
@endpush
