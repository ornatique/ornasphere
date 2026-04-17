@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <form method="POST" action="{{ !empty($isEdit) && !empty($sale) ? route('company.sales.update', ['slug' => $company->slug, 'sale' => $sale->id]) : route('company.sales.store', ['slug' => $company->slug]) }}">
        @csrf

        <div class="card">

            <div class="card-header d-flex justify-content-between">
                <h4 class="card-title">{{ !empty($isEdit) ? 'Edit Sale' : 'Create Sale' }}</h4>

                <button type="button" class="btn btn-warning" id="openApprovalModal">
                    Add Label from Approval
                </button>
            </div>

            <div class="card-body">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Customer</label>
                        <select name="customer_id" class="form-select" id="customerSelect" required>
                            <option value="">Select Customer</option>
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (!empty($sale) && (int) $sale->customer_id === (int) $customer->id) ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 position-relative">
                        <label>Scan / Search</label>
                        <input type="text" id="item_search" class="form-control" disabled autocomplete="off">

                        <div id="suggestionBox" class="list-group w-100" style="position:absolute; top:100%; z-index:9999; display:none;"></div>
                    </div>
                </div>

                <div class="table-responsive sale-grid-wrap">
                    <table class="table table-bordered" id="saleTable">
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
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="saleBody"></tbody>

                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Totals</th>
                                <th><span id="totalNetWt">0.000</span></th>
                                <th colspan="9"></th>
                                <th>Rs <span id="grandTotal">0.00</span></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>

            <div class="card-footer text-end">
                <button class="btn btn-primary">{{ !empty($isEdit) ? 'Update Sale' : 'Save Sale' }}</button>
            </div>

        </div>
    </form>
</div>

{{-- ================= MODAL ================= --}}
<div class="modal fade" id="approvalModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Approval Items</h5>
            </div>

            <div class="modal-body">

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Item</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody id="leftTable"></tbody>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Item</th>
                                    <th>Gross</th>
                                </tr>
                            </thead>
                            <tbody id="rightTable"></tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-2 d-flex justify-content-between">
                    <div>Total Items: <b id="totalItems">0</b></div>
                    <div>Total Gross: <b id="totalGross">0</b></div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" id="addToSale" type="button">OK</button>
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
    #suggestionBox .active {
        background: #007bff;
        color: #fff;
    }

    .sale-grid-wrap {
        overflow-x: auto;
        overflow-y: visible;
    }

    #saleTable {
        min-width: 2100px;
    }

    #saleTable th,
    #saleTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #saleTable td:first-child {
        min-width: 190px;
        white-space: normal;
        line-height: 1.2;
    }

    #saleTable input.form-control {
        min-width: 95px;
        text-align: right;
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
    const initialSaleRows = @json(!empty($editableItems) ? $editableItems : []);
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

    const esc = (v) => $('<div>').text(v ?? '').html();

    function normalizeSaleRowFromItem(item) {
        const gross = toNum(item.gross_weight ?? item.gross ?? 0);
        const otherWeight = toNum(item.other_weight ?? item.other ?? 0);
        const net = toNum(item.net_weight ?? (gross - otherWeight));
        const purity = toNum(item.purity ?? 0);
        const wastePercent = toNum(item.waste_percent ?? 0);
        const netPurity = toNum(item.net_purity ?? (purity - wastePercent));
        const fineWeight = toNum(item.fine_weight ?? (net * netPurity / 100));
        const metalRate = toNum(item.metal_rate ?? 0);
        const metalAmount = toNum(item.metal_amount ?? (net * metalRate));
        const labourRate = toNum(item.labour_rate ?? 0);
        const labourAmount = toNum(item.labour_amount ?? (net * labourRate));
        const otherAmount = toNum(item.other_amount ?? item.sale_other ?? 0);
        const totalAmount = toNum(item.total_amount ?? (metalAmount + labourAmount + otherAmount));

        return {
            itemset_id: toNum(item.itemset_id ?? item.id),
            item_id: toNum(item.item_id ?? ''),
            approval_id: item.approval_id ?? '',
            name: item.name ?? item.item_name ?? '',
            code: item.code ?? item.qr_code ?? '',
            huid: item.huid ?? item.HUID ?? '',
            gross_weight: gross,
            other_weight: otherWeight,
            net_weight: net,
            purity,
            waste_percent: wastePercent,
            net_purity: netPurity,
            fine_weight: fineWeight,
            metal_rate: metalRate,
            metal_amount: metalAmount,
            labour_rate: labourRate,
            labour_amount: labourAmount,
            other_amount: otherAmount,
            total_amount: totalAmount,
            other_charges: [],
        };
    }

    function addScanToGrid(query) {
        $.get("{{ route('company.sales.getItemset', $company->slug) }}", {
            qr_code: query
        }, function(resp) {
            if (!resp || resp.success !== true || !resp.data) {
                alert((resp && resp.message) ? resp.message : 'Item not found');
                return;
            }

            const row = normalizeSaleRowFromItem(resp.data);
            if (!row.itemset_id) {
                alert('Invalid scanned item');
                return;
            }

            appendSaleRow(row);
            $('#item_search').val('');
            $('#suggestionBox').hide().empty();
        }).fail(function() {
            alert('Scanned QR not found');
        });
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
        const rowContext = selectedRows[modalRowId] || {};

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

    function recalcRow(itemsetId) {
        const row = selectedRows[itemsetId];
        if (!row) return;

        row.gross_weight = toNum($(`.gross[data-id="${itemsetId}"]`).val());
        row.other_weight = toNum($(`.other-weight[data-id="${itemsetId}"]`).val());
        row.purity = toNum($(`.purity[data-id="${itemsetId}"]`).val());
        row.waste_percent = toNum($(`.waste-percent[data-id="${itemsetId}"]`).val());
        row.metal_rate = toNum($(`.metal-rate[data-id="${itemsetId}"]`).val());
        row.labour_rate = toNum($(`.labour-rate[data-id="${itemsetId}"]`).val());
        row.other_amount = toNum($(`.other-amount[data-id="${itemsetId}"]`).val());

        row.net_weight = row.gross_weight - row.other_weight;
        row.net_purity = row.purity - row.waste_percent;
        row.fine_weight = (row.net_weight * row.net_purity) / 100;
        row.metal_amount = row.net_weight * row.metal_rate;
        row.labour_amount = row.net_weight * row.labour_rate;
        row.total_amount = row.metal_amount + row.labour_amount + row.other_amount;

        $(`#net_${itemsetId}`).val(nfix(row.net_weight, 3));
        $(`#net_purity_${itemsetId}`).val(nfix(row.net_purity, 3));
        $(`#fine_${itemsetId}`).val(nfix(row.fine_weight, 3));
        $(`#metal_amt_${itemsetId}`).val(nfix(row.metal_amount, 2));
        $(`#labour_amt_${itemsetId}`).val(nfix(row.labour_amount, 2));
        $(`#total_amt_${itemsetId}`).val(nfix(row.total_amount, 2));

        $(`input[name="net_weight[]"][data-id="${itemsetId}"]`).val(nfix(row.net_weight, 3));
        $(`input[name="net_purity[]"][data-id="${itemsetId}"]`).val(nfix(row.net_purity, 3));
        $(`input[name="fine_weight[]"][data-id="${itemsetId}"]`).val(nfix(row.fine_weight, 3));
        $(`input[name="metal_amount[]"][data-id="${itemsetId}"]`).val(nfix(row.metal_amount, 2));
        $(`input[name="labour_amount[]"][data-id="${itemsetId}"]`).val(nfix(row.labour_amount, 2));
        $(`input[name="total_amount[]"][data-id="${itemsetId}"]`).val(nfix(row.total_amount, 2));

        recalcTotals();
    }

    function recalcTotals() {
        let totalAmount = 0;
        let totalNet = 0;

        Object.values(selectedRows).forEach(row => {
            totalAmount += toNum(row.total_amount);
            totalNet += toNum(row.net_weight);
        });

        $('#grandTotal').text(nfix(totalAmount, 2));
        $('#totalNetWt').text(nfix(totalNet, 3));
    }

    function appendSaleRow(row) {
        const itemsetId = row.itemset_id;
        if (!itemsetId || selectedRows[itemsetId]) {
            return;
        }

        selectedRows[itemsetId] = row;
        selectedRows[itemsetId].other_charges = row.other_charges || [];

        const tr = `
        <tr id="row_${itemsetId}">
            <td>
                <strong>${esc(row.huid || '')}</strong><br>
                <small>${esc(row.code || '')}</small><br>
                <small>${esc(row.name || '')}</small>
                <input type="hidden" name="items[]" value="${itemsetId}">
                <input type="hidden" name="approval_item_ids[]" value="${esc(row.approval_id || '')}">
                <input type="hidden" name="item_ids[]" value="${esc(row.item_id || '')}">

                <input type="hidden" name="net_weight[]" data-id="${itemsetId}" value="${nfix(row.net_weight,3)}">
                <input type="hidden" name="net_purity[]" data-id="${itemsetId}" value="${nfix(row.net_purity,3)}">
                <input type="hidden" name="fine_weight[]" data-id="${itemsetId}" value="${nfix(row.fine_weight,3)}">
                <input type="hidden" name="metal_amount[]" data-id="${itemsetId}" value="${nfix(row.metal_amount,2)}">
                <input type="hidden" name="labour_amount[]" data-id="${itemsetId}" value="${nfix(row.labour_amount,2)}">
                <input type="hidden" name="total_amount[]" data-id="${itemsetId}" value="${nfix(row.total_amount,2)}">
                <input type="hidden" name="other_charge_details[]" class="other-charge-details" data-id="${itemsetId}" value="">
            </td>

            <td><input type="number" step="0.001" class="form-control gross" name="gross_weight[]" data-id="${itemsetId}" value="${nfix(row.gross_weight,3)}"></td>
            <td><input type="number" step="0.001" class="form-control other-weight" name="other_weight[]" data-id="${itemsetId}" value="${nfix(row.other_weight,3)}"></td>
            <td><input type="number" step="0.001" class="form-control" id="net_${itemsetId}" readonly value="${nfix(row.net_weight,3)}"></td>
            <td><input type="number" step="0.001" class="form-control purity" name="purity[]" data-id="${itemsetId}" value="${nfix(row.purity,3)}"></td>
            <td><input type="number" step="0.001" class="form-control waste-percent" name="waste_percent[]" data-id="${itemsetId}" value="${nfix(row.waste_percent,3)}"></td>
            <td><input type="number" step="0.001" class="form-control" id="net_purity_${itemsetId}" readonly value="${nfix(row.net_purity,3)}"></td>
            <td><input type="number" step="0.001" class="form-control" id="fine_${itemsetId}" readonly value="${nfix(row.fine_weight,3)}"></td>
            <td><input type="number" step="0.01" class="form-control metal-rate" name="metal_rate[]" data-id="${itemsetId}" value="${nfix(row.metal_rate,2)}"></td>
            <td><input type="number" step="0.01" class="form-control" id="metal_amt_${itemsetId}" readonly value="${nfix(row.metal_amount,2)}"></td>
            <td><input type="number" step="0.01" class="form-control labour-rate" name="labour_rate[]" data-id="${itemsetId}" value="${nfix(row.labour_rate,2)}"></td>
            <td><input type="number" step="0.01" class="form-control" id="labour_amt_${itemsetId}" readonly value="${nfix(row.labour_amount,2)}"></td>
            <td>
                <div class="input-group other-amount-wrap">
                    <input type="number" step="0.01" class="form-control other-amount" name="other_amount[]" data-id="${itemsetId}" value="${nfix(row.other_amount,2)}">
                    <button type="button" class="btn btn-info open-other-charge-modal" data-id="${itemsetId}" title="Other Charges">...</button>
                </div>
            </td>
            <td><input type="number" step="0.01" class="form-control" id="total_amt_${itemsetId}" readonly value="${nfix(row.total_amount,2)}"></td>
            <td><button type="button" class="btn btn-danger removeRow" data-id="${itemsetId}">X</button></td>
        </tr>`;

        $('#saleBody').append(tr);
        recalcRow(itemsetId);
    }

    $('#customerSelect').change(function() {
        $('#item_search').prop('disabled', !$(this).val());
    });

    $('#item_search').on('keyup', function() {
        const query = $(this).val().trim();
        if (query.length < 2) {
            $('#suggestionBox').hide().empty();
            return;
        }

        $.get("{{ route('company.items.search', $company->slug) }}", { search: query }, function(data) {
            let html = '';
            data.forEach(item => {
                if (!item.id || selectedRows[item.id]) return;
                html += `
                <a href="#" class="list-group-item itemSelect"
                    data-itemset-id="${item.id}"
                    data-item-id="${item.item_id || ''}"
                    data-approval-id=""
                    data-name="${esc(item.name || '')}"
                    data-code="${esc(item.code || '')}"
                    data-huid="${esc(item.huid || '')}"
                    data-gross-weight="${nfix(item.gross_weight,3)}"
                    data-other-weight="${nfix(item.other_weight,3)}"
                    data-net-weight="${nfix(item.net_weight,3)}"
                    data-purity="${nfix(item.purity,3)}"
                    data-waste-percent="${nfix(item.waste_percent,3)}"
                    data-net-purity="${nfix(item.net_purity,3)}"
                    data-fine-weight="${nfix(item.fine_weight,3)}"
                    data-metal-rate="${nfix(item.metal_rate,2)}"
                    data-metal-amount="${nfix(item.metal_amount,2)}"
                    data-labour-rate="${nfix(item.labour_rate,2)}"
                    data-labour-amount="${nfix(item.labour_amount,2)}"
                    data-other-amount="${nfix(item.other_amount,2)}"
                    data-total-amount="${nfix(item.total_amount,2)}">
                    ${esc(item.code)} - ${esc(item.name)}
                </a>`;
            });

            $('#suggestionBox').html(html).show();
            $('#suggestionBox .itemSelect').first().addClass('active');
        });
    });

    $('#item_search').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = $(this).val().trim();
            const $active = $('#suggestionBox .itemSelect.active');

            if ($active.length) {
                $active.click();
                return;
            }

            // Scanner flow: when no search suggestion is active, try direct QR lookup and auto-add.
            if (query.length) {
                addScanToGrid(query);
            }
        }
    });

    $(document).on('click', '.itemSelect', function(e) {
        e.preventDefault();

        appendSaleRow({
            itemset_id: toNum($(this).data('itemset-id')),
            item_id: toNum($(this).data('item-id')),
            approval_id: $(this).data('approval-id'),
            name: $(this).data('name'),
            code: $(this).data('code'),
            huid: $(this).data('huid'),
            gross_weight: toNum($(this).data('gross-weight')),
            other_weight: toNum($(this).data('other-weight')),
            net_weight: toNum($(this).data('net-weight')),
            purity: toNum($(this).data('purity')),
            waste_percent: toNum($(this).data('waste-percent')),
            net_purity: toNum($(this).data('net-purity')),
            fine_weight: toNum($(this).data('fine-weight')),
            metal_rate: toNum($(this).data('metal-rate')),
            metal_amount: toNum($(this).data('metal-amount')),
            labour_rate: toNum($(this).data('labour-rate')),
            labour_amount: toNum($(this).data('labour-amount')),
            other_amount: toNum($(this).data('other-amount')),
            total_amount: toNum($(this).data('total-amount')),
            other_charges: [],
        });

        $('#item_search').val('');
        $('#suggestionBox').hide().empty();
    });

    $('#openApprovalModal').click(function() {
        const customerId = $('#customerSelect').val();

        if (!customerId) {
            alert('Select customer first');
            return;
        }

        $('#approvalModal').modal('show');

        $.get("{{ route('company.sales.approval.items', $company->slug) }}", { customer_id: customerId }, function(resp) {
            let data = [];
            if (Array.isArray(resp)) {
                data = resp;
            } else if (resp && Array.isArray(resp.data)) {
                data = resp.data;
            } else if (resp && typeof resp === 'object') {
                data = Object.values(resp);
            }
            let html = '';
            if (!data.length) {
                html = `<tr><td colspan="4" class="text-center">No Data</td></tr>`;
            } else {
                data.forEach((item, i) => {
                    const itemsetId = toNum(item.itemset_id ?? item.itemsetId ?? 0);
                    const approvalItemId = item.approval_item_id ?? item.approval_id ?? item.id ?? '';
                    const code = item.code ?? item.qr_code ?? '';
                    const grossWeight = toNum(item.gross_weight ?? item.gross_wt ?? item.gross ?? 0);
                    const otherWeight = toNum(item.other_weight ?? item.other_wt ?? 0);
                    const netWeight = toNum(item.net_weight ?? item.net_wt ?? (grossWeight - otherWeight));
                    const wastePercent = toNum(item.waste_percent ?? item.waste_pct ?? 0);
                    const fineWeight = toNum(item.fine_weight ?? item.fine_wt ?? 0);
                    const metalAmount = toNum(item.metal_amount ?? item.metal_amt ?? 0);
                    const labourAmount = toNum(item.labour_amount ?? item.labour_amt ?? 0);
                    const otherAmount = toNum(item.other_amount ?? item.other_amt ?? 0);
                    const totalAmount = toNum(item.total_amount ?? item.total_amt ?? 0);

                    if (!itemsetId || selectedRows[itemsetId]) return;
                    html += `
                    <tr class="leftRow"
                        data-itemset-id="${itemsetId}"
                        data-item-id="${item.item_id || ''}"
                        data-approval-id="${approvalItemId}"
                        data-name="${esc(item.name || '')}"
                        data-code="${esc(code)}"
                        data-huid="${esc(item.huid || '')}"
                        data-gross-weight="${nfix(grossWeight,3)}"
                        data-other-weight="${nfix(otherWeight,3)}"
                        data-net-weight="${nfix(netWeight,3)}"
                        data-purity="${nfix(item.purity,3)}"
                        data-waste-percent="${nfix(wastePercent,3)}"
                        data-net-purity="${nfix(item.net_purity,3)}"
                        data-fine-weight="${nfix(fineWeight,3)}"
                        data-metal-rate="${nfix(item.metal_rate,2)}"
                        data-metal-amount="${nfix(metalAmount,2)}"
                        data-labour-rate="${nfix(item.labour_rate,2)}"
                        data-labour-amount="${nfix(labourAmount,2)}"
                        data-other-amount="${nfix(otherAmount,2)}"
                        data-total-amount="${nfix(totalAmount,2)}">
                        <td>${i + 1}</td>
                        <td>${esc(code)}</td>
                        <td>${esc(item.name || '')}</td>
                        <td>${nfix(grossWeight,3)}</td>
                    </tr>`;
                });
            }
            $('#leftTable').html(html);
            $('#rightTable').html('');
            updateModalTotals();
        });
    });

    $(document).on('click', '.leftRow', function() {
        $(this).removeClass('leftRow').addClass('rightRow').appendTo('#rightTable');
        updateModalTotals();
    });

    $(document).on('click', '.rightRow', function() {
        $(this).removeClass('rightRow').addClass('leftRow').appendTo('#leftTable');
        updateModalTotals();
    });

    function updateModalTotals() {
        let count = 0, gross = 0;
        $('#rightTable tr').each(function() {
            count++;
            gross += toNum($(this).data('gross-weight'));
        });
        $('#totalItems').text(count);
        $('#totalGross').text(nfix(gross, 3));
    }

    $('#addToSale').click(function() {
        $('#rightTable tr').each(function() {
            appendSaleRow({
                itemset_id: toNum($(this).data('itemset-id')),
                item_id: toNum($(this).data('item-id')),
                approval_id: $(this).data('approval-id'),
                name: $(this).data('name'),
                code: $(this).data('code'),
                huid: $(this).data('huid'),
                gross_weight: toNum($(this).data('gross-weight')),
                other_weight: toNum($(this).data('other-weight')),
                net_weight: toNum($(this).data('net-weight')),
                purity: toNum($(this).data('purity')),
                waste_percent: toNum($(this).data('waste-percent')),
                net_purity: toNum($(this).data('net-purity')),
                fine_weight: toNum($(this).data('fine-weight')),
                metal_rate: toNum($(this).data('metal-rate')),
                metal_amount: toNum($(this).data('metal-amount')),
                labour_rate: toNum($(this).data('labour-rate')),
                labour_amount: toNum($(this).data('labour-amount')),
                other_amount: toNum($(this).data('other-amount')),
                total_amount: toNum($(this).data('total-amount')),
                other_charges: [],
            });
        });

        $('#approvalModal').modal('hide');
    });

    $(document).on('input', '.gross, .other-weight, .purity, .waste-percent, .metal-rate, .labour-rate, .other-amount', function() {
        recalcRow($(this).data('id'));
    });

    $(document).on('click', '.open-other-charge-modal', function () {
        const id = Number($(this).data('id'));
        const row = selectedRows[id];
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
        if (!modalRowId || !selectedRows[modalRowId]) {
            $('#otherChargeModal').modal('hide');
            return;
        }

        const lines = collectModalChargeLines();
        const total = lines.reduce((sum, line) => sum + toNum(line.total), 0);

        selectedRows[modalRowId].other_charges = lines;
        selectedRows[modalRowId].other_amount = total;

        $(`.other-amount[data-id="${modalRowId}"]`).val(nfix(total, 2));
        $(`.other-charge-details[data-id="${modalRowId}"]`).val(JSON.stringify(lines));
        recalcRow(modalRowId);
        $('#otherChargeModal').modal('hide');
    });

    $(document).on('click', '.removeRow', function() {
        const id = $(this).data('id');
        delete selectedRows[id];
        $('#row_' + id).remove();
        recalcTotals();
    });

    $('form').submit(function() {
        let valid = true;
        $('input[name="items[]"]').each(function() {
            if (!$(this).val() || $(this).val() === 'undefined') {
                valid = false;
            }
        });

        if (!valid) {
            alert('Invalid item detected');
            return false;
        }

        if (!$('input[name="items[]"]').length) {
            alert('Add at least one item');
            return false;
        }
    });

    if (Array.isArray(initialSaleRows) && initialSaleRows.length) {
        initialSaleRows.forEach(function(row) {
            appendSaleRow(normalizeSaleRowFromItem(row));
        });
    }

    $('#customerSelect').trigger('change');
});
</script>
@endpush
