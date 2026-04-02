@extends('company_layout.admin')

@section('content')
<style>
    .sale-search-wrap {
        position: relative;
    }

    #saleSuggestion {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 2000;
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        background: #2b2f4a;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }

    #saleSuggestion .list-group-item {
        background: #2b2f4a;
        color: #f3f3f7;
        border-color: rgba(255, 255, 255, 0.12);
        cursor: pointer;
    }

    #saleSuggestion .list-group-item:hover {
        background: #3a3f63;
    }

    #saleSuggestion .list-group-item.disabled {
        pointer-events: none;
        opacity: 0.45;
    }

    .return-grid-wrap {
        overflow-x: auto;
        overflow-y: visible;
    }

    #selectedQrTable {
        min-width: 2200px;
    }

    #selectedQrTable th,
    #selectedQrTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #selectedQrTable td:nth-child(2) {
        min-width: 170px;
        white-space: normal;
        line-height: 1.2;
    }

    .other-amount-wrap {
        min-width: 170px;
    }

    #otherChargeTable {
        min-width: 1450px;
    }

    #otherChargeTable th,
    #otherChargeTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #otherChargeTable .form-control,
    #otherChargeTable .form-select {
        min-width: 110px;
        text-align: right;
    }

    #otherChargeTable .charge-select {
        text-align: left;
        min-width: 140px;
    }

    #otherChargeTable .charge-sr {
        width: 44px;
        text-align: center;
        font-weight: 600;
    }

    #otherChargeTable .charge-row {
        cursor: pointer;
    }

    #otherChargeTable .charge-select-col {
        text-align: center;
    }
</style>

<div class="content-wrapper">
    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Select Sale For Return</h4>

            <button type="button" class="btn btn-warning" id="openApprovalModal">
                Add Label Return Approval
            </button>
        </div>

        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Customer</label>
                    <select id="filter_customer" class="form-select">
                        <option value="">Select Customer</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-8 sale-search-wrap">
                    <label>Search QR Code</label>
                    <input type="text" id="sale_search" class="form-control" disabled>
                    <div id="saleSuggestion" class="list-group"></div>
                </div>
            </div>

            <form method="POST" action="{{ route('company.returns.processSelected', $company->slug) }}" id="returnSubmitForm">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-12 return-grid-wrap">
                        <table class="table table-bordered" id="selectedQrTable">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Label</th>
                                    <th>Customer</th>
                                    <th>Gross Wt</th>
                                    <th>Other Wt</th>
                                    <th>Net Wt</th>
                                    <th>Purity</th>
                                    <th>Waste %</th>
                                    <th>Net Purity</th>
                                    <th>Fine Wt</th>
                                    <th>Metal Rate</th>
                                    <th>Metal Amt</th>
                                    <th>Labour Rate</th>
                                    <th>Labour Amt</th>
                                    <th>Other Amt</th>
                                    <th>Total Amt</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="selectedQrBody">
                                <tr id="selectedQrEmptyRow">
                                    <td colspan="17" class="text-center">No item selected</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end">Totals</th>
                                    <th><span id="sumNetWt">0.000</span></th>
                                    <th colspan="9"></th>
                                    <th><span id="sumTotalAmt">0.00</span></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div id="selectedInputs"></div>

                <div class="text-end">
                    <button type="submit" class="btn btn-danger" id="finalReturnBtn" disabled>
                        Return Selected Items
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="approvalReturnModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Approval Return Items (Pending)</h5>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Available</h6>
                        <div style="max-height:400px; overflow:auto;">
                            <table class="table table-bordered">
                                <tbody id="leftReturn"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6>Selected</h6>
                        <div style="max-height:400px; overflow:auto;">
                            <table class="table table-bordered">
                                <tbody id="rightReturn"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-2 d-flex justify-content-between">
                    <span>Total Items: <b id="totalItems">0</b></span>
                    <span>Total Gross: <b id="totalGross">0.000</b></span>
                </div>
            </div>

            <div class="modal-footer">
                <button id="addReturnItems" class="btn btn-success" type="button">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="otherChargeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Other Charges</h5>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="otherChargeTable">
                        <thead>
                            <tr>
                                <th>Sr</th>
                                <th>Charge</th>
                                <th>Amount</th>
                                <th>Qty</th>
                                <th>Wt Formula</th>
                                <th>Amt Formula</th>
                                <th>Total Amt</th>
                                <th>Select</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <strong>Charge Total:</strong> <span id="modalChargeTotal">0.00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="applyOtherChargesBtn">Apply</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function() {
    const selectedSaleItemIds = new Set();
    const selectedApprovalItemIds = new Set();
    const selectedRows = {};
    let modalRowId = null;
    let otherChargeOptions = [];

    const toNum = (v, d = 0) => {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : d;
    };

    const nfix = (value, decimals) => {
        const n = toNum(value);
        const fixed = Math.abs(n) < 1e-9 ? 0 : n;
        return fixed.toFixed(decimals);
    };

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function updateFinalButtonState() {
        const hasRows = $('#selectedQrBody tr').not('#selectedQrEmptyRow').length > 0;
        $('#finalReturnBtn').prop('disabled', !hasRows);
    }

    function updateHiddenInputs() {
        let html = '';

        selectedSaleItemIds.forEach(function(id) {
            html += `<input type="hidden" name="sale_item_ids[]" value="${id}">`;
        });

        selectedApprovalItemIds.forEach(function(id) {
            html += `<input type="hidden" name="approval_item_ids[]" value="${id}">`;
        });

        Object.entries(selectedRows).forEach(([rowId, row]) => {
            const payload = {
                type: row.type,
                id: row.record_id,
                other_amount: row.other_amount,
                total_amount: row.total_amount
            };
            html += `<input type="hidden" name="row_payloads[]" value='${JSON.stringify(payload).replace(/'/g, '&#39;')}'>`;
        });

        $('#selectedInputs').html(html);
        updateFinalButtonState();
    }

    function recalcTotals() {
        let net = 0;
        let amount = 0;

        Object.values(selectedRows).forEach(function(row) {
            net += toNum(row.net_weight);
            amount += toNum(row.total_amount);
        });

        $('#sumNetWt').text(nfix(net, 3));
        $('#sumTotalAmt').text(nfix(amount, 2));
    }

    function recalcRowData(rowId) {
        const row = selectedRows[rowId];
        if (!row) return;

        row.other_amount = toNum($(`.row-other-amount[data-row-id="${rowId}"]`).val());
        row.total_amount = row.base_total + row.other_amount;

        $(`.row-total-amount[data-row-id="${rowId}"]`).text(nfix(row.total_amount, 2));

        updateHiddenInputs();
        recalcTotals();
    }

    function calculateChargeTotal(option, rowContext) {
        const itemWeight = toNum(rowContext.net_weight || rowContext.gross_weight);
        const qty = toNum(option.quantity_pcs, 1);
        const amount = toNum(option.default_amount, 0);
        const defaultWeight = toNum(option.default_weight, 0);
        const weightPercent = toNum(option.weight_percent, 0);
        const wtFormula = String(option.weight_formula || 'flat').toLowerCase();
        const amtFormula = String(option.other_amt_formula || 'flat').toLowerCase();

        let weight = defaultWeight;
        if (weightPercent > 0) {
            weight = (itemWeight * weightPercent) / 100;
        } else if (wtFormula === 'per_weight') {
            weight = itemWeight;
        } else if (wtFormula === 'per_quantity') {
            weight = defaultWeight * qty;
        }

        let total = amount;
        if (amtFormula === 'per_weight') {
            total = amount * weight;
        } else if (amtFormula === 'per_quantity') {
            total = amount * qty;
        } else if (amtFormula === 'carat') {
            total = amount * itemWeight;
        }

        return { qty, amount, wt_formula: wtFormula || 'flat', amt_formula: amtFormula || 'flat', total };
    }

    function renderOtherChargeRows(lines) {
        const $tbody = $('#otherChargeTable tbody');
        $tbody.empty();
        const selectedIds = new Set((lines || []).map(x => Number(x.charge_id)));
        const rowContext = selectedRows[modalRowId] || {};

        otherChargeOptions.slice(0, 10).forEach((opt, index) => {
            const calc = calculateChargeTotal(opt, rowContext);
            const checked = selectedIds.has(Number(opt.id)) ? 'checked' : '';
            const activeClass = checked ? 'table-active' : '';
            const factor = calc.amount > 0 ? (calc.total / calc.amount) : 1;

            $tbody.append(`
                <tr class="charge-row ${activeClass}"
                    data-id="${opt.id}"
                    data-name="${escapeHtml(opt.name)}"
                    data-amount="${nfix(calc.amount, 2)}"
                    data-qty="${nfix(calc.qty, 3)}"
                    data-factor="${nfix(factor, 6)}"
                    data-wt-formula="${escapeHtml(calc.wt_formula)}"
                    data-amt-formula="${escapeHtml(calc.amt_formula)}"
                    data-total="${nfix(calc.total, 2)}">
                    <td class="charge-sr">${index + 1}</td>
                    <td>${escapeHtml(opt.name || '-')}</td>
                    <td><input type="number" step="0.01" class="form-control charge-amount-input text-end" value="${nfix(calc.amount, 2)}"></td>
                    <td><input type="number" step="0.001" class="form-control charge-qty-input text-end" value="${nfix(calc.qty, 3)}"></td>
                    <td>${escapeHtml(calc.wt_formula)}</td>
                    <td>${escapeHtml(calc.amt_formula)}</td>
                    <td class="text-end charge-total-cell">${nfix(calc.total, 2)}</td>
                    <td class="charge-select-col"><input type="checkbox" class="charge-check" ${checked}></td>
                </tr>
            `);
        });

        recalcModalCharges();
    }

    function recomputeChargeLine($tr) {
        const amount = toNum($tr.find('.charge-amount-input').val());
        const qty = toNum($tr.find('.charge-qty-input').val(), 1);
        const amtFormula = String($tr.data('amt-formula') || 'flat').toLowerCase();
        const factor = toNum($tr.data('factor'), 1);

        let total = amount;
        if (amtFormula === 'per_quantity') {
            total = amount * qty;
        } else if (amtFormula === 'per_weight' || amtFormula === 'carat') {
            total = amount * factor;
        }

        $tr.data('amount', nfix(amount, 2));
        $tr.data('qty', nfix(qty, 3));
        $tr.data('total', nfix(total, 2));
        $tr.find('.charge-total-cell').text(nfix(total, 2));
    }

    function recalcModalCharges() {
        let total = 0;
        $('#otherChargeTable tbody tr').each(function() {
            if ($(this).find('.charge-check').is(':checked')) {
                total += toNum($(this).data('total'));
            }
        });
        $('#modalChargeTotal').text(nfix(total, 2));
    }

    function collectModalChargeLines() {
        const lines = [];
        $('#otherChargeTable tbody tr').each(function() {
            const $tr = $(this);
            if (!$tr.find('.charge-check').is(':checked')) return;
            lines.push({
                charge_id: Number($tr.data('id')),
                charge_name: $tr.data('name'),
                qty: toNum($tr.data('qty')),
                amount: toNum($tr.data('amount')),
                formula: String($tr.data('wt-formula') || 'flat'),
                other_amt_formula: String($tr.data('amt-formula') || 'flat'),
                total: toNum($tr.data('total')),
            });
        });
        return lines;
    }

    function appendRow(type, idKey, data) {
        const rowId = `${type}_row_${idKey}`;
        if (selectedRows[rowId]) return;

        const customerName = data.customer || $('#filter_customer option:selected').text() || '-';
        const originalOther = toNum(data.other_amount);
        const originalTotal = toNum(data.total_amount);
        const baseTotal = originalTotal - originalOther;

        selectedRows[rowId] = {
            type,
            record_id: Number(idKey),
            item_id: Number(data.item_id || 0),
            net_weight: toNum(data.net_weight),
            base_total: baseTotal,
            other_amount: originalOther,
            total_amount: originalTotal,
            other_charges: [],
        };

        const rowHtml = `
            <tr id="${rowId}" data-type="${type}" data-id="${idKey}">
                <td>${type === 'sale' ? 'Sale' : 'Approval'}</td>
                <td>
                    <strong>${escapeHtml(data.huid || '-')}</strong><br>
                    <small>${escapeHtml(data.qr_code || '-')}</small><br>
                    <small>${escapeHtml(data.item_name || data.name || '-')}</small>
                </td>
                <td>${escapeHtml(customerName)}</td>
                <td>${nfix(data.gross_weight, 3)}</td>
                <td>${nfix(data.other_weight, 3)}</td>
                <td>${nfix(data.net_weight, 3)}</td>
                <td>${nfix(data.purity, 3)}</td>
                <td>${nfix(data.waste_percent, 3)}</td>
                <td>${nfix(data.net_purity, 3)}</td>
                <td>${nfix(data.fine_weight, 3)}</td>
                <td>${nfix(data.metal_rate, 2)}</td>
                <td>${nfix(data.metal_amount, 2)}</td>
                <td>${nfix(data.labour_rate, 2)}</td>
                <td>${nfix(data.labour_amount, 2)}</td>
                <td>
                    <div class="input-group other-amount-wrap">
                        <input type="number" step="0.01" class="form-control row-other-amount" data-row-id="${rowId}" value="${nfix(data.other_amount, 2)}">
                        <button type="button" class="btn btn-info open-other-charge-modal" data-row-id="${rowId}" title="Other Charges">...</button>
                    </div>
                </td>
                <td><span class="row-total-amount" data-row-id="${rowId}">${nfix(data.total_amount, 2)}</span></td>
                <td>
                    <button type="button" class="btn btn-sm btn-secondary removeSelectedRow" data-row-id="${rowId}">Remove</button>
                </td>
            </tr>
        `;

        $('#selectedQrEmptyRow').remove();
        $('#selectedQrBody').append(rowHtml);
        recalcRowData(rowId);
        updateHiddenInputs();
    }

    function clearSelectionGrid() {
        selectedSaleItemIds.clear();
        selectedApprovalItemIds.clear();
        Object.keys(selectedRows).forEach(k => delete selectedRows[k]);

        $('#selectedQrBody').html(`
            <tr id="selectedQrEmptyRow">
                <td colspan="17" class="text-center">No item selected</td>
            </tr>
        `);

        recalcTotals();
        updateHiddenInputs();
    }

    $('#filter_customer').change(function() {
        $('#sale_search').prop('disabled', !$(this).val()).val('');
        $('#saleSuggestion').hide().empty();
        clearSelectionGrid();
    });

    $('#sale_search').keyup(function() {
        const query = $(this).val().trim();
        const customerId = $('#filter_customer').val();

        if (!customerId || query.length < 2) {
            $('#saleSuggestion').hide().empty();
            return;
        }

        $.get("{{ route('company.sales.search',$company->slug) }}", {
            search: query,
            customer_id: customerId
        }, function(data) {
            let html = '';

            data.forEach(function(row) {
                const isAdded = selectedSaleItemIds.has(Number(row.sale_item_id));

                html += `
                    <a href="#" class="list-group-item saleSelect ${isAdded ? 'disabled' : ''}"
                        data-sale-item-id="${row.sale_item_id}"
                        data-row='${JSON.stringify(row).replace(/'/g, '&#39;')}'>
                        ${escapeHtml(row.qr_code || '')} - ${escapeHtml(row.customer || '')}
                        ${isAdded ? '(Added)' : ''}
                    </a>
                `;
            });

            if (html) {
                $('#saleSuggestion').html(html).show();
            } else {
                $('#saleSuggestion').hide().empty();
            }
        });
    });

    $(document).on('click', '.saleSelect', function(e) {
        e.preventDefault();

        if ($(this).hasClass('disabled')) return;

        const saleItemId = Number($(this).data('sale-item-id'));
        if (selectedSaleItemIds.has(saleItemId)) return;

        selectedSaleItemIds.add(saleItemId);

        const row = $(this).data('row');
        appendRow('sale', saleItemId, row);

        $('#saleSuggestion').hide().empty();
        $('#sale_search').val('');
    });

    $(document).on('click', '.removeSelectedRow', function() {
        const rowId = $(this).data('row-id');
        const $row = $('#' + rowId);
        const type = $row.data('type');
        const id = Number($row.data('id'));

        delete selectedRows[rowId];

        if (type === 'sale') selectedSaleItemIds.delete(id);
        if (type === 'approval') selectedApprovalItemIds.delete(id);

        $row.remove();

        if ($('#selectedQrBody tr').length === 0) {
            $('#selectedQrBody').html('<tr id="selectedQrEmptyRow"><td colspan="17" class="text-center">No item selected</td></tr>');
        }

        recalcTotals();
        updateHiddenInputs();
    });

    $(document).on('input', '.row-other-amount', function() {
        recalcRowData($(this).data('row-id'));
    });

    $(document).on('click', '.open-other-charge-modal', function () {
        const rowId = $(this).data('row-id');
        const row = selectedRows[rowId];
        if (!row) return;

        modalRowId = rowId;

        $.get("{{ route('company.other-charge.options', $company->slug) }}", {
            item_id: row.item_id || ''
        }, function(res) {
            otherChargeOptions = Array.isArray(res) ? res : [];
            const lines = Array.isArray(row.other_charges) && row.other_charges.length
                ? row.other_charges
                : [];

            if (!lines.length && toNum(row.other_amount) > 0) {
                lines.push({
                    charge_id: null,
                    charge_name: 'Manual',
                    formula: 'flat',
                    qty: 1,
                    amount: toNum(row.other_amount),
                });
            }

            renderOtherChargeRows(lines);
            $('#otherChargeModal').modal('show');
        });
    });

    $(document).on('click', '.charge-row', function (e) {
        if ($(e.target).is('input')) return;
        const $check = $(this).find('.charge-check');
        $check.prop('checked', !$check.prop('checked')).trigger('change');
    });

    $(document).on('change', '.charge-check', function () {
        $(this).closest('tr').toggleClass('table-active', $(this).is(':checked'));
        recalcModalCharges();
    });

    $(document).on('input', '.charge-amount-input, .charge-qty-input', function () {
        const $tr = $(this).closest('tr');
        recomputeChargeLine($tr);
        recalcModalCharges();
    });

    $('#applyOtherChargesBtn').on('click', function () {
        if (!modalRowId || !selectedRows[modalRowId]) {
            $('#otherChargeModal').modal('hide');
            return;
        }

        const lines = collectModalChargeLines();
        const total = lines.reduce((sum, line) => sum + toNum(line.total), 0);

        selectedRows[modalRowId].other_charges = lines;
        $(`.row-other-amount[data-row-id="${modalRowId}"]`).val(nfix(total, 2));
        recalcRowData(modalRowId);
        $('#otherChargeModal').modal('hide');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sale-search-wrap').length) {
            $('#saleSuggestion').hide();
        }
    });

    $('#openApprovalModal').click(function() {
        const customerId = $('#filter_customer').val();

        if (!customerId) {
            alert('Select customer first');
            return;
        }

        $('#approvalReturnModal').modal('show');

        $.get("{{ route('company.approval.returnItems',$company->slug) }}", {
            customer_id: customerId
        }, function(data) {
            let html = '';

            data.forEach((item, i) => {
                if (selectedApprovalItemIds.has(Number(item.id))) return;

                html += `
                    <tr class="leftItem"
                        data-row='${JSON.stringify(item).replace(/'/g, '&#39;')}'
                        data-id="${item.id}"
                        data-gross="${item.gross_weight}">
                        <td>${i + 1}</td>
                        <td>${escapeHtml(item.qr_code || '-')}</td>
                        <td>${escapeHtml(item.name || '-')}</td>
                        <td>${nfix(item.gross_weight, 3)}</td>
                    </tr>
                `;
            });

            $('#leftReturn').html(html || '<tr><td colspan="4" class="text-center">No pending approval items</td></tr>');
            $('#rightReturn').html('');
            updateModalTotals();
        });
    });

    $(document).on('click', '.leftItem', function() {
        $(this).appendTo('#rightReturn').removeClass('leftItem').addClass('rightItem');
        updateModalTotals();
    });

    $(document).on('click', '.rightItem', function() {
        $(this).appendTo('#leftReturn').removeClass('rightItem').addClass('leftItem');
        updateModalTotals();
    });

    function updateModalTotals() {
        let count = 0;
        let gross = 0;

        $('#rightReturn tr').each(function() {
            count++;
            gross += toNum($(this).data('gross'));
        });

        $('#totalItems').text(count);
        $('#totalGross').text(nfix(gross, 3));
    }

    $('#addReturnItems').click(function() {
        $('#rightReturn tr').each(function() {
            const approvalItemId = Number($(this).data('id'));
            if (selectedApprovalItemIds.has(approvalItemId)) return;

            selectedApprovalItemIds.add(approvalItemId);
            appendRow('approval', approvalItemId, $(this).data('row'));
        });

        $('#approvalReturnModal').modal('hide');
    });

    $('#returnSubmitForm').submit(function(e) {
        if (selectedSaleItemIds.size === 0 && selectedApprovalItemIds.size === 0) {
            e.preventDefault();
            alert('Please add at least one item for return');
        }
    });
});
</script>
@endpush
