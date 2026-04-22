@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">{{ isset($data) ? 'Edit Jobwork Issue' : 'Create Jobwork Issue' }}</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ isset($data) ? route('company.jobwork-issue.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.jobwork-issue.store', $company->slug) }}">
                @csrf
                @if(isset($data))
                    @method('PUT')
                @endif

                @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="jobwork_date" class="form-control"
                                value="{{ old('jobwork_date', isset($data) ? optional($data->jobwork_date)->toDateString() : now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Jobworker *</label>
                            <select name="job_worker_id" class="form-select" required>
                                <option value="">Select Person</option>
                                @foreach($jobWorkers as $w)
                                <option value="{{ $w->id }}" {{ (string) old('job_worker_id', $data->job_worker_id ?? '') === (string) $w->id ? 'selected' : '' }}>
                                    {{ $w->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Production Step *</label>
                            <select name="production_step_id" class="form-select" required>
                                <option value="">Select Value</option>
                                @foreach($productionSteps as $s)
                                <option value="{{ $s->id }}" {{ (string) old('production_step_id', $data->production_step_id ?? '') === (string) $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:24%">Item Name</th>
                                <th>Purity</th>
                                <th>Gross Wt</th>
                                <th>Other</th>
                                <th>Net Wt</th>
                                <th>Fine Wt</th>
                                <th>Qty Pcs</th>
                                <th>Net Purity</th>
                                <th>Remarks</th>
                                <th>Total Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $oldItems = old('items', isset($data) ? $data->items->map(function($i){
                                return [
                                    'item_id' => $i->item_id,
                                    'other_charge_id' => $i->other_charge_id,
                                    'gross_wt' => $i->gross_wt,
                                    'other_wt' => $i->other_wt,
                                    'other_amt' => $i->other_amt,
                                    'purity' => $i->purity,
                                    'net_purity' => $i->net_purity,
                                    'net_wt' => $i->net_wt,
                                    'fine_wt' => $i->fine_wt,
                                    'qty_pcs' => $i->qty_pcs,
                                    'total_amt' => $i->total_amt,
                                    'remarks' => $i->remarks,
                                ];
                            })->toArray() : []);
                            @endphp

                            @if(count($oldItems))
                                @foreach($oldItems as $idx => $row)
                                <tr>
                                    <td>
                                        <select name="items[{{ $idx }}][item_id]" class="form-select item-select" required>
                                            <option value="">Select Item</option>
                                            @foreach($items as $item)
                                            @php
                                            $purity = (float) ($item->outward_purity ?? 0);
                                            @endphp
                                            <option value="{{ $item->id }}"
                                                data-purity="{{ $purity }}"
                                                data-net-purity="{{ $purity }}"
                                                {{ (string) ($row['item_id'] ?? '') === (string) $item->id ? 'selected' : '' }}>
                                                {{ $item->item_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.001" name="items[{{ $idx }}][purity]" class="form-control purity" value="{{ $row['purity'] ?? 0 }}" readonly></td>
                                    <td><input type="number" step="0.001" name="items[{{ $idx }}][gross_wt]" class="form-control gross-wt" value="{{ $row['gross_wt'] ?? 0 }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][other_charge_id]" class="other-charge-id" value="{{ $row['other_charge_id'] ?? '' }}">
                                        <input type="hidden" name="items[{{ $idx }}][other_wt]" class="other-wt" value="{{ $row['other_wt'] ?? 0 }}">
                                        <input type="hidden" name="items[{{ $idx }}][other_amt]" class="other-amt" value="{{ $row['other_amt'] ?? 0 }}">
                                        <input type="hidden" name="items[{{ $idx }}][other_charge_details]" class="other-charge-details" value="{{ $row['other_charge_details'] ?? '' }}">
                                        <button type="button" class="btn btn-sm btn-warning otherChargeBtn">Wt | Amt</button>
                                        <div class="small text-muted other-summary mt-1"></div>
                                    </td>
                                    <td><input type="number" step="0.001" name="items[{{ $idx }}][net_wt]" class="form-control net-wt" value="{{ $row['net_wt'] ?? 0 }}" readonly></td>
                                    <td><input type="number" step="0.001" name="items[{{ $idx }}][fine_wt]" class="form-control fine-wt" value="{{ $row['fine_wt'] ?? 0 }}" readonly></td>
                                    <td><input type="number" name="items[{{ $idx }}][qty_pcs]" class="form-control" value="{{ $row['qty_pcs'] ?? 0 }}"></td>
                                    <td><input type="number" step="0.001" name="items[{{ $idx }}][net_purity]" class="form-control net-purity" value="{{ $row['net_purity'] ?? 0 }}" readonly></td>
                                    <td><input type="text" name="items[{{ $idx }}][remarks]" class="form-control row-remarks" value="{{ $row['remarks'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" name="items[{{ $idx }}][total_amt]" class="form-control total-amt" value="{{ $row['total_amt'] ?? 0 }}"></td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td>
                                        <select name="items[0][item_id]" class="form-select item-select" required>
                                            <option value="">Select Item</option>
                                            @foreach($items as $item)
                                            @php
                                            $purity = (float) ($item->outward_purity ?? 0);
                                            @endphp
                                            <option value="{{ $item->id }}" data-purity="{{ $purity }}" data-net-purity="{{ $purity }}">{{ $item->item_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.001" name="items[0][purity]" class="form-control purity" value="0" readonly></td>
                                    <td><input type="number" step="0.001" name="items[0][gross_wt]" class="form-control gross-wt" value="0"></td>
                                    <td>
                                        <input type="hidden" name="items[0][other_charge_id]" class="other-charge-id" value="">
                                        <input type="hidden" name="items[0][other_wt]" class="other-wt" value="0">
                                        <input type="hidden" name="items[0][other_amt]" class="other-amt" value="0">
                                        <input type="hidden" name="items[0][other_charge_details]" class="other-charge-details" value="">
                                        <button type="button" class="btn btn-sm btn-warning otherChargeBtn">Wt | Amt</button>
                                        <div class="small text-muted other-summary mt-1"></div>
                                    </td>
                                    <td><input type="number" step="0.001" name="items[0][net_wt]" class="form-control net-wt" value="0" readonly></td>
                                    <td><input type="number" step="0.001" name="items[0][fine_wt]" class="form-control fine-wt" value="0" readonly></td>
                                    <td><input type="number" name="items[0][qty_pcs]" class="form-control" value="0"></td>
                                    <td><input type="number" step="0.001" name="items[0][net_purity]" class="form-control net-purity" value="0" readonly></td>
                                    <td><input type="text" name="items[0][remarks]" class="form-control row-remarks"></td>
                                    <td><input type="number" step="0.01" name="items[0][total_amt]" class="form-control total-amt" value="0"></td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
        </div>
        <div class="card-footer text-end">
            <a href="{{ route('company.jobwork-issue.index', $company->slug) }}" class="btn btn-info">Back</a>
            <button type="submit" class="btn btn-primary">{{ isset($data) ? 'Update' : 'Save' }}</button>
        </div>
            </form>
    </div>
</div>

<div class="modal fade" id="otherChargeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Other Charges</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <th>Weight</th>
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

@push('styles')
<style>
    #otherChargeTable {
        min-width: 1300px;
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
@endpush

@push('scripts')
<script>
let rowIndex = $('#itemsTable tbody tr').length;
let currentRow = null;
@php
    $otherChargeOptions = $otherCharges->map(function ($c) {
        return [
            'id' => $c->id,
            'name' => $c->other_charge,
            'default_amount' => (float) ($c->default_amount ?? 0),
            'default_weight' => (float) ($c->default_weight ?? 0),
            'quantity_pcs' => (float) ($c->quantity_pcs ?? 1),
            'weight_formula' => $c->weight_formula ?: 'flat',
            'weight_percent' => (float) ($c->weight_percent ?? 0),
            'other_amt_formula' => $c->other_amt_formula ?: 'flat',
            'wt_operation' => $c->wt_operation ?: 'less',
            'is_default' => (bool) ($c->is_default ?? false),
            'is_selected' => (bool) ($c->is_selected ?? false),
        ];
    })->values();
@endphp
let otherChargeOptions = @json($otherChargeOptions);

const otherChargeModal = new bootstrap.Modal(document.getElementById('otherChargeModal'));

const toNum = (v, d = 0) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : d;
};

const nfix = (v, d) => {
    const n = toNum(v);
    return (Math.abs(n) < 1e-9 ? 0 : n).toFixed(d);
};

const esc = (v) => $('<div>').text(v ?? '').html();

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
    const selected = normalizeFormula(currentValue, options.map(o => o.value), 'flat');
    let html = `<select class="form-control ${cls}">`;
    options.forEach(opt => {
        html += `<option value="${opt.value}" ${selected === opt.value ? 'selected' : ''}>${opt.label}</option>`;
    });
    html += '</select>';
    return html;
}

function initItemSelect($context) {
    $context.find('.item-select').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Select Item',
        allowClear: true
    });
}

function recalcRow($row) {
    const gross = toNum($row.find('.gross-wt').val());
    const otherWt = toNum($row.find('.other-wt').val());
    const netPurity = toNum($row.find('.net-purity').val());

    const net = gross - otherWt;
    const fine = net * netPurity / 100;

    $row.find('.net-wt').val(nfix(net, 3));
    $row.find('.fine-wt').val(nfix(fine, 3));
}

function updateOtherSummary($row) {
    const wt = nfix($row.find('.other-wt').val(), 3);
    const amt = nfix($row.find('.other-amt').val(), 2);
    $row.find('.other-summary').text(`Wt: ${wt} | Amt: ${amt}`);
}

function getSelectedItemPurity($row) {
    const $opt = $row.find('.item-select option:selected');
    if (!$opt.length || !$opt.val()) {
        return { purity: 0, netPurity: 0 };
    }

    const purity = toNum($opt.data('purity'));
    const netPurity = toNum($opt.data('net-purity'), purity);

    return { purity, netPurity };
}

function applyPurityFromItem($row) {
    const p = getSelectedItemPurity($row);
    $row.find('.purity').val(nfix(p.purity, 3));
    $row.find('.net-purity').val(nfix(p.netPurity, 3));
    recalcRow($row);
}

function appendRow() {
    const row = `
        <tr>
            <td>
                <select name="items[${rowIndex}][item_id]" class="form-select item-select" required>
                    <option value="">Select Item</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}" data-purity="{{ (float) ($item->outward_purity ?? 0) }}" data-net-purity="{{ (float) ($item->outward_purity ?? 0) }}">{{ $item->item_name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][purity]" class="form-control purity" value="0" readonly></td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][gross_wt]" class="form-control gross-wt" value="0"></td>
            <td>
                <input type="hidden" name="items[${rowIndex}][other_charge_id]" class="other-charge-id" value="">
                <input type="hidden" name="items[${rowIndex}][other_wt]" class="other-wt" value="0">
                <input type="hidden" name="items[${rowIndex}][other_amt]" class="other-amt" value="0">
                <input type="hidden" name="items[${rowIndex}][other_charge_details]" class="other-charge-details" value="">
                <button type="button" class="btn btn-sm btn-warning otherChargeBtn">Wt | Amt</button>
                <div class="small text-muted other-summary mt-1"></div>
            </td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][net_wt]" class="form-control net-wt" value="0" readonly></td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][fine_wt]" class="form-control fine-wt" value="0" readonly></td>
            <td><input type="number" name="items[${rowIndex}][qty_pcs]" class="form-control" value="0"></td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][net_purity]" class="form-control net-purity" value="0" readonly></td>
            <td><input type="text" name="items[${rowIndex}][remarks]" class="form-control row-remarks"></td>
            <td><input type="number" step="0.01" name="items[${rowIndex}][total_amt]" class="form-control total-amt" value="0"></td>
        </tr>`;

    const $row = $(row);
    $('#itemsTable tbody').append($row);
    initItemSelect($row);
    applyPurityFromItem($row);
    updateOtherSummary($row);
    rowIndex++;
}

function calculateLineWeight(wtFormula, itemWeight, defaultWeight, qty, weightPercent) {
    // For pack/bag type charges: qty should multiply default weight.
    if ((wtFormula === 'per_quantity' || wtFormula === 'per_weight') && defaultWeight > 0) {
        return defaultWeight * qty;
    }
    if (weightPercent > 0) return (itemWeight * weightPercent) / 100;
    if (wtFormula === 'per_weight') return itemWeight;
    if (wtFormula === 'per_quantity') return defaultWeight * qty;
    return defaultWeight;
}

function calculateLineAmount(amtFormula, amount, qty, chargeWeight, itemWeight) {
    if (amtFormula === 'per_quantity') return amount * qty;
    if (amtFormula === 'per_weight') return amount * chargeWeight;
    if (amtFormula === 'carat') return amount * itemWeight;
    return amount;
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
    const wtOperation = String($tr.data('wt-operation') || 'less').toLowerCase();

    const weight = calculateLineWeight(wtFormula, itemWeight, defaultWeight, qty, weightPercent);
    const total = calculateLineAmount(amtFormula, amount, qty, weight, itemWeight);

    $tr.data('amount', nfix(amount, 2));
    $tr.data('qty', nfix(qty, 3));
    $tr.data('wt-formula', wtFormula);
    $tr.data('amt-formula', amtFormula);
    $tr.data('weight', nfix(weight, 6));
    $tr.data('total', nfix(total, 2));
    $tr.data('wt-operation', wtOperation);
    $tr.find('.charge-weight-input').val(nfix(weight, 3));
    $tr.find('.charge-total-cell').text(nfix(total, 2));
}

function recalcModalCharges() {
    let total = 0;
    $('#otherChargeTable tbody tr').each(function () {
        const $tr = $(this);
        if (!$tr.find('.charge-check').is(':checked')) return;
        total += toNum($tr.data('total'));
    });
    $('#modalChargeTotal').text(nfix(total, 2));
}

function collectModalChargeLines() {
    const lines = [];
    $('#otherChargeTable tbody tr').each(function () {
        const $tr = $(this);
        if (!$tr.find('.charge-check').is(':checked')) return;

        lines.push({
            charge_id: toNum($tr.data('id')),
            charge_name: String($tr.data('name') || ''),
            amount: toNum($tr.data('amount')),
            qty: toNum($tr.data('qty')),
            wt_formula: String($tr.data('wt-formula') || 'flat'),
            amt_formula: String($tr.data('amt-formula') || 'flat'),
            weight: toNum($tr.data('weight')),
            total: toNum($tr.data('total')),
            wt_operation: String($tr.data('wt-operation') || 'less')
        });
    });

    return lines;
}

function renderOtherChargeRows(lines, rowContext) {
    const $tbody = $('#otherChargeTable tbody');
    $tbody.empty();
    const existingMap = new Map((lines || []).map(x => [Number(x.charge_id), x]));

    otherChargeOptions.forEach((opt, index) => {
        const existing = existingMap.get(Number(opt.id)) || null;
        // Do not auto-select by default. Only keep previously saved selections.
        const checked = existing ? 'checked' : '';
        const activeClass = checked ? 'table-active' : '';

        const amount = existing ? toNum(existing.amount, opt.default_amount) : toNum(opt.default_amount);
        const qty = existing ? toNum(existing.qty, opt.quantity_pcs || 1) : toNum(opt.quantity_pcs || 1);
        const wtFormula = existing ? String(existing.wt_formula || opt.weight_formula || 'flat') : String(opt.weight_formula || 'flat');
        const amtFormula = existing ? String(existing.amt_formula || opt.other_amt_formula || 'flat') : String(opt.other_amt_formula || 'flat');
        const wtOperation = existing ? String(existing.wt_operation || opt.wt_operation || 'less') : String(opt.wt_operation || 'less');

        $tbody.append(`
            <tr class="charge-row ${activeClass}"
                data-id="${opt.id}"
                data-name="${esc(opt.name)}"
                data-item-weight="${nfix(toNum(rowContext.net_weight || rowContext.gross_weight), 6)}"
                data-default-weight="${nfix(toNum(opt.default_weight), 6)}"
                data-weight-percent="${nfix(toNum(opt.weight_percent), 6)}"
                data-wt-operation="${esc(wtOperation)}"
                data-amount="${nfix(amount, 2)}"
                data-qty="${nfix(qty, 3)}"
                data-wt-formula="${esc(wtFormula)}"
                data-amt-formula="${esc(amtFormula)}"
                data-weight="0"
                data-total="0">
                <td class="charge-sr">${index + 1}</td>
                <td>${esc(opt.name || '-')}</td>
                <td><input type="number" step="0.01" class="form-control charge-amount-input text-end" value="${nfix(amount, 2)}"></td>
                <td><input type="number" step="0.001" class="form-control charge-qty-input text-end" value="${nfix(qty, 3)}"></td>
                <td>${buildFormulaSelect('wt', wtFormula)}</td>
                <td><input type="number" step="0.001" class="form-control charge-weight-input text-end" value="0" readonly></td>
                <td>${buildFormulaSelect('amt', amtFormula)}</td>
                <td class="text-end charge-total-cell">0.00</td>
                <td class="charge-select-col"><input type="checkbox" class="charge-check" ${checked}></td>
            </tr>
        `);

        recomputeChargeLine($tbody.find('tr:last'));
    });

    recalcModalCharges();
}

$(document).on('change', '.item-select', function () {
    const $row = $(this).closest('tr');
    applyPurityFromItem($row);
});

$(document).on('input', '.gross-wt', function () {
    recalcRow($(this).closest('tr'));
});

$(document).on('keydown', '#itemsTable tbody tr:last-child select, #itemsTable tbody tr:last-child input', function (e) {
    if (e.key !== 'ArrowDown') return;
    e.preventDefault();
    appendRow();
    $('#itemsTable tbody tr:last-child .item-select').focus();
});

$(document).on('click', '.otherChargeBtn', function () {
    currentRow = $(this).closest('tr');

    const detailsRaw = currentRow.find('.other-charge-details').val();
    let lines = [];
    if (detailsRaw) {
        try {
            lines = JSON.parse(detailsRaw);
        } catch (_) {
            lines = [];
        }
    }

    const rowContext = {
        gross_weight: toNum(currentRow.find('.gross-wt').val()),
        net_weight: toNum(currentRow.find('.net-wt').val())
    };

    $.get("{{ route('company.other-charge.options', $company->slug) }}", function (res) {
        otherChargeOptions = Array.isArray(res) ? res : [];
        renderOtherChargeRows(lines, rowContext);
        otherChargeModal.show();
    }).fail(function () {
        renderOtherChargeRows(lines, rowContext);
        otherChargeModal.show();
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
    if (!currentRow) {
        otherChargeModal.hide();
        return;
    }

    const lines = collectModalChargeLines();
    let totalAmt = 0;
    let lessWt = 0;
    let addWt = 0;

    lines.forEach(line => {
        totalAmt += toNum(line.total);
        if (String(line.wt_operation || 'less').toLowerCase() === 'add') {
            addWt += toNum(line.weight);
        } else {
            lessWt += toNum(line.weight);
        }
    });

    const effectiveOtherWt = lessWt - addWt;

    currentRow.find('.other-charge-id').val(lines.length ? lines[0].charge_id : '');
    currentRow.find('.other-wt').val(nfix(effectiveOtherWt, 3));
    currentRow.find('.other-amt').val(nfix(totalAmt, 2));
    currentRow.find('.other-charge-details').val(lines.length ? JSON.stringify(lines) : '');

    recalcRow(currentRow);
    updateOtherSummary(currentRow);
    otherChargeModal.hide();
});

initItemSelect($('#itemsTable'));
$('#itemsTable tbody tr').each(function () {
    applyPurityFromItem($(this));
    updateOtherSummary($(this));
});
</script>
@endpush
