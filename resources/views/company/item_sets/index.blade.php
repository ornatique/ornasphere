@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="card-title">Item Sets Grid</h4>
        </div>

        <div class="card-body">

            {{-- ITEM SELECT --}}
            <div class="row mb-3">

                <div class="col-md-4">

                    <div class="form-group">

                        <label>Select Item</label>

                        <select id="itemSelect" class="form-select">

                            <option value="">Select Item</option>

                            @foreach($items as $item)

                            <option value="{{ $item->id }}">
                                {{ $item->item_name }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Carat</label>
                        <input type="text"
                            id="carat"
                            class="form-control mb-3"
                            placeholder=""
                            required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Purity</label>
                        <input type="text"
                            id="purity"
                            class="form-control mb-3"
                            placeholder=""
                            required>
                    </div>
                </div>

            </div>


            {{-- GRID TABLE --}}
            <div style="height:500px; overflow-y:auto;" id="gridContainer">

                <table class="table table-bordered table-striped">

                    <thead>

                        <tr>
                            <th width="70">SR No</th>

                            <th width="120">Gross Weight</th>

                            <th width="140">Other</th>

                            <th width="120">Net Weight</th>

                            <th width="150">Sale Labour Formula</th>

                            <th width="120">Labour Rate</th>

                            <th width="120">Labour Amount</th>

                            <th width="120">Sale Other</th>

                            <th width="140">Supplier Person</th>

                            <th width="120">Size</th>

                            <th width="150">HUID</th>

                        </tr>

                    </thead>

                    <tbody id="setsBody">

                    </tbody>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th id="totalGrossCell">0.000</th>
                            <th id="totalOtherCell">0.000</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th id="totalAmountCell">0.00</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>

                </table>

            </div>

        </div>
        <button type="button" id="btnFinalizeItemSets" class="btn btn-success">
            Finalize & Generate QR
        </button>
    </div>

</div>

<div class="modal fade" id="itemSetOtherChargeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Other Charges</h5>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="itemSetOtherChargeTable">
                        <thead>
                            <tr>
                                <th>Sr</th>
                                <th>Charge</th>
                                <th>Amount</th>
                                <th>Qty</th>
                                <th>Weight</th>
                                <th>Wt Formula</th>
                                <th>Total Weight</th>
                                <th>Amt Formula</th>
                                <th>Total Amt</th>
                                <th>Select</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <strong>Weight Total:</strong> <span id="itemSetModalWeightTotal">0.000</span>
                </div>
                <div class="text-end mt-1">
                    <strong>Charge Total:</strong> <span id="itemSetModalChargeTotal">0.00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="itemSetApplyOtherChargesBtn">Apply</button>
            </div>
        </div>
    </div>
</div>

<style>
    #gridContainer table {
        min-width: 1300px;
    }

    #gridContainer thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #2b2f4a;
        color: #f5f5f7;
        white-space: nowrap;
    }

    #gridContainer td {
        white-space: nowrap;
        min-width: 90px;
    }

    #gridContainer .cell {
        outline: none;
    }

    #itemSetOtherChargeTable {
        min-width: 1100px;
    }

    #itemSetOtherChargeTable .charge-row {
        cursor: pointer;
    }

    #itemSetOtherChargeTable .charge-sr {
        width: 44px;
        text-align: center;
        font-weight: 600;
    }

    #itemSetOtherChargeTable td,
    #itemSetOtherChargeTable th {
        white-space: nowrap;
        vertical-align: middle;
    }
</style>

@endsection


@push("scripts")



<script>
    let itemId = null;

    let offset = 0;

    let loading = false;
    let hasMoreRows = true;
    let otherChargeOptions = [];
    let modalTargetRow = null;
    let selectedLabourFormula = 'Per Net Weight';

    const editableColumns = [
        'gross_weight',
        'sale_labour_rate',
        'sale_labour_amount',
        'sale_other',
        'supplier_person',
        'size',
        'HUID'
    ];


    //////////////////////////////////////////////////////
    // ITEM CHANGE LOAD FIRST 10 ROWS
    //////////////////////////////////////////////////////

    $('#itemSelect').change(function() {

        itemId = $(this).val();

        offset = 0;
        hasMoreRows = true;

        $('#setsBody').html('');

        updateSrNumbers();
        updateTotals();

        loadMore();

    });


    //////////////////////////////////////////////////////
    // LOAD MORE FUNCTION
    //////////////////////////////////////////////////////

    function loadMore() {

        if (!itemId) return;

        if (loading) return;

        loading = true;

        $.get(
            "{{ route('company.item_sets.load',$company->slug) }}", {
                offset: offset,
                item_id: itemId
            },
            function(rows) {

                //////////////////////////////////////////////////////
                // ADD EXISTING ROWS
                //////////////////////////////////////////////////////

                rows.forEach(addRow);
                applyFormulaToAllRows();

                offset += rows.length;
                hasMoreRows = rows.length === 10;
                ensureAtLeastOneEmptyRow();

                loading = false;
                updateSrNumbers();
                updateTotals();

            }
        );

    }



    //////////////////////////////////////////////////////
    // ADD EXISTING ROW
    //////////////////////////////////////////////////////

    function addRow(row) {
        if (!row || !row.id) return;
        if ($(`#setsBody tr[data-id="${row.id}"]`).length) return;

        $('#setsBody').append(`

        <tr data-id="${row.id}" data-other-weight="${row.other ?? 0}">
            <td class="sr-no"></td>

            <td contenteditable="true" class="cell" data-column="gross_weight">${row.gross_weight ?? ''}</td>

            <td>
                <div class="d-flex align-items-center gap-1">
                    <span class="other-weight-display">${nfix(row.other ?? 0, 3)}</span>
                    <button type="button" class="btn btn-sm btn-info open-other-charge-modal">Wt|Amt</button>
                </div>
            </td>

            <td contenteditable="false" class="cell" data-column="net_weight">${row.net_weight ?? ''}</td>

            <td contenteditable="false" class="cell formula-cell" data-column="sale_labour_formula">${row.sale_labour_formula ?? selectedLabourFormula}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate">${row.sale_labour_rate ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount">${row.sale_labour_amount ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_other">${row.sale_other ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="supplier_person">${row.supplier_person ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="size">${row.size ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="HUID">${row.HUID ?? ''}</td>

        </tr>

    `);

    }


    //////////////////////////////////////////////////////
    // ADD EMPTY ROW
    //////////////////////////////////////////////////////

    function addEmptyRow() {

        $('#setsBody').append(`

        <tr data-id="" data-other-weight="0">
            <td class="sr-no"></td>

            <td contenteditable="true" class="cell" data-column="gross_weight"></td>

            <td>
                <div class="d-flex align-items-center gap-1">
                    <span class="other-weight-display">0.000</span>
                    <button type="button" class="btn btn-sm btn-info open-other-charge-modal">Wt|Amt</button>
                </div>
            </td>

            <td contenteditable="false" class="cell" data-column="net_weight"></td>

            <td contenteditable="false" class="cell formula-cell" data-column="sale_labour_formula">${selectedLabourFormula}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate"></td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount"></td>

            <td contenteditable="true" class="cell" data-column="sale_other"></td>

            <td contenteditable="true" class="cell" data-column="supplier_person"></td>

            <td contenteditable="true" class="cell" data-column="size"></td>

            <td contenteditable="true" class="cell" data-column="HUID"></td>

        </tr>

    `);

    }


    //////////////////////////////////////////////////////
    // AUTO SAVE CELL
    //////////////////////////////////////////////////////

    function saveCell($cell) {
        if (!$cell || !$cell.length || !itemId) return;

        const tr = $cell.closest('tr');
        const column = $cell.data('column');
        const value = String($cell.text() ?? '').trim();
        const currentId = tr.attr('data-id');

        if (value === '') return;
        if (!column) return;

        if (!currentId && tr.data('creating')) return;
        if (!currentId) tr.data('creating', true);

        $.post(
            "{{ route('company.item_sets.saveCell',$company->slug) }}", {
                _token: "{{ csrf_token() }}",
                id: currentId,
                item_id: itemId,
                column: column,
                value: value
            }
        ).done(function(res) {
            const wasNew = !tr.attr('data-id');
            tr.attr('data-id', res.id);
            if (wasNew && res.id) {
                offset += 1;
            }
            ensureNextEmptyRow(tr);
            updateSrNumbers();
            updateTotals();
        }).always(function() {
            tr.data('creating', false);
        });
    }

    function saveDerivedCell($row, column, value) {
        const id = $row.attr('data-id');
        if (!id || !itemId) return;

        $.post(
            "{{ route('company.item_sets.saveCell',$company->slug) }}", {
                _token: "{{ csrf_token() }}",
                id: id,
                item_id: itemId,
                column: column,
                value: value
            }
        );
    }

    $(document).on('blur', '.cell', function() {
        saveCell($(this));
    });


    //////////////////////////////////////////////////////
    // ENTER KEY MOVE TO NEXT CELL/ROW
    //////////////////////////////////////////////////////

    $(document).on('keydown', '.cell', function(e) {

        if (e.key !== 'Enter' && e.key !== 'Tab') return;
        e.preventDefault();

        const td = $(this);
        const tr = td.closest('tr');
        const col = td.data('column');
        saveCell(td);

        // Fast-entry mode requested:
        // From first column (gross_weight), TAB should jump to next row first column.
        if (e.key === 'Tab' && col === 'gross_weight') {
            let nextRow = tr.next('tr');
            if (!nextRow.length || rowHasAnyValue(nextRow)) {
                addEmptyRow();
                updateSrNumbers();
                nextRow = tr.next('tr');
            }

            const nextGross = nextRow.find('.cell[data-column="gross_weight"]');
            if (nextGross.length) {
                setTimeout(function() {
                    nextGross.focus();
                    placeCaretAtEnd(nextGross[0]);
                }, 0);
            }
            return;
        }

        const currentIndex = editableColumns.indexOf(col);
        if (currentIndex === -1) return;

        const nextIndex = currentIndex + 1;

        if (nextIndex < editableColumns.length) {
            const nextCol = editableColumns[nextIndex];
            const nextCell = tr.find(`.cell[data-column="${nextCol}"]`);
            if (nextCell.length) {
                setTimeout(function() {
                    nextCell.focus();
                    placeCaretAtEnd(nextCell[0]);
                }, 0);
            }
            return;
        }

        let nextRow = tr.next('tr');

        // Always ensure next row exists immediately on last column Tab/Enter.
        // This avoids delay when autosave is async or scroll load is pending.
        if (!nextRow.length || rowHasAnyValue(nextRow)) {
            addEmptyRow();
            updateSrNumbers();
            nextRow = tr.next('tr');
        }

        const firstCell = nextRow.find(`.cell[data-column="${editableColumns[0]}"]`);
        if (firstCell.length) {
            setTimeout(function() {
                firstCell.focus();
                placeCaretAtEnd(firstCell[0]);
            }, 0);
        }
    });


    //////////////////////////////////////////////////////
    // HELPERS
    //////////////////////////////////////////////////////

    function updateSrNumbers() {
        $('#setsBody tr').each(function(index) {
            $(this).find('.sr-no').text(index + 1);
        });
    }

    function toNumber(val) {
        const n = parseFloat(String(val ?? '').replace(/,/g, '').trim());
        return Number.isFinite(n) ? n : 0;
    }

    function updateTotals() {
        let totalAmount = 0;
        let totalGross = 0;
        let totalOther = 0;

        $('#setsBody tr').each(function() {
            const amountCell = $(this).find('.cell[data-column="sale_other"]');
            const grossCell = $(this).find('.cell[data-column="gross_weight"]');
            totalAmount += toNumber(amountCell.text());
            totalGross += toNumber(grossCell.text());
            totalOther += toNumber($(this).attr('data-other-weight'));
        });

        $('#totalAmountCell').text(totalAmount.toFixed(2));
        $('#totalGrossCell').text(totalGross.toFixed(3));
        $('#totalOtherCell').text(totalOther.toFixed(3));
    }

    function placeCaretAtEnd(el) {
        if (!el) return;
        el.focus();
        if (typeof window.getSelection !== 'undefined' && typeof document.createRange !== 'undefined') {
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    function rowHasAnyValue(tr) {
        let hasValue = false;
        tr.find('.cell').each(function() {
            const col = $(this).data('column');
            const txt = $(this).text().trim();
            if (col === 'sale_labour_formula') {
                return;
            }
            if (txt !== '') {
                hasValue = true;
                return false;
            }
        });
        return hasValue;
    }

    function ensureNextEmptyRow(currentRow) {
        const nextRow = currentRow.next('tr');

        if (!nextRow.length) {
            addEmptyRow();
            return;
        }

        if (rowHasAnyValue(nextRow)) {
            addEmptyRow();
        }
        ensureAtLeastOneEmptyRow();
    }

    function ensureAtLeastOneEmptyRow() {
        const rows = $('#setsBody tr');
        if (!rows.length) {
            addEmptyRow();
            return;
        }

        const lastRow = rows.last();
        if (rowHasAnyValue(lastRow)) {
            addEmptyRow();
        }
    }

    function applyFormulaToAllRows() {
        $('#setsBody tr').each(function() {
            const $row = $(this);
            const $formulaCell = $row.find('.cell[data-column="sale_labour_formula"]');
            if (!$formulaCell.length) return;

            const current = String($formulaCell.text() || '').trim();
            if (current === '') {
                $formulaCell.text(selectedLabourFormula);
                saveDerivedCell($row, 'sale_labour_formula', selectedLabourFormula);
            }
        });
    }


    //////////////////////////////////////////////////////
    // LIVE TOTAL UPDATE
    //////////////////////////////////////////////////////

    $(document).on('input', '.cell[data-column="sale_other"]', function() {
        updateTotals();
    });


    //////////////////////////////////////////////////////
    // OTHER CHARGES MODAL
    //////////////////////////////////////////////////////

    function esc(v) {
        return $('<div>').text(v ?? '').html();
    }

    function nfix(value, decimals) {
        const n = toNumber(value);
        const fixed = Math.abs(n) < 1e-9 ? 0 : n;
        return fixed.toFixed(decimals);
    }

    function getRowWeightContext($row) {
        return {
            gross_weight: toNumber($row.find('.cell[data-column="gross_weight"]').text()),
            net_weight: toNumber($row.find('.cell[data-column="net_weight"]').text()),
        };
    }

    function calculateChargeTotal(option, rowContext) {
        const itemWeight = toNumber(rowContext.net_weight || rowContext.gross_weight);
        const qty = toNumber(option.quantity_pcs || 1);
        const amount = toNumber(option.default_amount || 0);
        const defaultWeight = toNumber(option.default_weight || 0);
        const weightPercent = toNumber(option.weight_percent || 0);
        const wtFormula = String(option.weight_formula || 'flat').toLowerCase();
        const amtFormula = String(option.other_amt_formula || 'flat').toLowerCase();

        let weight = defaultWeight;
        if (weightPercent > 0) {
            weight = (itemWeight * weightPercent) / 100;
        } else if (wtFormula === 'per_weight') {
            weight = itemWeight;
        } else if (wtFormula === 'per_quantity') {
            weight = defaultWeight;
        }

        let totalWeight = weight;
        if (wtFormula === 'per_quantity') {
            totalWeight = weight * qty;
        }

        let total = amount;
        if (amtFormula === 'per_weight') {
            total = amount * totalWeight;
        } else if (amtFormula === 'per_quantity') {
            total = amount * qty;
        } else if (amtFormula === 'carat') {
            total = amount * itemWeight;
        }

        return {
            qty,
            amount,
            wt_formula: wtFormula,
            amt_formula: amtFormula,
            stock_effect: !!option.stock_effect,
            wt_operation: String(option.wt_operation || 'less').toLowerCase(),
            weight,
            total_weight: totalWeight,
            total,
        };
    }

    const WEIGHT_FORMULA_OPTIONS = [
        { value: 'flat', label: 'Flat' },
        { value: 'per_weight', label: 'Per Weight' },
        { value: 'per_quantity', label: 'Per Quantity' }
    ];

    const AMOUNT_FORMULA_OPTIONS = [
        { value: 'flat', label: 'Flat' },
        { value: 'per_weight', label: 'Per Weight' },
        { value: 'per_quantity', label: 'Per Quantity' },
        { value: 'carat', label: 'Carat' }
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

    function parseStoredCharges($row) {
        const raw = $row.attr('data-other-charges');
        if (!raw) return [];
        try {
            const lines = JSON.parse(raw);
            return Array.isArray(lines) ? lines : [];
        } catch (e) {
            return [];
        }
    }

    function renderOtherChargeRows(lines, rowContext) {
        const $tbody = $('#itemSetOtherChargeTable tbody');
        $tbody.empty();
        const selectedIds = new Set((lines || []).map(x => Number(x.charge_id)));
        const existingLineMap = new Map(
            (lines || []).map(x => [Number(x.charge_id), x])
        );

        otherChargeOptions.slice(0, 10).forEach((opt, index) => {
            const calc = calculateChargeTotal(opt, rowContext);
            const existing = existingLineMap.get(Number(opt.id)) || null;
            const checked = selectedIds.has(Number(opt.id)) ? 'checked' : '';
            const activeClass = checked ? 'table-active' : '';
            const amount = existing ? toNumber(existing.amount, calc.amount) : calc.amount;
            const qty = existing ? toNumber(existing.qty, calc.qty) : calc.qty;
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
            const weightVal = existing ? toNumber(existing.base_weight ?? existing.weight, calc.weight) : calc.weight;
            const totalWeightVal = existing ? toNumber(existing.total_weight ?? existing.weight, calc.total_weight) : calc.total_weight;

            $tbody.append(`
                <tr class="charge-row ${activeClass}"
                    data-id="${opt.id}"
                    data-name="${esc(opt.name)}"
                    data-amount="${nfix(amount, 2)}"
                    data-qty="${nfix(qty, 3)}"
                    data-item-weight="${nfix(toNumber(rowContext.net_weight || rowContext.gross_weight), 6)}"
                    data-default-weight="${nfix(toNumber(opt.default_weight, 0), 6)}"
                    data-weight-percent="${nfix(toNumber(opt.weight_percent, 0), 6)}"
                    data-wt-formula="${esc(wtFormula)}"
                    data-stock-effect="${opt.stock_effect ? 1 : 0}"
                    data-wt-operation="${esc(opt.wt_operation || 'less')}"
                    data-amt-formula="${esc(amtFormula)}"
                    data-weight="${nfix(weightVal, 6)}"
                    data-total-weight="${nfix(totalWeightVal, 6)}"
                    data-total="0">
                    <td class="charge-sr">${index + 1}</td>
                    <td>${esc(opt.name || '-')}</td>
                    <td><input type="number" step="0.01" class="form-control charge-amount-input text-end" value="${nfix(amount, 2)}"></td>
                    <td><input type="number" step="0.001" class="form-control charge-qty-input text-end" value="${nfix(qty, 3)}"></td>
                    <td><input type="number" step="0.001" class="form-control charge-weight-input text-end" value="${nfix(weightVal, 3)}"></td>
                    <td>${buildFormulaSelect('wt', wtFormula)}</td>
                    <td class="text-end charge-total-weight-cell">0.000</td>
                    <td>${buildFormulaSelect('amt', amtFormula)}</td>
                    <td class="text-end charge-total-cell">0.00</td>
                    <td class="text-center"><input type="checkbox" class="charge-check" ${checked}></td>
                </tr>
            `);

            recomputeChargeLine($tbody.find('tr:last'));
        });

        recalcModalCharges();
    }

    function recomputeChargeLine($tr) {
        const amount = toNumber($tr.find('.charge-amount-input').val());
        const qty = toNumber($tr.find('.charge-qty-input').val(), 1);
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
        const itemWeight = toNumber($tr.data('item-weight'));
        const defaultWeight = toNumber($tr.data('default-weight'));
        const weightPercent = toNumber($tr.data('weight-percent'));
        const stockEffect = String($tr.data('stock-effect')) === '1';
        const wtOperation = String($tr.data('wt-operation') || 'less').toLowerCase();
        const enteredWeightRaw = String($tr.find('.charge-weight-input').val() ?? '').trim();
        const lastFormula = String($tr.data('last-wt-formula') || wtFormula);
        const formulaChanged = lastFormula !== wtFormula;

        let autoWeight = defaultWeight;
        if (weightPercent > 0) {
            autoWeight = (itemWeight * weightPercent) / 100;
        } else if (wtFormula === 'per_weight') {
            autoWeight = itemWeight;
        } else if (wtFormula === 'per_quantity') {
            autoWeight = defaultWeight;
        }
        const hasManualWeight = !formulaChanged && enteredWeightRaw !== '' && !Number.isNaN(Number(enteredWeightRaw));
        let weight = hasManualWeight ? toNumber(enteredWeightRaw) : autoWeight;
        weight = Math.max(0, weight);
        if (!hasManualWeight) {
            $tr.find('.charge-weight-input').val(nfix(weight, 3));
        }

        let totalWeight = weight;
        if (wtFormula === 'per_quantity') {
            totalWeight = Math.max(0, weight * qty);
        }

        let total = amount;
        if (amtFormula === 'per_quantity') {
            total = amount * qty;
        } else if (amtFormula === 'per_weight') {
            total = amount * totalWeight;
        } else if (amtFormula === 'carat') {
            total = amount * itemWeight;
        }

        $tr.data('amount', nfix(amount, 2));
        $tr.data('qty', nfix(qty, 3));
        $tr.data('wt-formula', wtFormula);
        $tr.data('last-wt-formula', wtFormula);
        $tr.data('stock-effect', stockEffect ? 1 : 0);
        $tr.data('wt-operation', wtOperation);
        $tr.data('weight', nfix(weight, 6));
        $tr.data('total-weight', nfix(totalWeight, 6));
        $tr.data('amt-formula', amtFormula);
        $tr.data('total', nfix(total, 2));
        $tr.find('.charge-total-weight-cell').text(nfix(totalWeight, 3));
        $tr.find('.charge-total-cell').text(nfix(total, 2));
    }

    function recalcModalCharges() {
        let total = 0;
        let totalWeight = 0;
        $('#itemSetOtherChargeTable tbody tr').each(function() {
            if ($(this).find('.charge-check').is(':checked')) {
                total += toNumber($(this).data('total'));
                totalWeight += toNumber($(this).data('total-weight'));
            }
        });
        $('#itemSetModalWeightTotal').text(nfix(totalWeight, 3));
        $('#itemSetModalChargeTotal').text(nfix(total, 2));
    }

    function collectModalChargeLines() {
        const lines = [];
        $('#itemSetOtherChargeTable tbody tr').each(function() {
            const $tr = $(this);
            if (!$tr.find('.charge-check').is(':checked')) return;

            lines.push({
                charge_id: Number($tr.data('id')),
                charge_name: $tr.data('name'),
                qty: toNumber($tr.data('qty')),
                amount: toNumber($tr.data('amount')),
                formula: String($tr.data('wt-formula') || 'flat'),
                stock_effect: String($tr.data('stock-effect')) === '1',
                wt_operation: String($tr.data('wt-operation') || 'less'),
                base_weight: toNumber($tr.data('weight')),
                weight: toNumber($tr.data('total-weight')),
                total_weight: toNumber($tr.data('total-weight')),
                other_amt_formula: String($tr.data('amt-formula') || 'flat'),
                total: toNumber($tr.data('total')),
            });
        });
        return lines;
    }

    function recalcRowWeightsFromCharges($row) {
        const gross = toNumber($row.find('.cell[data-column="gross_weight"]').text());
        const lines = parseStoredCharges($row);
        let stockEffectWeight = 0;

        lines.forEach(line => {
            if (!line) return;
            const weight = toNumber(line.weight);
            if (weight <= 0) return;
            const op = String(line.wt_operation || 'less').toLowerCase();
            stockEffectWeight += (op === 'add') ? -weight : weight;
        });

        const computedOther = Math.max(0, stockEffectWeight);
        const net = Math.max(0, gross - computedOther);

        $row.attr('data-other-weight', computedOther);
        $row.find('.other-weight-display').text(nfix(computedOther, 3));
        $row.find('.cell[data-column="net_weight"]').text(nfix(net, 3));

        saveDerivedCell($row, 'other', nfix(computedOther, 3));
        saveDerivedCell($row, 'net_weight', nfix(net, 3));
    }

    $(document).on('click', '.open-other-charge-modal', function() {
        if (!itemId) {
            alert('Select item first');
            return;
        }

        modalTargetRow = $(this).closest('tr');
        const rowContext = getRowWeightContext(modalTargetRow);
        const lines = parseStoredCharges(modalTargetRow);

        $.get("{{ route('company.other-charge.options', $company->slug) }}", {
            item_id: itemId
        }, function(res) {
            otherChargeOptions = Array.isArray(res) ? res : [];
            renderOtherChargeRows(lines, rowContext);
            $('#itemSetOtherChargeModal').modal('show');
        });
    });

    $(document).on('click', '#itemSetOtherChargeTable .charge-row', function(e) {
        if ($(e.target).is('input, select, option')) return;
        const $check = $(this).find('.charge-check');
        $check.prop('checked', !$check.prop('checked')).trigger('change');
    });

    $(document).on('change', '#itemSetOtherChargeTable .charge-check', function() {
        $(this).closest('tr').toggleClass('table-active', $(this).is(':checked'));
        recalcModalCharges();
    });

    $(document).on('input change', '#itemSetOtherChargeTable .charge-amount-input, #itemSetOtherChargeTable .charge-qty-input, #itemSetOtherChargeTable .charge-weight-input, #itemSetOtherChargeTable .charge-wt-formula, #itemSetOtherChargeTable .charge-amt-formula', function() {
        const $tr = $(this).closest('tr');
        recomputeChargeLine($tr);
        recalcModalCharges();
    });

    $('#itemSetApplyOtherChargesBtn').on('click', function() {
        if (!modalTargetRow || !modalTargetRow.length) {
            $('#itemSetOtherChargeModal').modal('hide');
            return;
        }

        const lines = collectModalChargeLines();
        const total = lines.reduce((sum, line) => sum + toNumber(line.total), 0);
        const $cell = modalTargetRow.find('.cell[data-column="sale_other"]');

        modalTargetRow.attr('data-other-charges', JSON.stringify(lines));
        $cell.text(nfix(total, 2));
        recalcRowWeightsFromCharges(modalTargetRow);
        saveCell($cell);
        updateTotals();

        $('#itemSetOtherChargeModal').modal('hide');
    });


    //////////////////////////////////////////////////////
    // SCROLL LOAD MORE
    //////////////////////////////////////////////////////

    $('#gridContainer').scroll(function() {
        if (!hasMoreRows) return;
        if (loading) return;

        let div = $(this)[0];

        if (div.scrollTop + div.clientHeight >= div.scrollHeight - 10) {
            loadMore();
        }

    });

    $(document).on('input blur', '.cell[data-column="gross_weight"]', function() {
        const $row = $(this).closest('tr');
        recalcRowWeightsFromCharges($row);
    });
</script>






<script>
    var urlTemplate = "{{ route('company.get-item-details', [$company->slug, ':id']) }}";

    $('#itemSelect').on('change', function() {

        var itemId = $(this).val();

        if (itemId) {
            var url = urlTemplate.replace(':id', itemId);

            $.get(url, function(res) {

                if (res.status) {
                    $('#carat').val(res.carat);
                    $('#purity').val(res.purity);
                    selectedLabourFormula = res.sale_labour_formula || 'Per Net Weight';
                    applyFormulaToAllRows();
                }

            });
        }

    });

    function finalizeItemSets() {

        if (!itemId) {
            alert("Select item first");
            return;
        }

        $.post("{{ route('company.item_sets.finalize',$company->slug) }}", {
            _token: "{{ csrf_token() }}",
            item_id: itemId
        })
        .done(function(res) {
            alert(res.message || 'Finalize completed');

            if (res.status === false) {
                return;
            }
            window.location.href = "{{ route('company.list_itemset', $company->slug) }}";
        })
        .fail(function(xhr) {
            const msg =
                xhr?.responseJSON?.message ||
                'Finalize failed. Please check Label Config and try again.';
            alert(msg);
        });

    }

    $('#btnFinalizeItemSets').on('click', function(e) {
        e.preventDefault();
        finalizeItemSets();
    });
</script>


@endpush
