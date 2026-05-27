@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ !empty($isEdit) ? 'Edit Approval' : 'Create Approval' }}</h4>
        </div>

        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">Select Customer</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (!empty($approval) && (int) $approval->customer_id === (int) $c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-8 search-wrapper">
                    <label>Scan / Search (HUID / QR)</label>
                    <input type="text" id="label_search" class="form-control" autocomplete="off">
                    <div id="search_results" class="list-group"></div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label>Voucher Remarks</label>
                    <textarea id="voucher_remarks" class="form-control" rows="2" placeholder="Enter remarks for this approval">{{ !empty($approval) ? ($approval->remarks ?? '') : '' }}</textarea>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 approval-summary d-flex flex-wrap gap-3">
                    <div class="summary-chip">
                        <span class="summary-label">Total Count</span>
                        <span class="summary-value" id="totalCount">0</span>
                    </div>
                    <div class="summary-chip">
                        <span class="summary-label">Total Gross Wt</span>
                        <span class="summary-value" id="totalGross">0.000</span>
                    </div>
                    <div class="summary-chip">
                        <span class="summary-label">Total Other Wt</span>
                        <span class="summary-value" id="totalOtherWt">0.000</span>
                    </div>
                    <div class="summary-chip">
                        <span class="summary-label">Total Net Wt</span>
                        <span class="summary-value" id="totalNet">0.000</span>
                    </div>
                    <div class="summary-chip">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" id="totalAmount">0.00</span>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-2">
                <div style="min-width: 280px;">
                    <input type="text" id="item_name_search" class="form-control" placeholder="Search Item Name">
                </div>
            </div>

            <div class="table-responsive approval-grid-wrap">
                <table class="table table-bordered" id="cartTable">
                    <thead>
                        <tr>
                            <th>Label</th>
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
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="mt-3 text-end">
                <button class="btn btn-success" id="saveBtn">{{ !empty($isEdit) ? 'Update Approval' : 'Save Approval' }}</button>
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

<style>
    .search-wrapper {
        position: relative;
    }

    #search_results {
        position: absolute;
        z-index: 2000;
        width: 100%;
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: #2b2f4a;
        display: none;
    }

    #search_results .list-group-item {
        background: #2b2f4a;
        color: #f5f5f7;
        border-color: rgba(255, 255, 255, 0.1);
    }

    #search_results .list-group-item:hover {
        background: #3a3f63;
    }

    .approval-grid-wrap {
        max-height: 62vh;
        overflow: auto;
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    .approval-grid-wrap:hover {
        scrollbar-color: rgba(125, 145, 255, 0.7) rgba(255, 255, 255, 0.08);
    }

    .approval-grid-wrap::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .approval-grid-wrap::-webkit-scrollbar-track {
        background: transparent;
    }

    .approval-grid-wrap::-webkit-scrollbar-thumb {
        background: transparent;
        border-radius: 10px;
    }

    .approval-grid-wrap:hover::-webkit-scrollbar-thumb {
        background: rgba(125, 145, 255, 0.7);
    }

    .approval-grid-wrap:hover::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
    }

    .approval-summary .summary-chip {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 8px 12px;
        min-width: 170px;
    }

    .approval-summary .summary-label {
        display: block;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.72);
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .approval-summary .summary-value {
        display: block;
        font-size: 24px;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: 0.2px;
        line-height: 1.1;
    }

    #cartTable thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #2b2f4a;
        box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
    }

    #cartTable {
        min-width: 2150px;
    }

    #cartTable th,
    #cartTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #cartTable td:first-child {
        min-width: 180px;
        white-space: normal;
        line-height: 1.2;
        position: sticky;
        left: 0;
        z-index: 3;
        background: #262a44;
        box-shadow: 1px 0 0 rgba(255, 255, 255, 0.08);
    }

    #cartTable thead th:first-child {
        position: sticky;
        left: 0;
        z-index: 7;
    }

    #cartTable input.form-control {
        min-width: 98px;
        text-align: right;
        padding-right: 8px;
    }

    #cartTable .btn {
        min-width: 54px;
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
@endsection

@push('scripts')
<script>
$(function () {
    const isEdit = {{ !empty($isEdit) ? 'true' : 'false' }};
    const saveUrl = isEdit
        ? "{{ !empty($approval) ? route('company.approval.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $approval->id)]) : '' }}"
        : "{{ route('company.approval.store', $company->slug) }}";
    const initialItems = @json(!empty($editableItems) ? $editableItems : []);

    const selectedItems = {};
    let modalRowId = null;
    let otherChargeOptions = [];

    const toNum = (v, d = 0) => {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : d;
    };

    const esc = (v) => $('<div>').text(v ?? '').html();

    function nfix(value, decimals) {
        const n = toNum(value);
        const fixed = Math.abs(n) < 1e-9 ? 0 : n;
        return fixed.toFixed(decimals);
    }

    function calculateRow(id) {
        const row = selectedItems[id];
        if (!row) return;

        row.gross_weight = toNum($(`.gross[data-id="${id}"]`).val());
        row.other_weight = toNum($(`.other-weight[data-id="${id}"]`).val());
        row.purity = toNum($(`.purity[data-id="${id}"]`).val());
        row.waste_percent = toNum($(`.waste-percent[data-id="${id}"]`).val());
        row.metal_rate = toNum($(`.metal-rate[data-id="${id}"]`).val());
        row.labour_rate = toNum($(`.labour-rate[data-id="${id}"]`).val());
        row.other_amount = toNum($(`.other-amount[data-id="${id}"]`).val());

        row.net_weight = row.gross_weight - row.other_weight;
        row.net_purity = row.purity - row.waste_percent;
        row.total_fine_weight = (row.net_weight * row.net_purity) / 100;
        row.metal_amount = row.net_weight * row.metal_rate;
        row.labour_amount = row.net_weight * row.labour_rate;
        row.total_amount = row.metal_amount + row.labour_amount + row.other_amount;

        $(`#net_${id}`).val(nfix(row.net_weight, 3));
        $(`#net_purity_${id}`).val(nfix(row.net_purity, 3));
        $(`#fine_${id}`).val(nfix(row.total_fine_weight, 3));
        $(`#metal_amt_${id}`).val(nfix(row.metal_amount, 2));
        $(`#labour_amt_${id}`).val(nfix(row.labour_amount, 2));
        $(`#total_amt_${id}`).val(nfix(row.total_amount, 2));

        calculateTotals();
    }

    function calculateTotals() {
        let totalCount = 0;
        let totalGross = 0;
        let totalOtherWt = 0;
        let totalNet = 0;
        let totalAmount = 0;

        Object.values(selectedItems).forEach(row => {
            totalCount += 1;
            totalGross += toNum(row.gross_weight);
            totalOtherWt += toNum(row.other_weight);
            totalNet += toNum(row.net_weight);
            totalAmount += toNum(row.total_amount);
        });

        $('#totalCount').text(totalCount);
        $('#totalGross').text(nfix(totalGross, 3));
        $('#totalOtherWt').text(nfix(totalOtherWt, 3));
        $('#totalNet').text(nfix(totalNet, 3));
        $('#totalAmount').text(nfix(totalAmount, 2));
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

        return {
            qty,
            amount,
            wt_formula: wtFormula || 'flat',
            amt_formula: amtFormula || 'flat',
            total,
        };
    }

    const WEIGHT_FORMULA_OPTIONS = [
        { value: 'flat', label: 'flat' },
        { value: 'per_weight', label: 'per_weight' },
        { value: 'per_quantity', label: 'per_quantity' }
    ];

    const AMOUNT_FORMULA_OPTIONS = [
        { value: 'flat', label: 'flat' },
        { value: 'per_weight', label: 'per_weight' },
        { value: 'per_quantity', label: 'per_quantity' },
        { value: 'carat', label: 'carat' }
    ];

    function normalizeFormula(value, allowed, fallback = 'flat') {
        const v = String(value || fallback).toLowerCase();
        return allowed.includes(v) ? v : fallback;
    }

    function buildFormulaSelect(type, currentValue) {
        const options = type === 'wt' ? WEIGHT_FORMULA_OPTIONS : AMOUNT_FORMULA_OPTIONS;
        const cls = type === 'wt' ? 'charge-wt-formula' : 'charge-amt-formula';
        const allowed = options.map(o => o.value);
        const selected = normalizeFormula(currentValue, allowed, 'flat');

        let html = `<select class="form-control ${cls}">`;
        options.forEach(opt => {
            html += `<option value="${opt.value}" ${selected === opt.value ? 'selected' : ''}>${opt.label}</option>`;
        });
        html += `</select>`;
        return html;
    }

    function renderOtherChargeRows(lines) {
        const $tbody = $('#otherChargeTable tbody');
        $tbody.empty();
        const selectedIds = new Set((lines || []).map(x => Number(x.charge_id)));
        const existingLineMap = new Map(
            (lines || []).map(x => [Number(x.charge_id), x])
        );
        const rowContext = selectedItems[modalRowId] || {};

        otherChargeOptions.slice(0, 10).forEach((opt, index) => {
            const calc = calculateChargeTotal(opt, rowContext);
            const existing = existingLineMap.get(Number(opt.id)) || null;
            const checked = selectedIds.has(Number(opt.id)) ? 'checked' : '';
            const activeClass = checked ? 'table-active' : '';
            const amount = existing ? toNum(existing.amount, calc.amount) : calc.amount;
            const qty = existing ? toNum(existing.qty, calc.qty) : calc.qty;
            const wtFormula = normalizeFormula(
                existing ? (existing.formula ?? existing.wt_formula ?? calc.wt_formula) : calc.wt_formula,
                WEIGHT_FORMULA_OPTIONS.map(o => o.value),
                'flat'
            );
            const amtFormula = normalizeFormula(
                existing ? (existing.other_amt_formula ?? existing.amt_formula ?? calc.amt_formula) : calc.amt_formula,
                AMOUNT_FORMULA_OPTIONS.map(o => o.value),
                'flat'
            );

            $tbody.append(`
                <tr class="charge-row ${activeClass}"
                    data-id="${opt.id}"
                    data-name="${esc(opt.name)}"
                    data-amount="${nfix(amount, 2)}"
                    data-qty="${nfix(qty, 3)}"
                    data-item-weight="${nfix(toNum(rowContext.net_weight || rowContext.gross_weight), 6)}"
                    data-default-weight="${nfix(toNum(opt.default_weight, 0), 6)}"
                    data-weight-percent="${nfix(toNum(opt.weight_percent, 0), 6)}"
                    data-wt-formula="${esc(wtFormula)}"
                    data-amt-formula="${esc(amtFormula)}"
                    data-total="0">
                    <td class="charge-sr">${index + 1}</td>
                    <td>${esc(opt.name || '-')}</td>
                    <td><input type="number" step="0.01" class="form-control charge-amount-input text-end" value="${nfix(amount, 2)}"></td>
                    <td><input type="number" step="0.001" class="form-control charge-qty-input text-end" value="${nfix(qty, 3)}"></td>
                    <td>${buildFormulaSelect('wt', wtFormula)}</td>
                    <td>${buildFormulaSelect('amt', amtFormula)}</td>
                    <td class="text-end charge-total-cell">0.00</td>
                    <td class="charge-select-col"><input type="checkbox" class="charge-check" ${checked}></td>
                </tr>
            `);

            recomputeChargeLine($tbody.find('tr:last'));
        });

        recalcModalCharges();
    }

    function recomputeChargeLine($tr) {
        const amount = toNum($tr.find('.charge-amount-input').val());
        const qty = toNum($tr.find('.charge-qty-input').val(), 1);
        const wtFormula = normalizeFormula(
            $tr.find('.charge-wt-formula').val(),
            WEIGHT_FORMULA_OPTIONS.map(o => o.value),
            'flat'
        );
        const amtFormula = normalizeFormula(
            $tr.find('.charge-amt-formula').val(),
            AMOUNT_FORMULA_OPTIONS.map(o => o.value),
            'flat'
        );
        const itemWeight = toNum($tr.data('item-weight'));
        const defaultWeight = toNum($tr.data('default-weight'));
        const weightPercent = toNum($tr.data('weight-percent'));

        let weight = defaultWeight;
        if (weightPercent > 0) {
            weight = (itemWeight * weightPercent) / 100;
        } else if (wtFormula === 'per_weight') {
            weight = itemWeight;
        } else if (wtFormula === 'per_quantity') {
            weight = defaultWeight * qty;
        }

        let total = amount;
        if (amtFormula === 'per_quantity') {
            total = amount * qty;
        } else if (amtFormula === 'per_weight') {
            total = amount * weight;
        } else if (amtFormula === 'carat') {
            total = amount * itemWeight;
        }

        $tr.data('amount', nfix(amount, 2));
        $tr.data('qty', nfix(qty, 3));
        $tr.data('wt-formula', wtFormula);
        $tr.data('amt-formula', amtFormula);
        $tr.data('total', nfix(total, 2));
        $tr.find('.charge-total-cell').text(nfix(total, 2));
    }

    function recalcModalCharges() {
        let total = 0;
        $('#otherChargeTable tbody tr').each(function() {
            const checked = $(this).find('.charge-check').is(':checked');
            if (checked) total += toNum($(this).data('total'));
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

    function addRow(row, prepend = false) {
        if (selectedItems[row.itemset_id]) return;

        selectedItems[row.itemset_id] = row;

        const rowHtml = `
            <tr id="row_${row.itemset_id}">
                <td>
                    <strong>${esc(row.huid)}</strong><br>
                    <small>${esc(row.qr_code)}</small><br>
                    <small>${esc(row.item_name)}</small>
                </td>
                <td><input type="number" step="0.001" class="form-control gross" data-id="${row.itemset_id}" value="${nfix(row.gross_weight,3)}"></td>
                <td><input type="number" step="0.001" class="form-control other-weight" data-id="${row.itemset_id}" value="${nfix(row.other_weight,3)}"></td>
                <td><input type="number" step="0.001" class="form-control" id="net_${row.itemset_id}" readonly value="${nfix(row.net_weight,3)}"></td>
                <td><input type="number" step="0.001" class="form-control purity" data-id="${row.itemset_id}" value="${nfix(row.purity,3)}"></td>
                <td><input type="number" step="0.001" class="form-control waste-percent" data-id="${row.itemset_id}" value="${row.waste_percent}"></td>
                <td><input type="number" step="0.001" class="form-control" id="net_purity_${row.itemset_id}" readonly value="${nfix(row.net_purity,3)}"></td>
                <td><input type="number" step="0.001" class="form-control" id="fine_${row.itemset_id}" readonly value="${nfix(row.total_fine_weight,3)}"></td>
                <td><input type="number" step="0.01" class="form-control metal-rate" data-id="${row.itemset_id}" value="${nfix(row.metal_rate,2)}"></td>
                <td><input type="number" step="0.01" class="form-control" id="metal_amt_${row.itemset_id}" readonly value="${nfix(row.metal_amount,2)}"></td>
                <td><input type="number" step="0.01" class="form-control labour-rate" data-id="${row.itemset_id}" value="${nfix(row.labour_rate,2)}"></td>
                <td><input type="number" step="0.01" class="form-control" id="labour_amt_${row.itemset_id}" readonly value="${nfix(row.labour_amount,2)}"></td>
                <td>
                    <div class="input-group other-amount-wrap">
                        <input type="number" step="0.01" class="form-control other-amount" data-id="${row.itemset_id}" value="${nfix(row.other_amount,2)}">
                        <button type="button" class="btn btn-info open-other-charge-modal" data-id="${row.itemset_id}" title="Other Charges">...</button>
                    </div>
                </td>
                <td><input type="number" step="0.01" class="form-control" id="total_amt_${row.itemset_id}" readonly value="${nfix(row.total_amount,2)}"></td>
                <td><input type="text" class="form-control remarks" data-id="${row.itemset_id}" value="${esc(row.remarks || '')}"></td>
                <td><button class="btn btn-danger removeRow" data-id="${row.itemset_id}" type="button">X</button></td>
            </tr>
        `;

        if (prepend) {
            $('#cartTable tbody').prepend(rowHtml);
        } else {
            $('#cartTable tbody').append(rowHtml);
        }

        calculateRow(row.itemset_id);
    }

    $('#label_search').on('keyup', function () {
        const keyword = $(this).val().trim();

        if (keyword.length < 2) {
            $('#search_results').hide().empty();
            return;
        }

        $.get("{{ route('company.approval.searchItemSets', $company->slug) }}", { keyword, limit: 1000 }, function (res) {
            let html = '';

            res.forEach(itemSet => {
                const isItemOnly = !!itemSet.is_item_only;
                if (!isItemOnly && selectedItems[itemSet.id]) return;
                const codeText = itemSet.qr_code || itemSet.HUID || itemSet.barcode || '';
                const rowClass = isItemOnly ? ' disabled text-muted bg-transparent' : '';
                const disabledAttr = isItemOnly ? ' data-item-only="1"' : '';
                const noteText = isItemOnly ? '<br><small>(Item found, Itemset not created)</small>' : '';
                html += `
                    <a href="#" class="list-group-item list-group-item-action selectItem${rowClass}"${disabledAttr}
                       data-id="${itemSet.id}"
                       data-item-id="${itemSet.item_id}"
                       data-item-name="${esc(itemSet.item?.item_name || '')}"
                       data-huid="${esc(itemSet.HUID || '')}"
                       data-qr="${esc(itemSet.qr_code || '')}"
                       data-gross="${toNum(itemSet.gross_weight).toFixed(3)}"
                       data-other="${toNum(itemSet.other).toFixed(3)}"
                       data-net="${toNum(itemSet.net_weight).toFixed(3)}"
                       data-purity="${toNum(itemSet.item?.outward_purity).toFixed(3)}"
                       data-metal-rate="0"
                       data-labour-rate="${toNum(itemSet.sale_labour_rate ?? itemSet.item?.labour_rate).toFixed(2)}"
                       data-other-amount="${toNum(itemSet.sale_other).toFixed(2)}">
                       ${esc(codeText)}<br>
                        <small>${esc(itemSet.item?.item_name || '')}</small>${noteText}
                    </a>
                `;
            });

            $('#search_results').html(html || '<div class="p-2">No result</div>').show();
        });
    });

    $(document).on('click', '.selectItem', function (e) {
        e.preventDefault();
        if ($(this).data('item-only') == 1) {
            return;
        }

        const id = Number($(this).data('id'));

        const row = {
            itemset_id: id,
            item_id: Number($(this).data('item-id')),
            item_name: $(this).data('item-name'),
            huid: $(this).data('huid'),
            qr_code: $(this).data('qr'),
            gross_weight: toNum($(this).data('gross')),
            other_weight: toNum($(this).data('other')),
            net_weight: toNum($(this).data('net')),
            purity: toNum($(this).data('purity')),
            waste_percent: 0,
            net_purity: 0,
            total_fine_weight: 0,
            metal_rate: toNum($(this).data('metal-rate')),
            metal_amount: 0,
            labour_rate: toNum($(this).data('labour-rate')),
            labour_amount: 0,
            other_amount: toNum($(this).data('other-amount')),
            total_amount: 0,
            remarks: '',
            other_charges: [],
        };

        addRow(row, true);

        $('#label_search').val('');
        $('#search_results').hide().empty();
    });

    $(document).on('click', '.removeRow', function () {
        const id = Number($(this).data('id'));
        delete selectedItems[id];
        $(`#row_${id}`).remove();
        calculateTotals();
    });

    $(document).on('input', '.gross, .other-weight, .purity, .waste-percent, .metal-rate, .labour-rate, .other-amount', function () {
        calculateRow(Number($(this).data('id')));
    });

    $(document).on('input', '.remarks', function () {
        const id = Number($(this).data('id'));
        if (!selectedItems[id]) return;
        selectedItems[id].remarks = $(this).val();
    });

    $(document).on('click', '.open-other-charge-modal', function () {
        const id = Number($(this).data('id'));
        const row = selectedItems[id];
        if (!row) return;

        modalRowId = id;

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
        if ($(e.target).is('input, select, option')) return;
        const $check = $(this).find('.charge-check');
        $check.prop('checked', !$check.prop('checked')).trigger('change');
    });

    $(document).on('change', '.charge-check', function () {
        $(this).closest('tr').toggleClass('table-active', $(this).is(':checked'));
        recalcModalCharges();
    });

    $(document).on('input change', '.charge-amount-input, .charge-qty-input, .charge-wt-formula, .charge-amt-formula', function () {
        const $tr = $(this).closest('tr');
        recomputeChargeLine($tr);
        recalcModalCharges();
    });

    $('#applyOtherChargesBtn').on('click', function () {
        if (!modalRowId || !selectedItems[modalRowId]) {
            $('#otherChargeModal').modal('hide');
            return;
        }

        const lines = collectModalChargeLines();
        const total = lines.reduce((sum, line) => sum + toNum(line.total), 0);

        selectedItems[modalRowId].other_charges = lines;
        selectedItems[modalRowId].other_amount = total;

        $(`.other-amount[data-id="${modalRowId}"]`).val(nfix(total, 2));
        calculateRow(modalRowId);
        $('#otherChargeModal').modal('hide');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-wrapper').length) {
            $('#search_results').hide();
        }
    });

    $('#item_name_search').on('input', function () {
        const keyword = ($(this).val() || '').toLowerCase().trim();
        $('#cartTable tbody tr').each(function () {
            const text = $(this).find('td:first').text().toLowerCase();
            $(this).toggle(text.includes(keyword));
        });
    });

    $('#saveBtn').on('click', function () {
        const customer_id = $('#customer_id').val();

        if (!customer_id) {
            alert('Select customer');
            return;
        }

        const items = Object.values(selectedItems);
        if (!items.length) {
            alert('Add at least one item');
            return;
        }

        $.post(saveUrl, {
            _token: "{{ csrf_token() }}",
            customer_id,
            voucher_remarks: $('#voucher_remarks').val(),
            items,
        }, function (res) {
            if (res.success) {
                alert(res.message || (isEdit ? 'Approval updated successfully' : 'Approval Created Successfully'));
                window.location.href = "{{ route('company.approval.index', $company->slug) }}";
            } else {
                alert(res.message || 'Failed to save approval');
            }
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Failed to save approval');
        });
    });

    if (Array.isArray(initialItems) && initialItems.length) {
        initialItems.forEach(function (row) {
            if (!row || !row.itemset_id) return;
            addRow({
                itemset_id: Number(row.itemset_id),
                item_id: Number(row.item_id || 0),
                item_name: row.item_name || '',
                huid: row.huid || '',
                qr_code: row.qr_code || '',
                gross_weight: toNum(row.gross_weight),
                other_weight: toNum(row.other_weight),
                net_weight: toNum(row.net_weight),
                purity: toNum(row.purity),
                waste_percent: toNum(row.waste_percent),
                net_purity: toNum(row.net_purity),
                total_fine_weight: toNum(row.total_fine_weight),
                metal_rate: toNum(row.metal_rate),
                metal_amount: toNum(row.metal_amount),
                labour_rate: toNum(row.labour_rate),
                labour_amount: toNum(row.labour_amount),
                other_amount: toNum(row.other_amount),
                total_amount: toNum(row.total_amount),
                remarks: row.remarks || '',
                other_charges: Array.isArray(row.other_charges) ? row.other_charges : [],
            });
        });
    }
});
</script>
@endpush
