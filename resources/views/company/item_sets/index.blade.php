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

                        <select id="itemSelect" class="form-select itemset-search-select">

                            <option value="">Select Item</option>

                            @foreach($items as $item)

                            <option value="{{ $item->id }}">
                                {{ $item->item_name }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                </div>
                  <?php /* ?>  
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
                  <?php */ ?>  
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

                            <th width="140">Upload Image</th>

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
                            <th colspan="5"></th>
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

<div class="modal fade" id="itemSetImageModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">Item Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="grid_image_show_url">
                <input type="hidden" id="grid_image_upload_url">
                <input type="hidden" id="grid_image_remove_url">

                <div class="row">
                    <div class="col-md-7 mb-3">
                        <div class="item-grid-image-preview-wrap">
                            <img id="grid_item_image_preview" class="item-grid-image-preview" alt="Item image preview">
                            <div id="grid_item_image_empty" class="item-grid-image-empty">No image uploaded</div>
                        </div>
                    </div>

                    <div class="col-md-5 mb-3">
                        <div class="mb-3">
                            <label>Item</label>
                            <input type="text" id="grid_image_item_name" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Label Code</label>
                            <input type="text" id="grid_image_label_code" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Upload Image</label>
                            <input type="file" id="grid_item_image_file" class="form-control text-white border-0" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted d-block mt-1">JPG, PNG or WebP up to 20 MB. Uploaded using the item image disk.</small>
                        </div>

                        <div class="mb-3">
                            <label>Uploaded</label>
                            <input type="text" id="grid_image_uploaded_at" class="form-control text-white border-0" readonly>
                        </div>
                    </div>
                </div>

                <div id="grid_image_upload_error" class="alert alert-danger d-none mb-0"></div>
                <div id="grid_image_upload_success" class="alert alert-success d-none mb-0"></div>
            </div>

            <div class="modal-footer border-top">
                <button class="btn btn-danger me-auto" id="gridRemoveImageBtn" type="button">Remove Image</button>
                <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="gridUploadImageBtn" type="button">Upload Image</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="itemSetOtherChargeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header other-charge-modal-header">
                <h5 class="modal-title">Other Charges</h5>
                <input type="text" class="form-control other-charge-search" id="itemSetOtherChargeSearch" placeholder="Search charge">
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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

    .grid-image-cell {
        min-width: 130px;
    }

    .grid-image-btn {
        min-width: 110px;
    }

    .item-grid-image-preview-wrap {
        height: min(56vh, 460px);
        min-height: 320px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.04);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .item-grid-image-preview {
        display: none;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        object-position: center;
    }

    .item-grid-image-empty {
        color: rgba(255, 255, 255, 0.62);
    }

    #itemSetOtherChargeModal .modal-dialog {
        max-width: min(1320px, calc(100vw - 48px));
    }

    #itemSetOtherChargeModal .modal-body .table-responsive {
        overflow-x: visible;
    }

    #itemSetOtherChargeTable {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
        border-color: rgba(185, 198, 255, 0.28);
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
        border-color: rgba(185, 198, 255, 0.28) !important;
    }

    #itemSetOtherChargeTable thead th {
        background: #2b2f4a;
        color: #ffffff;
        box-shadow: inset 0 -1px 0 rgba(185, 198, 255, 0.35);
    }

    #itemSetOtherChargeTable th:nth-child(1),
    #itemSetOtherChargeTable td:nth-child(1) {
        width: 48px;
    }

    #itemSetOtherChargeTable th:nth-child(2),
    #itemSetOtherChargeTable td:nth-child(2) {
        width: 170px;
        white-space: normal;
    }

    #itemSetOtherChargeTable th:nth-child(3),
    #itemSetOtherChargeTable td:nth-child(3) {
        width: 145px;
    }

    #itemSetOtherChargeTable th:nth-child(4),
    #itemSetOtherChargeTable td:nth-child(4),
    #itemSetOtherChargeTable th:nth-child(5),
    #itemSetOtherChargeTable td:nth-child(5) {
        width: 120px;
    }

    #itemSetOtherChargeTable th:nth-child(6),
    #itemSetOtherChargeTable td:nth-child(6),
    #itemSetOtherChargeTable th:nth-child(8),
    #itemSetOtherChargeTable td:nth-child(8) {
        width: 125px;
    }

    #itemSetOtherChargeTable th:nth-child(7),
    #itemSetOtherChargeTable td:nth-child(7),
    #itemSetOtherChargeTable th:nth-child(9),
    #itemSetOtherChargeTable td:nth-child(9) {
        width: 115px;
    }

    #itemSetOtherChargeTable th:nth-child(10),
    #itemSetOtherChargeTable td:nth-child(10) {
        width: 72px;
        text-align: center;
    }

    #itemSetOtherChargeTable .form-control,
    #itemSetOtherChargeTable .form-select {
        width: 100%;
        min-width: 0;
        height: 46px;
        padding: 0 12px;
    }

    .other-charge-modal-header {
        gap: 16px;
        align-items: center;
    }

    .other-charge-modal-header .modal-title {
        flex: 0 0 auto;
        font-size: 20px;
        font-weight: 700;
        color: #ffffff;
    }

    .other-charge-search {
        max-width: 420px;
        margin-left: auto;
        background: #292d49;
        color: #ffffff;
        border: 1px solid rgba(150, 170, 255, 0.5);
    }

    .other-charge-search::placeholder {
        color: rgba(255, 255, 255, 0.56);
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
    let modalOtherChargeLines = [];
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
    const LABOUR_FORMULA_OPTIONS = [
        'Per Netweight',
        'Per Fineweight',
        'Per Grossweight',
        'Per Quantity',
        'Flat'
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
                recalcLabourAmountForAllRows(false);

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
        const rowFormula = normalizeLabourFormula(row.sale_labour_formula || selectedLabourFormula);

        $('#setsBody').append(`

        <tr data-id="${row.id}"
            data-other-weight="${row.other ?? 0}"
            data-image-show-url="${esc(row.image_show_url || '')}"
            data-image-upload-url="${esc(row.image_upload_url || '')}"
            data-image-remove-url="${esc(row.image_remove_url || '')}">
            <td class="sr-no"></td>

            <td contenteditable="true" class="cell" data-column="gross_weight">${row.gross_weight ?? ''}</td>

            <td>
                <div class="d-flex align-items-center gap-1">
                    <span class="other-weight-display">${nfix(row.other ?? 0, 3)}</span>
                    <button type="button" class="btn btn-sm btn-info open-other-charge-modal">Wt|Amt</button>
                </div>
            </td>

            <td contenteditable="false" class="cell" data-column="net_weight">${row.net_weight ?? ''}</td>

            <td class="cell formula-cell" data-column="sale_labour_formula">${buildLabourFormulaSelect(rowFormula)}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate">${row.sale_labour_rate ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount">${row.sale_labour_amount ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_other">${row.sale_other ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="supplier_person">${row.supplier_person ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="size">${row.size ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="HUID">${row.HUID ?? ''}</td>

            <td class="grid-image-cell">
                <button type="button" class="btn btn-sm btn-info grid-image-btn open-grid-image-modal">
                    ${row.image_path ? 'View Image' : 'Upload Image'}
                </button>
            </td>

        </tr>

    `);

    }


    //////////////////////////////////////////////////////
    // ADD EMPTY ROW
    //////////////////////////////////////////////////////

    function addEmptyRow() {
        const defaultFormula = normalizeLabourFormula(selectedLabourFormula);

        $('#setsBody').append(`

        <tr data-id="" data-other-weight="0" data-image-show-url="" data-image-upload-url="" data-image-remove-url="">
            <td class="sr-no"></td>

            <td contenteditable="true" class="cell" data-column="gross_weight"></td>

            <td>
                <div class="d-flex align-items-center gap-1">
                    <span class="other-weight-display">0.000</span>
                    <button type="button" class="btn btn-sm btn-info open-other-charge-modal">Wt|Amt</button>
                </div>
            </td>

            <td contenteditable="false" class="cell" data-column="net_weight"></td>

            <td class="cell formula-cell" data-column="sale_labour_formula">${buildLabourFormulaSelect(defaultFormula)}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate"></td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount"></td>

            <td contenteditable="true" class="cell" data-column="sale_other"></td>

            <td contenteditable="true" class="cell" data-column="supplier_person"></td>

            <td contenteditable="true" class="cell" data-column="size"></td>

            <td contenteditable="true" class="cell" data-column="HUID"></td>

            <td class="grid-image-cell">
                <button type="button" class="btn btn-sm btn-info grid-image-btn open-grid-image-modal">
                    Upload Image
                </button>
            </td>

        </tr>

    `);

        const $row = $('#setsBody tr').last();
        updateSrNumbers();
        return $row;
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
        const selectedFormula = getRowFormula(tr);

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
                value: value,
                sale_labour_formula: selectedFormula
            }
        ).done(function(res) {
            const wasNew = !tr.attr('data-id');
            tr.attr('data-id', res.id);
            updateRowImageUrls(tr, res);
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
                value: value,
                sale_labour_formula: getRowFormula($row)
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
        if (e.key !== 'Enter' && e.key !== 'Tab' && e.key !== 'ArrowDown') return;
        e.preventDefault();

        const $cell = $(this);
        const $row = $cell.closest('tr');
        const col = $cell.data('column');
        const currentIndex = editableColumns.indexOf(col);
        if (currentIndex === -1) return;

        saveCell($cell);

        const focusCell = function($target) {
            if (!$target || !$target.length) return;
            setTimeout(function() {
                $target.focus();
                placeCaretAtEnd($target[0]);
            }, 0);
        };

        const getOrCreateNextRow = function($fromRow) {
            let $nextRow = $fromRow.next('tr');
            if (!$nextRow.length || rowHasAnyValue($nextRow)) {
                addEmptyRow();
                updateSrNumbers();
                $nextRow = $fromRow.next('tr');
            }
            return $nextRow;
        };

        // Enter / ArrowDown => same column, next row.
        if (e.key === 'Enter' || e.key === 'ArrowDown') {
            const $nextRow = getOrCreateNextRow($row);
            focusCell($nextRow.find(`.cell[data-column="${col}"]`));
            return;
        }

        // Tab => next editable column in same row.
        const nextIndex = currentIndex + 1;
        if (nextIndex < editableColumns.length) {
            const nextCol = editableColumns[nextIndex];
            focusCell($row.find(`.cell[data-column="${nextCol}"]`));
            return;
        }

        // If last column on Tab => first editable column of next row.
        const $nextRow = getOrCreateNextRow($row);
        focusCell($nextRow.find(`.cell[data-column="${editableColumns[0]}"]`));
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

    function ensureEmptyRowAfterFilledLastRow() {
        const $rows = $('#setsBody tr');
        if (!$rows.length) {
            addEmptyRow();
            return;
        }

        if (rowHasAnyValue($rows.last())) {
            addEmptyRow();
        }
    }

    function applyFormulaToAllRows() {
        $('#setsBody tr').each(function() {
            const $row = $(this);
            const $select = $row.find('.labour-formula-select');
            if (!$select.length) return;

            const currentRaw = String($select.val() || '').trim();
            const current = normalizeLabourFormula(currentRaw);
            const fallback = normalizeLabourFormula(selectedLabourFormula);

            if (currentRaw === '' || currentRaw !== current) {
                $select.val(fallback);
                saveDerivedCell($row, 'sale_labour_formula', fallback);
            }
        });
    }

    function normalizeLabourFormula(value) {
        const raw = String(value || '').trim().toLowerCase().replace(/\s+/g, '');
        if (raw === 'pernetweight' || raw === 'per_netweight') return 'Per Netweight';
        if (raw === 'perfineweight' || raw === 'per_fineweight') return 'Per Fineweight';
        if (raw === 'pergrossweight' || raw === 'per_grossweight') return 'Per Grossweight';
        if (raw === 'perquantity' || raw === 'per_quantity') return 'Per Quantity';
        if (raw === 'flat') return 'Flat';
        return 'Per Netweight';
    }

    function buildLabourFormulaSelect(selectedValue) {
        const selected = normalizeLabourFormula(selectedValue);
        return `<select class="form-control form-control-sm labour-formula-select">${
            LABOUR_FORMULA_OPTIONS.map(option => {
                const isSelected = option === selected ? 'selected' : '';
                return `<option value="${option}" ${isSelected}>${option}</option>`;
            }).join('')
        }</select>`;
    }

    function getRowFormula($row) {
        const v = $row.find('.labour-formula-select').val();
        return normalizeLabourFormula(v);
    }

    function getLabourBaseWeight($row) {
        const formula = getRowFormula($row).toLowerCase().replace(/\s+/g, '');

        const gross = toNumber($row.find('.cell[data-column="gross_weight"]').text());
        const net = toNumber($row.find('.cell[data-column="net_weight"]').text());
        const purity = toNumber($('#purity').val());

        if (formula.includes('pergrossweight')) return gross;
        if (formula.includes('perfineweight')) {
            const purityFactor = purity > 0 ? (purity / 100) : 1;
            return net * purityFactor;
        }
        if (formula.includes('perquantity')) return 1;
        if (formula.includes('flat')) return 1;

        // Default: Per Net Weight
        return net;
    }

    function recalcLabourAmount($row, persist = false) {
        const rate = toNumber($row.find('.cell[data-column="sale_labour_rate"]').text());
        const baseWeight = getLabourBaseWeight($row);
        const amount = rate * baseWeight;
        const formatted = nfix(amount, 2);

        const $amountCell = $row.find('.cell[data-column="sale_labour_amount"]');
        $amountCell.text(formatted);

        if (persist) {
            saveDerivedCell($row, 'sale_labour_amount', formatted);
        }
    }

    function recalcLabourAmountForAllRows(persist = false) {
        $('#setsBody tr').each(function() {
            recalcLabourAmount($(this), persist);
        });
    }


    //////////////////////////////////////////////////////
    // LIVE TOTAL UPDATE
    //////////////////////////////////////////////////////

    $(document).on('input', '.cell[data-column="sale_other"]', function() {
        updateTotals();
        ensureEmptyRowAfterFilledLastRow();
    });

    $(document).on('input', '.cell[data-column="sale_labour_rate"]', function() {
        recalcLabourAmount($(this).closest('tr'), false);
        ensureEmptyRowAfterFilledLastRow();
    });

    $(document).on('input', '.cell[data-column="gross_weight"], .cell[data-column="supplier_person"], .cell[data-column="size"], .cell[data-column="HUID"]', function() {
        ensureEmptyRowAfterFilledLastRow();
    });

    $(document).on('click', '.content-wrapper', function(e) {
        if ($(e.target).closest('.modal, .dropdown-menu').length) return;
        ensureEmptyRowAfterFilledLastRow();
    });

    $(document).on('blur', '.cell[data-column="sale_labour_rate"]', function() {
        recalcLabourAmount($(this).closest('tr'), true);
    });

    $(document).on('change', '.labour-formula-select', function() {
        const $row = $(this).closest('tr');
        const formula = getRowFormula($row);
        $(this).val(formula);
        saveDerivedCell($row, 'sale_labour_formula', formula);
        recalcLabourAmount($row, true);
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

    let imageTargetRow = null;

    function updateRowImageUrls($row, data) {
        if (!$row || !$row.length || !data) return;

        if (data.image_show_url) {
            $row.attr('data-image-show-url', data.image_show_url);
        }

        if (data.image_upload_url) {
            $row.attr('data-image-upload-url', data.image_upload_url);
        }

        if (data.image_remove_url) {
            $row.attr('data-image-remove-url', data.image_remove_url);
        }
    }

    function resetGridImageMessages() {
        $('#grid_image_upload_error').addClass('d-none').text('');
        $('#grid_image_upload_success').addClass('d-none').text('');
    }

    function setGridImagePreview(url) {
        if (url) {
            $('#grid_item_image_preview').attr('src', url).show();
            $('#grid_item_image_empty').hide();
            $('#gridRemoveImageBtn').prop('disabled', false).show();
            return;
        }

        $('#grid_item_image_preview').removeAttr('src').hide();
        $('#grid_item_image_empty').show();
        $('#gridRemoveImageBtn').prop('disabled', true).hide();
    }

    function loadGridItemImage() {
        resetGridImageMessages();
        $('#grid_item_image_file').val('');

        $.get($('#grid_image_show_url').val(), function(data) {
            $('#grid_image_item_name').val(data.item_name || '-');
            $('#grid_image_label_code').val(data.label_code || '-');
            $('#grid_image_uploaded_at').val(data.image_uploaded_at || '-');
            setGridImagePreview(data.image_url || '');
            $('#itemSetImageModal').modal('show');
        }).fail(function() {
            $('#grid_image_upload_error').removeClass('d-none').text('Unable to load item image details.');
            $('#itemSetImageModal').modal('show');
        });
    }

    $(document).on('click', '.open-grid-image-modal', function() {
        const $row = $(this).closest('tr');
        const rowId = $row.attr('data-id');

        if (!rowId) {
            alert('Please enter item row details first, then upload image.');
            return;
        }

        const showUrl = $row.attr('data-image-show-url');
        const uploadUrl = $row.attr('data-image-upload-url');
        const removeUrl = $row.attr('data-image-remove-url');

        if (!showUrl || !uploadUrl || !removeUrl) {
            alert('Image upload URL is not ready. Please edit any cell in this row once and try again.');
            return;
        }

        imageTargetRow = $row;
        $('#grid_image_show_url').val(showUrl);
        $('#grid_image_upload_url').val(uploadUrl);
        $('#grid_image_remove_url').val(removeUrl);
        loadGridItemImage();
    });

    $('#grid_item_image_file').on('change', function() {
        resetGridImageMessages();

        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            loadGridItemImage();
            return;
        }

        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            $(this).val('');
            $('#grid_image_upload_error').removeClass('d-none').text('Please select a JPG, PNG or WebP image.');
            return;
        }

        setGridImagePreview(URL.createObjectURL(file));
    });

    $('#gridUploadImageBtn').on('click', function() {
        resetGridImageMessages();

        const fileInput = $('#grid_item_image_file')[0];
        if (!fileInput.files || !fileInput.files[0]) {
            $('#grid_image_upload_error').removeClass('d-none').text('Please choose an image to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('_token', "{{ csrf_token() }}");
        formData.append('image', fileInput.files[0]);

        $('#gridUploadImageBtn').prop('disabled', true).text('Uploading...');

        $.ajax({
            url: $('#grid_image_upload_url').val(),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                $('#grid_item_image_file').val('');
                $('#grid_image_uploaded_at').val(data.image_uploaded_at || '-');
                setGridImagePreview(data.image_url || '');
                $('#grid_image_upload_success').removeClass('d-none').text(data.message || 'Image uploaded successfully.');

                if (imageTargetRow && imageTargetRow.length) {
                    imageTargetRow.find('.open-grid-image-modal').text('View Image');
                }

                $('#itemSetImageModal').modal('hide');
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || xhr.responseJSON?.errors?.image?.[0] || 'Image upload failed.';
                $('#grid_image_upload_error').removeClass('d-none').text(message);
            },
            complete: function() {
                $('#gridUploadImageBtn').prop('disabled', false).text('Upload Image');
            }
        });
    });

    $('#gridRemoveImageBtn').on('click', function() {
        if (!confirm('Remove this item image?')) {
            return;
        }

        resetGridImageMessages();
        $('#gridRemoveImageBtn').prop('disabled', true).text('Removing...');

        $.ajax({
            url: $('#grid_image_remove_url').val(),
            method: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function() {
                $('#grid_item_image_file').val('');
                $('#grid_image_uploaded_at').val('-');
                setGridImagePreview('');
                $('#grid_image_upload_success').removeClass('d-none').text('Image removed successfully.');

                if (imageTargetRow && imageTargetRow.length) {
                    imageTargetRow.find('.open-grid-image-modal').text('Upload Image');
                }
            },
            error: function(xhr) {
                $('#grid_image_upload_error').removeClass('d-none').text(xhr.responseJSON?.message || 'Unable to remove image.');
            },
            complete: function() {
                $('#gridRemoveImageBtn').text('Remove Image');
            }
        });
    });

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
        modalOtherChargeLines = Array.isArray(lines) ? lines : [];
        const $tbody = $('#itemSetOtherChargeTable tbody');
        $tbody.empty();
        const searchTerm = ($('#itemSetOtherChargeSearch').val() || '').toLowerCase().trim();
        const selectedIds = new Set(modalOtherChargeLines.map(x => Number(x.charge_id)));
        const existingLineMap = new Map(
            modalOtherChargeLines.map(x => [Number(x.charge_id), x])
        );
        const visibleOptions = otherChargeOptions
            .filter(opt => !searchTerm || String(opt.name || '').toLowerCase().includes(searchTerm))
            .slice(0, 50);

        if (!visibleOptions.length) {
            $tbody.append('<tr><td colspan="10" class="text-center text-muted">No charge found</td></tr>');
            recalcModalCharges();
            return;
        }

        visibleOptions.forEach((opt, index) => {
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

    function mergeModalChargeLines() {
        const visibleIds = $('#itemSetOtherChargeTable tbody tr[data-id]').map(function() {
            return Number($(this).data('id'));
        }).get();
        const merged = new Map(
            modalOtherChargeLines
                .filter(line => !visibleIds.includes(Number(line.charge_id)))
                .map(line => [Number(line.charge_id), line])
        );

        collectModalChargeLines().forEach(line => merged.set(Number(line.charge_id), line));
        return Array.from(merged.values()).filter(line => Number(line.charge_id));
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
        recalcLabourAmount($row, true);
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
            $('#itemSetOtherChargeSearch').val('');
            renderOtherChargeRows(lines, rowContext);
            $('#itemSetOtherChargeModal').modal('show');
        });
    });

    $('#itemSetOtherChargeSearch').on('input', function() {
        const rowContext = modalTargetRow && modalTargetRow.length
            ? getRowWeightContext(modalTargetRow)
            : {};
        renderOtherChargeRows(mergeModalChargeLines(), rowContext);
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

        const lines = mergeModalChargeLines();
        const total = lines.reduce((sum, line) => sum + toNumber(line.total), 0);
        const $cell = modalTargetRow.find('.cell[data-column="sale_other"]');

        modalTargetRow.attr('data-other-charges', JSON.stringify(lines));
        $cell.text(nfix(total, 2));
        recalcRowWeightsFromCharges(modalTargetRow);
        saveCell($cell);
        updateTotals();
        ensureEmptyRowAfterFilledLastRow();

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
        ensureEmptyRowAfterFilledLastRow();
    });
</script>






<script>
    var urlTemplate = "{{ route('company.get-item-details', [$company->slug, ':id']) }}";

    if (window.jQuery && $.fn.select2) {
        $('#itemSelect').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            placeholder: 'Select Item'
        });
    }

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
                    recalcLabourAmountForAllRows(true);
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

    $('#purity').on('input blur', function() {
        recalcLabourAmountForAllRows(true);
    });
</script>


@endpush
