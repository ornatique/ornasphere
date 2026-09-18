@extends('company_layout.admin')

@section('content')
@php
    $isEdit = isset($receive);
    $savedRows = old('items');
    if ($savedRows === null && $isEdit) {
        $savedRows = $receive->items->map(function ($row) {
            return [
                'item_id' => $row->item_id,
                'receive_gross_wt' => $row->receive_gross_wt,
                'other_wt' => $row->other_wt,
                'purity' => $row->purity,
                'waste_percent' => $row->waste_percent,
                'net_purity' => $row->net_purity,
                'receive_net_wt' => $row->receive_net_wt,
                'receive_fine_wt' => $row->receive_fine_wt,
                'metal_rate' => $row->metal_rate,
                'metal_amount' => $row->metal_amount,
                'labour_rate' => $row->labour_rate,
                'labour_amount' => $row->labour_amount,
                'other_amt' => $row->other_amt,
                'other_charge_details' => $row->other_charge_details,
                'total_amount' => $row->total_amount,
                'receive_qty_pcs' => $row->receive_qty_pcs,
                'remarks' => $row->remarks,
            ];
        })->values()->toArray();
    }

    if (empty($savedRows)) {
        $savedRows = [[
            'item_id' => '',
            'receive_gross_wt' => 0,
            'other_wt' => 0,
            'purity' => 0,
            'waste_percent' => 0,
            'net_purity' => 0,
            'receive_net_wt' => 0,
            'receive_fine_wt' => 0,
            'metal_rate' => 0,
            'metal_amount' => 0,
            'labour_rate' => 0,
            'labour_amount' => 0,
            'other_amt' => 0,
            'other_charge_details' => '',
            'total_amount' => 0,
            'receive_qty_pcs' => 1,
            'remarks' => '',
        ]];
    }

    $itemOptions = $items->map(function ($item) {
        $code = trim((string) ($item->item_code ?? ''));
        $label = $code !== '' ? $code : $item->item_name;

        return [
            'id' => (int) $item->id,
            'name' => $item->item_name,
            'item_code' => $code,
            'label' => $label,
            'display_name' => $label . ' - ' . $item->item_name . ' - Item',
            'purity' => number_format((float) ($item->outward_purity ?: $item->inward_purity ?: 0), 3, '.', ''),
                'labour_rate' => number_format((float) ($item->labour_rate ?? 0), 2, '.', ''),
        ];
    })->values();
    $directOtherChargeOptions = $otherCharges ?? collect();
@endphp

<div class="content-wrapper">
    <div class="card direct-receive-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">{{ $isEdit ? 'Edit Direct Jobwork Receive' : 'Direct Jobwork Receive' }}</h4>
                <div class="direct-receive-subtitle">Receive jobwork directly without selecting a jobwork issue voucher.</div>
            </div>
            <div class="d-flex gap-2">
                @if($isEdit)
                    <a href="{{ route('company.jobwork-receive.direct.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $receive->id)]) }}" class="btn btn-success">PDF</a>
                @endif
                <a href="{{ route('company.jobwork-receive.index', $company->slug) }}" class="btn btn-info">Back</a>
            </div>
        </div>

        <form method="POST" action="{{ $isEdit ? route('company.jobwork-receive.direct.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $receive->id)]) : route('company.jobwork-receive.direct.store', $company->slug) }}">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="direct-receive-panel mb-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Receive Date</label>
                            <input type="date" name="receive_date" class="form-control" value="{{ old('receive_date', $isEdit ? optional($receive->receive_date)->toDateString() : now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select direct-select" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $isEdit ? $receive->customer_id : '') === (string) $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md">
                        <div class="direct-total-card"><span>Qty</span><strong id="directTotalQty">0</strong></div>
                    </div>
                    <div class="col-md">
                        <div class="direct-total-card"><span>Gross Wt</span><strong id="directTotalGross">0.000</strong></div>
                    </div>
                    <div class="col-md">
                        <div class="direct-total-card"><span>Net Wt</span><strong id="directTotalNet">0.000</strong></div>
                    </div>
                    <div class="col-md">
                        <div class="direct-total-card"><span>Fine Wt</span><strong id="directTotalFine">0.000</strong></div>
                    </div>
                    <div class="col-md">
                        <div class="direct-total-card"><span>Total Amount</span><strong id="directTotalAmount">0.00</strong></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="section-title mb-0">Receive Items</h5>
                    <button type="button" class="btn btn-primary" id="addDirectReceiveRow">+ Add Row</button>
                </div>

                <div class="table-responsive direct-receive-scroll">
                    <table class="table table-bordered direct-receive-table" id="directReceiveTable">
                        <thead>
                            <tr>
                                <th>Sr</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Purity</th>
                                <th>Waste %</th>
                                <th>Net Purity</th>
                                <th>Gross Wt</th>
                                <th>Other Wt</th>
                                <th>Net Wt</th>
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
                        <tbody>
                            @foreach($savedRows as $index => $row)
                                @php
                                    $itemId = $row['item_id'] ?? '';
                                    $selectedItem = $items->firstWhere('id', (int) $itemId);
                                    $defaultPurity = (float) ($selectedItem?->outward_purity ?: $selectedItem?->inward_purity ?: 0);
                                    $purity = (float) ($row['purity'] ?? $defaultPurity);
                                    $waste = (float) ($row['waste_percent'] ?? 0);
                                    $netPurity = (float) ($row['net_purity'] ?? ($purity + $waste));
                                    $gross = (float) ($row['receive_gross_wt'] ?? 0);
                                    $other = (float) ($row['other_wt'] ?? 0);
                                    $net = max(0, $gross - $other);
                                    $fine = (float) ($row['receive_fine_wt'] ?? ($net * $netPurity / 100));
                                    $metalRate = (float) ($row['metal_rate'] ?? 0);
                                    $metalAmount = (float) ($row['metal_amount'] ?? ($fine * $metalRate));
                                    $labourRate = (float) ($row['labour_rate'] ?? ($selectedItem?->labour_rate ?? 0));
                                    $labourAmount = (float) ($row['labour_amount'] ?? ($net * $labourRate));
                                    $otherAmount = (float) ($row['other_amt'] ?? 0);
                                    $totalAmount = (float) ($row['total_amount'] ?? ($metalAmount + $labourAmount + $otherAmount));
                                @endphp
                                <tr class="direct-receive-row">
                                    <td class="direct-sr">{{ $index + 1 }}</td>
                                    <td>
                                        <select name="items[{{ $index }}][item_id]" class="form-select direct-item-select" required>
                                            <option value="">Select Item</option>
                                            @if($selectedItem)
                                                @php
                                                    $itemCode = trim((string) ($selectedItem->item_code ?? ''));
                                                    $itemLabel = $itemCode !== '' ? $itemCode : $selectedItem->item_name;
                                                    $displayName = $itemLabel . ' - ' . $selectedItem->item_name . ' - Item';
                                                @endphp
                                                <option value="{{ $selectedItem->id }}" data-label="{{ $itemLabel }}" data-name="{{ $selectedItem->item_name }}" data-type="Item" data-purity="{{ number_format($purity, 3, '.', '') }}" data-labour-rate="{{ number_format((float) ($selectedItem->labour_rate ?? 0), 2, '.', '') }}" selected>
                                                    {{ $displayName }}
                                                </option>
                                            @endif
                                        </select>
                                    </td>
                                    <td><input type="number" step="1" min="0" name="items[{{ $index }}][receive_qty_pcs]" class="form-control direct-qty" value="{{ (int) ($row['receive_qty_pcs'] ?? 1) }}"></td>
                                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][purity]" class="form-control direct-purity" value="{{ number_format($purity, 3, '.', '') }}"></td>
                                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][waste_percent]" class="form-control direct-waste" value="{{ number_format($waste, 3, '.', '') }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][net_purity]" class="direct-net-purity" value="{{ number_format($netPurity, 3, '.', '') }}">
                                        <input type="text" class="form-control direct-net-purity-view" value="{{ number_format($netPurity, 3, '.', '') }}" readonly>
                                    </td>
                                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][receive_gross_wt]" class="form-control direct-gross" value="{{ number_format($gross, 3, '.', '') }}"></td>
                                    <td><input type="number" step="0.001" min="0" name="items[{{ $index }}][other_wt]" class="form-control direct-other" value="{{ number_format($other, 3, '.', '') }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][receive_net_wt]" class="direct-net" value="{{ number_format($net, 3, '.', '') }}">
                                        <input type="text" class="form-control direct-net-view" value="{{ number_format($net, 3, '.', '') }}" readonly>
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][receive_fine_wt]" class="direct-fine" value="{{ number_format($fine, 3, '.', '') }}">
                                        <input type="text" class="form-control direct-fine-view" value="{{ number_format($fine, 3, '.', '') }}" readonly>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][metal_rate]" class="form-control direct-metal-rate" value="{{ number_format($metalRate, 2, '.', '') }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][metal_amount]" class="direct-metal-amount" value="{{ number_format($metalAmount, 2, '.', '') }}">
                                        <input type="text" class="form-control direct-metal-amount-view" value="{{ number_format($metalAmount, 2, '.', '') }}" readonly>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="items[{{ $index }}][labour_rate]" class="form-control direct-labour-rate" value="{{ number_format($labourRate, 2, '.', '') }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][labour_amount]" class="direct-labour-amount" value="{{ number_format($labourAmount, 2, '.', '') }}">
                                        <input type="text" class="form-control direct-labour-amount-view" value="{{ number_format($labourAmount, 2, '.', '') }}" readonly>
                                    </td>
                                    <td>
                                        <div class="input-group direct-other-wrap">
                                            <input type="number" step="0.01" min="0" name="items[{{ $index }}][other_amt]" class="form-control direct-other-amt" value="{{ number_format($otherAmount, 2, '.', '') }}">
                                            <button type="button" class="btn btn-info open-direct-other-modal" title="Other Charges">...</button>
                                        </div>
                                        <input type="hidden" name="items[{{ $index }}][other_charge_details]" class="direct-other-charge-details" value="{{ $row['other_charge_details'] ?? '' }}">
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][total_amount]" class="direct-total-amount" value="{{ number_format($totalAmount, 2, '.', '') }}">
                                        <input type="text" class="form-control direct-total-amount-view" value="{{ number_format($totalAmount, 2, '.', '') }}" readonly>
                                    </td>
                                    <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $row['remarks'] ?? '' }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-danger remove-direct-row">Delete</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $isEdit ? $receive->remarks : '') }}</textarea>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('company.jobwork-receive.index', $company->slug) }}" class="btn btn-info">Back</a>
                <button type="submit" class="btn btn-success">{{ $isEdit ? 'Update Direct Receive' : 'Save Direct Receive' }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="directOtherChargeModal" tabindex="-1" role="dialog" aria-labelledby="directOtherChargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header align-items-center">
                <h5 class="modal-title" id="directOtherChargeModalLabel">Other Charges</h5>
                <input type="text" class="form-control direct-other-search" id="directOtherChargeSearch" placeholder="Search charge">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="direct-other-charge-table-wrap">
                    <table class="table table-bordered" id="directOtherChargeTable">
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
                    <strong>Charge Total:</strong> <span id="directModalChargeTotal">0.00</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="applyDirectOtherCharges">Apply</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .direct-receive-subtitle {
        color: #b9c2dc;
        font-size: 13px;
    }

    .direct-receive-panel,
    .direct-total-card {
        border: 1px solid rgba(148, 163, 184, 0.18);
        background: #292c45;
        border-radius: 8px;
        padding: 14px;
    }

    .direct-total-card span {
        display: block;
        color: #b9c2dc;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .direct-total-card strong {
        color: #fff;
        font-size: 22px;
        line-height: 1;
    }

    .direct-receive-scroll {
        max-height: clamp(280px, calc(100vh - 430px), 560px);
        overflow: auto;
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 6px;
    }

    .direct-receive-table {
        min-width: 2250px;
        margin-bottom: 0;
    }

    .direct-receive-table th,
    .direct-receive-table td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .direct-receive-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263b;
    }

    .direct-receive-table th:nth-child(2),
    .direct-receive-table td:nth-child(2) {
        min-width: 380px;
        width: 380px;
    }

    .direct-receive-table .direct-item-select,
    .direct-receive-table .select2-container {
        min-width: 360px;
        width: 100% !important;
    }

    .direct-receive-table .select2-container--bootstrap4 .select2-selection--single,
    .direct-receive-table .select2-container--default .select2-selection--single {
        min-height: 58px;
        display: flex;
        align-items: center;
    }

    .direct-receive-table .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered,
    .direct-receive-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.2;
        white-space: normal;
    }

    .direct-item-result strong,
    .direct-item-selection strong {
        display: block;
        color: inherit;
        font-size: 14px;
        line-height: 1.15;
    }

    .direct-item-result small,
    .direct-item-selection small {
        display: block;
        color: inherit;
        font-size: 12px;
        line-height: 1.2;
        opacity: 0.92;
    }

    .direct-receive-table .form-control {
        min-width: 130px;
    }

    .direct-other-wrap {
        min-width: 190px;
    }

    .direct-other-wrap .open-direct-other-modal {
        min-width: 42px;
        padding-left: 10px;
        padding-right: 10px;
    }

    #directOtherChargeModal .modal-dialog {
        max-width: min(1320px, calc(100vw - 56px));
    }

    #directOtherChargeModal .modal-header {
        display: flex;
        gap: 16px;
        padding: 22px 26px;
    }

    #directOtherChargeModal .modal-title {
        color: #fff;
        font-size: 22px;
        font-weight: 800;
        line-height: 1.2;
        margin: 0;
    }

    #directOtherChargeSearch {
        max-width: 430px;
        margin-left: auto;
    }

    .direct-other-charge-table-wrap {
        overflow-x: hidden;
        width: 100%;
    }

    #directOtherChargeTable {
        width: 100%;
        table-layout: fixed;
        margin-bottom: 0;
        border-color: rgba(190, 200, 230, 0.35);
    }

    #directOtherChargeTable th,
    #directOtherChargeTable td {
        border-color: rgba(190, 200, 230, 0.35) !important;
        background: #30344a;
        color: #f8fafc;
        white-space: nowrap;
        vertical-align: middle;
    }

    #directOtherChargeTable thead th {
        background: #282a42;
        color: #fff;
        font-weight: 700;
    }

    #directOtherChargeTable tbody tr.table-active td {
        background: #363b55;
    }

    #directOtherChargeTable tbody tr:hover td {
        background: #3a3f5c;
    }

    #directOtherChargeTable .form-control,
    #directOtherChargeTable .form-select {
        border: 1px solid rgba(190, 200, 230, 0.18);
        background: #2d2f4d;
        color: #fff;
    }

    #directOtherChargeTable .charge-sr,
    #directOtherChargeTable .charge-select-col {
        text-align: center;
    }

    #directOtherChargeTable .charge-row {
        cursor: pointer;
    }

    #directOtherChargeTable th:nth-child(1),
    #directOtherChargeTable td:nth-child(1) {
        width: 52px;
    }

    #directOtherChargeTable th:nth-child(2),
    #directOtherChargeTable td:nth-child(2) {
        width: 19%;
        white-space: normal;
    }

    #directOtherChargeTable th:nth-child(3),
    #directOtherChargeTable td:nth-child(3),
    #directOtherChargeTable th:nth-child(4),
    #directOtherChargeTable td:nth-child(4),
    #directOtherChargeTable th:nth-child(6),
    #directOtherChargeTable td:nth-child(6) {
        width: 14%;
    }

    #directOtherChargeTable th:nth-child(5),
    #directOtherChargeTable td:nth-child(5),
    #directOtherChargeTable th:nth-child(7),
    #directOtherChargeTable td:nth-child(7) {
        width: 11%;
    }

    #directOtherChargeTable th:nth-child(8),
    #directOtherChargeTable td:nth-child(8) {
        width: 9%;
    }

    #directOtherChargeTable th:nth-child(9),
    #directOtherChargeTable td:nth-child(9) {
        width: 66px;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    let rowIndex = $('#directReceiveTable tbody tr').length;
    const itemOptions = @json($itemOptions);
    const otherChargeOptions = @json($directOtherChargeOptions);
    let activeDirectOtherRow = null;

    function numberValue(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function fixed(value) {
        return numberValue(value).toFixed(3);
    }

    function money(value) {
        return numberValue(value).toFixed(2);
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    const weightFormulaOptions = [
        { value: 'flat', label: 'flat' },
        { value: 'per_weight', label: 'per_weight' },
        { value: 'per_quantity', label: 'per_quantity' }
    ];

    const amountFormulaOptions = [
        { value: 'flat', label: 'flat' },
        { value: 'per_weight', label: 'per_weight' },
        { value: 'per_quantity', label: 'per_quantity' },
        { value: 'carat', label: 'carat' }
    ];

    function normalizeFormula(value, allowed, fallback = 'flat') {
        const normalized = String(value || fallback).toLowerCase();
        return allowed.includes(normalized) ? normalized : fallback;
    }

    function buildFormulaSelect(type, currentValue) {
        const options = type === 'wt' ? weightFormulaOptions : amountFormulaOptions;
        const className = type === 'wt' ? 'charge-wt-formula' : 'charge-amt-formula';
        const selected = normalizeFormula(currentValue, options.map((option) => option.value), 'flat');

        return `<select class="form-control ${className}">`
            + options.map((option) => `<option value="${option.value}" ${selected === option.value ? 'selected' : ''}>${option.label}</option>`).join('')
            + '</select>';
    }

    function calculateChargeWeight(wtFormula, itemWeight, defaultWeight, qty, weightPercent) {
        if ((wtFormula === 'per_quantity' || wtFormula === 'per_weight') && defaultWeight > 0) {
            return defaultWeight * qty;
        }
        if (weightPercent > 0) {
            return (itemWeight * weightPercent) / 100;
        }
        if (wtFormula === 'per_weight') {
            return itemWeight;
        }
        if (wtFormula === 'per_quantity') {
            return defaultWeight * qty;
        }
        return defaultWeight;
    }

    function calculateChargeAmount(amtFormula, amount, qty, chargeWeight, itemWeight) {
        if (amtFormula === 'per_quantity') {
            return amount * qty;
        }
        if (amtFormula === 'per_weight') {
            return amount * chargeWeight;
        }
        if (amtFormula === 'carat') {
            return amount * itemWeight;
        }
        return amount;
    }

    function parseOtherChargeDetails($row) {
        const raw = $row.find('.direct-other-charge-details').val() || '';
        if (!raw) return [];

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    }

    function recomputeDirectChargeLine($line) {
        const amount = numberValue($line.find('.charge-amount-input').val());
        const qty = numberValue($line.find('.charge-qty-input').val() || 1);
        const wtFormula = normalizeFormula($line.find('.charge-wt-formula').val(), weightFormulaOptions.map((option) => option.value), 'flat');
        const amtFormula = normalizeFormula($line.find('.charge-amt-formula').val(), amountFormulaOptions.map((option) => option.value), 'flat');
        const itemWeight = numberValue($line.data('item-weight'));
        const defaultWeight = numberValue($line.data('default-weight'));
        const weightPercent = numberValue($line.data('weight-percent'));
        const weight = calculateChargeWeight(wtFormula, itemWeight, defaultWeight, qty, weightPercent);
        const total = calculateChargeAmount(amtFormula, amount, qty, weight, itemWeight);

        $line.attr('data-amount', money(amount));
        $line.attr('data-qty', fixed(qty));
        $line.attr('data-wt-formula', wtFormula);
        $line.attr('data-amt-formula', amtFormula);
        $line.attr('data-weight', fixed(weight));
        $line.attr('data-total', money(total));
        $line.find('.charge-weight-input').val(fixed(weight));
        $line.find('.charge-total-cell').text(money(total));
    }

    function recalcDirectModalTotal() {
        let total = 0;
        $('#directOtherChargeTable tbody tr').each(function () {
            const $line = $(this);
            if ($line.find('.charge-check').is(':checked')) {
                total += numberValue($line.attr('data-total'));
            }
        });

        $('#directModalChargeTotal').text(money(total));
    }

    function renderDirectOtherChargeRows($row) {
        const $tbody = $('#directOtherChargeTable tbody');
        const searchTerm = ($('#directOtherChargeSearch').val() || '').toLowerCase().trim();
        const details = parseOtherChargeDetails($row);
        const existingMap = new Map(details.map((line) => [Number(line.charge_id), line]));
        const grossWeight = numberValue($row.find('.direct-gross').val());
        $tbody.empty();

        (otherChargeOptions || [])
            .filter((charge) => !searchTerm || String(charge.name || '').toLowerCase().includes(searchTerm))
            .forEach(function (charge, index) {
                const existing = existingMap.get(Number(charge.id)) || null;
                const checked = existing ? 'checked' : '';
                const activeClass = checked ? 'table-active' : '';
                const amount = existing ? numberValue(existing.amount) : numberValue(charge.default_amount);
                const qty = existing ? numberValue(existing.qty || 1) : numberValue(charge.quantity_pcs || 1);
                const wtFormula = existing ? String(existing.wt_formula || charge.weight_formula || 'flat') : String(charge.weight_formula || 'flat');
                const amtFormula = existing ? String(existing.amt_formula || charge.other_amt_formula || 'flat') : String(charge.other_amt_formula || 'flat');
                const wtOperation = existing ? String(existing.wt_operation || charge.wt_operation || 'less') : String(charge.wt_operation || 'less');

                const html = `
                    <tr class="charge-row ${activeClass}"
                        data-id="${charge.id}"
                        data-name="${escapeHtml(charge.name || '')}"
                        data-item-weight="${fixed(grossWeight)}"
                        data-default-weight="${fixed(charge.default_weight || 0)}"
                        data-weight-percent="${fixed(charge.weight_percent || 0)}"
                        data-wt-operation="${escapeHtml(wtOperation)}"
                        data-amount="${money(amount)}"
                        data-qty="${fixed(qty)}"
                        data-wt-formula="${escapeHtml(wtFormula)}"
                        data-amt-formula="${escapeHtml(amtFormula)}"
                        data-weight="0.000"
                        data-total="0.00">
                        <td class="charge-sr">${index + 1}</td>
                        <td>${escapeHtml(charge.name || '-')}</td>
                        <td><input type="number" step="0.01" class="form-control charge-amount-input text-end" value="${money(amount)}"></td>
                        <td><input type="number" step="0.001" class="form-control charge-qty-input text-end" value="${fixed(qty)}"></td>
                        <td>${buildFormulaSelect('wt', wtFormula)}</td>
                        <td><input type="number" step="0.001" class="form-control charge-weight-input text-end" value="0.000" readonly></td>
                        <td>${buildFormulaSelect('amt', amtFormula)}</td>
                        <td class="text-end charge-total-cell">0.00</td>
                        <td class="charge-select-col"><input type="checkbox" class="charge-check" ${checked}></td>
                    </tr>`;

                const $line = $(html).appendTo($tbody);
                recomputeDirectChargeLine($line);
            });

        recalcDirectModalTotal();
    }

    function collectDirectModalChargeLines() {
        const lines = [];

        $('#directOtherChargeTable tbody tr').each(function () {
            const $line = $(this);
            if (!$line.find('.charge-check').is(':checked')) return;

            lines.push({
                charge_id: numberValue($line.data('id')),
                charge_name: String($line.data('name') || ''),
                amount: numberValue($line.attr('data-amount')),
                qty: numberValue($line.attr('data-qty')),
                wt_formula: String($line.attr('data-wt-formula') || 'flat'),
                amt_formula: String($line.attr('data-amt-formula') || 'flat'),
                weight: numberValue($line.attr('data-weight')),
                total: numberValue($line.attr('data-total')),
                wt_operation: String($line.data('wt-operation') || 'less')
            });
        });

        return lines;
    }

    function formatDirectItemOption(data) {
        if (!data.id) {
            return data.text || '';
        }

        const $option = data.element ? $(data.element) : null;
        const label = data.label || ($option ? $option.data('label') : '') || data.text || '';
        const name = data.name || ($option ? $option.data('name') : '') || data.text || '';
        const type = data.type || ($option ? $option.data('type') : '') || 'Item';

        return $(`
            <div class="direct-item-result">
                <strong>${escapeHtml(label)}</strong>
                <small>${escapeHtml(name)} - ${escapeHtml(type)}</small>
            </div>
        `);
    }

    function formatDirectItemSelection(data) {
        if (!data.id) {
            return data.text || '';
        }

        const $option = data.element ? $(data.element) : null;
        const label = data.label || ($option ? $option.data('label') : '') || data.text || '';
        const name = data.name || ($option ? $option.data('name') : '') || '';
        const type = data.type || ($option ? $option.data('type') : '') || 'Item';

        return $(`
            <span class="direct-item-selection">
                <strong>${escapeHtml(label)}</strong>
                <small>${escapeHtml(name)} - ${escapeHtml(type)}</small>
            </span>
        `);
    }

    function initSelect2(context = document) {
        if (!$.fn.select2) return;

        $(context).find('.direct-select, .direct-item-select').each(function () {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) return;

            const options = {
                theme: 'bootstrap4',
                width: '100%',
                placeholder: $select.hasClass('direct-item-select') ? 'Select Item' : 'Select Value',
                allowClear: true,
                templateResult: $select.hasClass('direct-item-select') ? formatDirectItemOption : undefined,
                templateSelection: $select.hasClass('direct-item-select') ? formatDirectItemSelection : undefined,
                escapeMarkup: function(markup) {
                    return markup;
                }
            };

            if ($select.hasClass('direct-item-select')) {
                options.minimumInputLength = 2;
                options.language = {
                    inputTooShort: function () {
                        return 'Type 2 or more characters to search label or item';
                    },
                    noResults: function () {
                        return 'No label or item found';
                    },
                    searching: function () {
                        return 'Searching...';
                    }
                };
                options.ajax = {
                    url: "{{ route('company.approval.searchItemSets', $company->slug) }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            keyword: params.term || '',
                            limit: 50
                        };
                    },
                    processResults: function (rows) {
                        return {
                            results: (rows || []).map(function (row) {
                                const isItemOnly = !!row.is_item_only;
                                const item = row.item || {};
                                const label = row.qr_code || row.HUID || row.barcode || item.item_code || row.code || item.item_name || row.item_name || '';
                                const name = item.item_name || row.item_name || row.name || '';
                                const type = isItemOnly ? 'Item' : 'Label';
                                const itemId = row.item_id || item.id || row.id;
                                const gross = numberValue(row.gross_weight);
                                const other = numberValue(row.other || row.other_weight);
                                const net = numberValue(row.net_weight || Math.max(0, gross - other));
                                const purity = numberValue(item.outward_purity ?? row.purity);
                                const labourRate = numberValue(row.sale_labour_rate ?? item.labour_rate ?? row.labour_rate);
                                const otherAmount = numberValue(row.sale_other ?? row.other_amount);

                                return {
                                    id: itemId,
                                    text: `${label} - ${name} - ${type}`,
                                    label: label,
                                    name: name,
                                    type: type,
                                    purity: purity,
                                    labour_rate: labourRate,
                                    gross_weight: gross,
                                    other_weight: other,
                                    net_weight: net,
                                    other_amount: otherAmount
                                };
                            })
                        };
                    },
                    cache: true
                };
            }

            $select.select2(options);
        });
    }

    function calculateRow($row) {
        const gross = numberValue($row.find('.direct-gross').val());
        const other = numberValue($row.find('.direct-other').val());
        const purity = numberValue($row.find('.direct-purity').val());
        const waste = numberValue($row.find('.direct-waste').val());
        const metalRate = numberValue($row.find('.direct-metal-rate').val());
        const labourRate = numberValue($row.find('.direct-labour-rate').val());
        const otherAmount = numberValue($row.find('.direct-other-amt').val());
        const net = Math.max(0, gross - other);
        const netPurity = purity + waste;
        const fine = net * netPurity / 100;
        const metalAmount = fine * metalRate;
        const labourAmount = net * labourRate;
        const totalAmount = metalAmount + labourAmount + otherAmount;

        $row.find('.direct-net-purity').val(fixed(netPurity));
        $row.find('.direct-net-purity-view').val(fixed(netPurity));
        $row.find('.direct-net').val(fixed(net));
        $row.find('.direct-net-view').val(fixed(net));
        $row.find('.direct-fine').val(fixed(fine));
        $row.find('.direct-fine-view').val(fixed(fine));
        $row.find('.direct-metal-amount').val(money(metalAmount));
        $row.find('.direct-metal-amount-view').val(money(metalAmount));
        $row.find('.direct-labour-amount').val(money(labourAmount));
        $row.find('.direct-labour-amount-view').val(money(labourAmount));
        $row.find('.direct-total-amount').val(money(totalAmount));
        $row.find('.direct-total-amount-view').val(money(totalAmount));
    }

    function updateTotals() {
        let qty = 0;
        let gross = 0;
        let net = 0;
        let fine = 0;
        let totalAmount = 0;

        $('#directReceiveTable tbody tr').each(function () {
            const $row = $(this);
            calculateRow($row);
            qty += parseInt($row.find('.direct-qty').val() || '0', 10) || 0;
            gross += numberValue($row.find('.direct-gross').val());
            net += numberValue($row.find('.direct-net').val());
            fine += numberValue($row.find('.direct-fine').val());
            totalAmount += numberValue($row.find('.direct-total-amount').val());
        });

        $('#directTotalQty').text(qty);
        $('#directTotalGross').text(fixed(gross));
        $('#directTotalNet').text(fixed(net));
        $('#directTotalFine').text(fixed(fine));
        $('#directTotalAmount').text(money(totalAmount));
    }

    function refreshSrAndNames() {
        $('#directReceiveTable tbody tr').each(function (index) {
            $(this).find('.direct-sr').text(index + 1);
            $(this).find('select, input').each(function () {
                const name = $(this).attr('name');
                if (!name) return;
                $(this).attr('name', name.replace(/items\[\d+\]/, `items[${index}]`));
            });
        });
        rowIndex = $('#directReceiveTable tbody tr').length;
    }

    function itemOptionsHtml() {
        return '<option value="">Select Item</option>';
    }

    $('#addDirectReceiveRow').on('click', function () {
        const html = `
            <tr class="direct-receive-row">
                <td class="direct-sr">${rowIndex + 1}</td>
                <td><select name="items[${rowIndex}][item_id]" class="form-select direct-item-select" required>${itemOptionsHtml()}</select></td>
                <td><input type="number" step="1" min="0" name="items[${rowIndex}][receive_qty_pcs]" class="form-control direct-qty" value="1"></td>
                <td><input type="number" step="0.001" min="0" name="items[${rowIndex}][purity]" class="form-control direct-purity" value="0.000"></td>
                <td><input type="number" step="0.001" min="0" name="items[${rowIndex}][waste_percent]" class="form-control direct-waste" value="0.000"></td>
                <td><input type="hidden" name="items[${rowIndex}][net_purity]" class="direct-net-purity" value="0.000"><input type="text" class="form-control direct-net-purity-view" value="0.000" readonly></td>
                <td><input type="number" step="0.001" min="0" name="items[${rowIndex}][receive_gross_wt]" class="form-control direct-gross" value="0.000"></td>
                <td><input type="number" step="0.001" min="0" name="items[${rowIndex}][other_wt]" class="form-control direct-other" value="0.000"></td>
                <td><input type="hidden" name="items[${rowIndex}][receive_net_wt]" class="direct-net" value="0.000"><input type="text" class="form-control direct-net-view" value="0.000" readonly></td>
                <td><input type="hidden" name="items[${rowIndex}][receive_fine_wt]" class="direct-fine" value="0.000"><input type="text" class="form-control direct-fine-view" value="0.000" readonly></td>
                <td><input type="number" step="0.01" min="0" name="items[${rowIndex}][metal_rate]" class="form-control direct-metal-rate" value="0.00"></td>
                <td><input type="hidden" name="items[${rowIndex}][metal_amount]" class="direct-metal-amount" value="0.00"><input type="text" class="form-control direct-metal-amount-view" value="0.00" readonly></td>
                <td><input type="number" step="0.01" min="0" name="items[${rowIndex}][labour_rate]" class="form-control direct-labour-rate" value="0.00"></td>
                <td><input type="hidden" name="items[${rowIndex}][labour_amount]" class="direct-labour-amount" value="0.00"><input type="text" class="form-control direct-labour-amount-view" value="0.00" readonly></td>
                <td>
                    <div class="input-group direct-other-wrap">
                        <input type="number" step="0.01" min="0" name="items[${rowIndex}][other_amt]" class="form-control direct-other-amt" value="0.00">
                        <button type="button" class="btn btn-info open-direct-other-modal" title="Other Charges">...</button>
                    </div>
                    <input type="hidden" name="items[${rowIndex}][other_charge_details]" class="direct-other-charge-details" value="">
                </td>
                <td><input type="hidden" name="items[${rowIndex}][total_amount]" class="direct-total-amount" value="0.00"><input type="text" class="form-control direct-total-amount-view" value="0.00" readonly></td>
                <td><input type="text" name="items[${rowIndex}][remarks]" class="form-control" value=""></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-direct-row">Delete</button></td>
            </tr>`;

        const $row = $(html).appendTo('#directReceiveTable tbody');
        rowIndex++;
        initSelect2($row);
        updateTotals();
    });

    $(document).on('select2:select', '.direct-item-select', function (event) {
        const data = event.params.data || {};
        const $option = $(this).find(':selected');
        const $row = $(this).closest('tr');

        $option.attr('data-label', data.label || data.text || '');
        $option.attr('data-name', data.name || '');
        $option.attr('data-type', data.type || 'Item');
        $option.attr('data-purity', fixed(data.purity));
        $option.attr('data-labour-rate', money(data.labour_rate));

        $row.find('.direct-purity').val(fixed(data.purity));
        $row.find('.direct-labour-rate').val(money(data.labour_rate));

        if ((data.type || '').toLowerCase() === 'label') {
            $row.find('.direct-gross').val(fixed(data.gross_weight));
            $row.find('.direct-other').val(fixed(data.other_weight));
            $row.find('.direct-other-amt').val(money(data.other_amount));
        }

        updateTotals();
    });

    $(document).on('select2:clear', '.direct-item-select', function () {
        const $row = $(this).closest('tr');
        $row.find('.direct-purity').val('0.000');
        $row.find('.direct-waste').val('0.000');
        $row.find('.direct-labour-rate').val('0.00');
        updateTotals();
    });

    $(document).on('input', '.direct-gross, .direct-other, .direct-qty, .direct-purity, .direct-waste, .direct-metal-rate, .direct-labour-rate, .direct-other-amt', updateTotals);

    $(document).on('click', '.open-direct-other-modal', function () {
        activeDirectOtherRow = $(this).closest('tr');
        $('#directOtherChargeSearch').val('');
        renderDirectOtherChargeRows(activeDirectOtherRow);
        $('#directOtherChargeModal').modal('show');
    });

    $('#directOtherChargeSearch').on('input', function () {
        if (activeDirectOtherRow) {
            renderDirectOtherChargeRows(activeDirectOtherRow);
        }
    });

    $(document).on('input change', '#directOtherChargeTable .charge-amount-input, #directOtherChargeTable .charge-qty-input, #directOtherChargeTable .charge-wt-formula, #directOtherChargeTable .charge-amt-formula', function () {
        const $line = $(this).closest('tr');
        recomputeDirectChargeLine($line);
        recalcDirectModalTotal();
    });

    $(document).on('change', '#directOtherChargeTable .charge-check', function () {
        $(this).closest('tr').toggleClass('table-active', this.checked);
        recalcDirectModalTotal();
    });

    $(document).on('click', '#directOtherChargeTable .charge-row', function (event) {
        if ($(event.target).is('input, select, option')) {
            return;
        }

        const $check = $(this).find('.charge-check');
        $check.prop('checked', !$check.prop('checked')).trigger('change');
    });

    $('#applyDirectOtherCharges').on('click', function () {
        if (!activeDirectOtherRow) {
            $('#directOtherChargeModal').modal('hide');
            return;
        }

        const lines = collectDirectModalChargeLines();
        const total = lines.reduce((sum, line) => sum + numberValue(line.total), 0);
        activeDirectOtherRow.find('.direct-other-charge-details').val(JSON.stringify(lines));
        activeDirectOtherRow.find('.direct-other-amt').val(money(total));
        updateTotals();
        $('#directOtherChargeModal').modal('hide');
    });

    $(document).on('click', '.remove-direct-row', function () {
        if ($('#directReceiveTable tbody tr').length <= 1) {
            return;
        }

        const $select = $(this).closest('tr').find('.direct-item-select');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $(this).closest('tr').remove();
        refreshSrAndNames();
        updateTotals();
    });

    initSelect2();
    updateTotals();
});
</script>
@endpush
