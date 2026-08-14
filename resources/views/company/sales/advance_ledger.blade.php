@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Add Receive / Return / Purchase</h4>
            <a href="{{ route('company.sales.advance.index', $company->slug) }}" class="btn btn-primary">Back</a>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-3 align-items-end" id="customerLoadForm">
                <div class="col-md-5">
                    <label>Party (Active Customer)</label>
                    <select name="customer_id" id="customer_id" class="form-select searchable-party-select">
                        <option value="">Select Party</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (int)$selectedCustomerId === (int)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" id="btnLoadCustomer">Load</button>
                </div>
                <div class="col-md-3">
                    <a href="#" class="btn btn-danger w-100" id="btnAdvanceHistoryPdf" target="_blank">Export PDF History</a>
                </div>
            </form>

            <div class="row g-2 mb-4">
                <div class="col-md-3" id="cashBalanceCard">
                    <div class="balance-card">
                        <small id="cashBalanceLabel">Cash Balance Credit</small>
                        <h5 class="mb-0" id="cashBalance">{{ number_format(abs((float)($balance['cash_balance'] ?? 0)), 2) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="goldBalanceCard">
                    <div class="balance-card">
                        <small id="goldBalanceLabel">Gold Fine Balance Credit</small>
                        <h5 class="mb-0" id="goldBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.gold', 0)), 3) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="silverBalanceCard">
                    <div class="balance-card">
                        <small id="silverBalanceLabel">Silver Fine Balance Credit</small>
                        <h5 class="mb-0" id="silverBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.silver', 0)), 3) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="otherBalanceCard">
                    <div class="balance-card">
                        <small id="otherBalanceLabel">Other Metal Balance Credit</small>
                        <h5 class="mb-0" id="otherBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.other', 0)), 3) }}</h5>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('company.sales.advance.store', $company->slug) }}" class="row g-3 mb-4" id="advanceForm">
                @csrf
                <input type="hidden" name="entry_type" value="receive_amount">
                <input type="hidden" name="advance_items_payload" id="advance_items_payload" value="[]">
                <div class="col-md-2">
                    <label>Date</label>
                    <input type="date" name="entry_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-3">
                    <label>Party</label>
                    <select name="customer_id" id="entry_customer_id" class="form-select searchable-party-select" required>
                        <option value="">Select Party</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (int)$selectedCustomerId === (int)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Payment Mode</label>
                    <select name="payment_mode" class="form-select">
                        <option value="">Select</option>
                        <option value="cash">Cash</option>
                        <option value="online">Online</option>
                        <option value="upi">UPI</option>
                        <option value="bank">Bank</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Amount</label>
                    <input type="number" step="any" min="0" name="amount" id="amount" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-md-6">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-control" maxlength="255" placeholder="Remarks">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-success w-100">Save Entry</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-warning w-100" id="btnOpenConvertModal">
                        Balance Conversion
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered" id="advanceLedgerTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date & Time</th>
                            <th>Party</th>
                            <th>Entry Type</th>
                            <th>Mode</th>
                            <th>Cash In</th>
                            <th>Cash Out</th>
                            <th>Metal Type</th>
                            <th>Metal In</th>
                            <th>Metal Out</th>
                            <th>Rate</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="advanceLedgerBody">
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center">Select party and click Load</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-4 mb-2">
                <div style="min-width: 280px;">
                    <input type="text" id="advance_item_name_search" class="form-control" placeholder="Search Item Name">
                </div>
            </div>

            <div class="table-responsive advance-sale-grid-wrap">
                <table class="table table-bordered" id="advanceSaleTable">
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
                            <th>Apply</th>
                            <th>Metal Amt</th>
                            <th>Labour Rate</th>
                            <th>Apply</th>
                            <th>Labour Amt</th>
                            <th>Other Amt</th>
                            <th>Total Amt</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="advanceSaleBody"></tbody>
                    <tfoot>
                        <tr>
                            <th class="text-end">Totals</th>
                            <th><span id="advanceTotalGrossWt">0.000</span></th>
                            <th><span id="advanceTotalOtherWt">0.000</span></th>
                            <th><span id="advanceTotalNetWt">0.000</span></th>
                            <th colspan="3"></th>
                            <th><span id="advanceTotalFineWt">0.000</span></th>
                            <th></th>
                            <th></th>
                            <th><span id="advanceTotalMetalAmt">0.00</span></th>
                            <th></th>
                            <th></th>
                            <th><span id="advanceTotalLabourAmt">0.00</span></th>
                            <th><span id="advanceTotalOtherAmt">0.00</span></th>
                            <th>Rs <span id="advanceGrandTotal">0.00</span></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <form method="POST" action="{{ route('company.sales.advance.items.store', $company->slug) }}" id="advanceItemsForm" class="d-none">
                @csrf
                <input type="hidden" name="entry_date" id="advance_items_entry_date">
                <input type="hidden" name="customer_id" id="advance_items_customer_id">
                <input type="hidden" name="remarks" id="advance_items_remarks">
                <input type="hidden" name="advance_items_payload" id="advance_items_payload_only" value="[]">
            </form>

            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-success" id="btnSaveAdvanceItems">
                    Save Items
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Balance Conversion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('company.sales.advance.store', $company->slug) }}" id="convertForm">
                @csrf
                <input type="hidden" name="entry_type" id="convert_entry_type" value="convert_to_metal">
                <input type="hidden" name="customer_id" id="convert_customer_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label>Date</label>
                            <input type="date" name="entry_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label>Conversion Type</label>
                            <select id="convert_type" class="form-select" required>
                                <option value="convert_to_metal">Rupees To Metal</option>
                                <option value="convert_to_rupees">Metal To Rupees</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Metal Type</label>
                            <select name="metal_type" id="convert_metal_type" class="form-select" required>
                                <option value="">Select</option>
                                <option value="gold">Gold</option>
                                <option value="silver">Silver</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Metal Rate</label>
                            <input type="number" step="any" min="0.01" name="rate" id="convert_rate" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label id="convert_amount_label">Amount From Advance</label>
                            <input type="number" step="any" min="0.01" name="amount" id="convert_amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label id="convert_preview_label">Fine Weight (Auto)</label>
                            <input type="text" id="convert_fine_preview" class="form-control" value="0.000" readonly>
                        </div>
                        <div class="col-md-6">
                            <label>Remarks</label>
                            <input type="text" name="remarks" id="convert_remarks" class="form-control" maxlength="255" placeholder="Remarks">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Convert & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #customerLoadForm .btn {
        min-height: 44px;
    }

    .balance-card {
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 76px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: rgba(255, 255, 255, 0.02);
    }

    .balance-card small {
        opacity: .95;
        margin-bottom: 4px;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: .2px;
    }

    .balance-card h5 {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        color: #ffffff;
        letter-spacing: .3px;
    }

    #advanceLedgerTable th {
        white-space: nowrap;
    }

    #advanceLedgerTable td {
        vertical-align: middle;
    }

    .advance-sale-grid-wrap {
        height: 400px;
        overflow: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
    }

    #advanceSaleTable {
        min-width: 1900px;
        margin-bottom: 0;
    }

    #advanceSaleTable th {
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
        background: #2b2f4a;
        color: #ffffff;
    }

    #advanceSaleTable th:first-child,
    #advanceSaleTable td:first-child {
        min-width: 300px;
        position: sticky;
        left: 0;
        z-index: 4;
        background: #262a44;
    }

    #advanceSaleTable thead th:first-child {
        z-index: 7;
    }

    #advanceSaleTable input.form-control {
        min-width: 120px;
        width: 100%;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .advance-grid-label-wrap {
        min-width: 280px;
        width: 100%;
    }

    .advance-grid-label-select {
        min-width: 280px;
        width: 100%;
    }

    #advanceSaleTable .select2-container {
        min-width: 280px;
        width: 100% !important;
    }

    .advance-grid-placeholder-cell {
        color: rgba(255, 255, 255, 0.6);
        font-weight: 700;
    }

    .searchable-party-select {
        width: 100%;
    }

    .select2-container--bootstrap4 .select2-selection--single,
    .select2-container--default .select2-selection--single {
        min-height: 40px;
        background-color: #2e2d52;
        border-color: rgba(255, 255, 255, 0.12);
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered,
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #c9c8dc;
        line-height: 40px;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow,
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .select2-container--default .select2-dropdown,
    .select2-container--bootstrap4 .select2-dropdown {
        background-color: #302f54;
        border-color: rgba(150, 170, 255, 0.45);
        color: #ffffff;
    }

    .select2-container--default .select2-search--dropdown .select2-search__field,
    .select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
        min-height: 34px;
        background: #292d49;
        color: #ffffff;
        border: 1px solid rgba(150, 170, 255, 0.5);
    }

    .select2-results__option {
        color: #ffffff;
        padding: 8px 12px;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected],
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background: #0d6efd;
        color: #ffffff;
    }

    .select2-results__options {
        max-height: 400px !important;
    }

    #convertModal .modal-dialog {
        max-width: 1100px;
    }

    #convertModal .modal-content {
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        overflow: hidden;
    }

    #convertModal .modal-header {
        padding: 14px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    }

    #convertModal .modal-title {
        font-weight: 700;
        letter-spacing: .2px;
    }

    #convertModal .modal-body {
        padding: 20px;
    }

    #convertModal .modal-footer {
        padding: 14px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        gap: 8px;
    }

    #convertModal label {
        display: inline-block;
        margin-bottom: 6px;
        font-weight: 600;
    }

    #convertModal .form-control,
    #convertModal .form-select {
        min-height: 46px;
        border: 1px solid rgba(255, 255, 255, 0.28);
    }

    #convertModal .form-select {
        appearance: auto;
        -webkit-appearance: auto;
        -moz-appearance: auto;
        padding-right: 2.25rem;
    }

    #convertModal .form-control:focus,
    #convertModal .form-select:focus {
        border-color: #4f8cff;
        box-shadow: 0 0 0 0.15rem rgba(79, 140, 255, 0.25);
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    let availableCashRaw = 0;
    let availableMetal = { gold: 0, silver: 0, other: 0 };
    let advanceLedgerDt = null;
    const selectedAdvanceItems = {};
    const advanceItemSearchUrl = "{{ route('company.items.search', $company->slug) }}";

    const toNum = (value, fallback = 0) => {
        const n = parseFloat(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(n) ? n : fallback;
    };

    const nfix = (value, decimals) => {
        const n = toNum(value);
        const fixed = Math.abs(n) < 1e-9 ? 0 : n;
        return fixed.toFixed(decimals);
    };

    const esc = (value) => $('<div>').text(value ?? '').html();

    function initPartySelects() {
        if (!$.fn.select2) return;
        $('.searchable-party-select').each(function () {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select Party',
                allowClear: true
            });
        });
    }

    function advanceSearchParams(query) {
        return {
            search: query || '',
            limit: 1000,
            customer_id: $('#customer_id').val() || $('#entry_customer_id').val() || ''
        };
    }

    function normalizeAdvanceSearchResponse(resp) {
        if (Array.isArray(resp)) return resp;
        if (resp && Array.isArray(resp.data)) return resp.data;
        if (resp && typeof resp === 'object') return Object.values(resp);
        return [];
    }

    function normalizeMetalType(value) {
        const s = String(value || '').trim().toLowerCase();
        if (s === 'gold' || s.includes('gold')) return 'gold';
        if (s === 'silver' || s.includes('silver')) return 'silver';
        return 'other';
    }

    function normalizeAdvanceItem(row) {
        const gross = toNum(row.gross_weight ?? row.gross ?? 0);
        const otherWeight = toNum(row.other_weight ?? row.other ?? 0);
        const net = toNum(row.net_weight ?? (gross - otherWeight));
        const purity = toNum(row.purity ?? 0);
        const wastePercent = toNum(row.waste_percent ?? 0);
        const netPurity = toNum(row.net_purity ?? (purity + wastePercent));
        const fineWeight = toNum(row.fine_weight ?? (net * netPurity / 100));
        const metalRate = toNum(row.metal_rate ?? 0);
        const metalAmount = toNum(row.metal_amount ?? (fineWeight * metalRate));
        const labourRate = toNum(row.labour_rate ?? 0);
        const labourAmount = toNum(row.labour_amount ?? (net * labourRate));
        const otherAmount = toNum(row.other_amount ?? row.sale_other ?? 0);

        return {
            row_key: row.row_key || ((row.is_item_only || row.source === 'item') ? 'item_' + row.item_id : 'set_' + (row.itemset_id || row.id)),
            itemset_id: toNum(row.itemset_id ?? row.id),
            item_id: toNum(row.item_id ?? 0),
            name: row.name ?? row.item_name ?? '',
            code: row.code ?? row.qr_code ?? '',
            huid: row.huid ?? row.HUID ?? '',
            metal_type: normalizeMetalType(row.metal_type ?? row.metal ?? ''),
            gross_weight: gross,
            other_weight: otherWeight,
            net_weight: net,
            purity: purity,
            waste_percent: wastePercent,
            net_purity: netPurity,
            fine_weight: fineWeight,
            metal_rate: metalRate,
            apply_metal: true,
            metal_amount: metalAmount,
            labour_rate: labourRate,
            apply_labour: true,
            labour_amount: labourAmount,
            other_amount: otherAmount,
            total_amount: toNum(row.total_amount ?? (metalAmount + labourAmount + otherAmount)),
            remarks: row.remarks ?? '',
            source: row.source || (row.is_item_only ? 'item' : 'itemset'),
            is_item_only: !!row.is_item_only
        };
    }

    function resolveAdvanceRowKey(row) {
        return String(row.row_key || ((row.is_item_only || row.source === 'item') ? 'item_' + row.item_id : 'set_' + (row.itemset_id || row.id || '')));
    }

    function advanceSuggestionLabel(row) {
        const codeText = row.code || row.huid || row.name || '';
        const typeText = row.source === 'approval' ? 'Approval Label' : (row.is_item_only ? 'Item' : 'Label');
        const noteText = row.is_item_only ? '<br><small>(Item found, Itemset not created)</small>' : '';

        return `
            <strong>${esc(codeText || '-')}</strong>
            <br><small>${esc(row.name || '')} - ${esc(typeText)}</small>${noteText}
        `;
    }

    function ensureAdvanceGridSearchRow() {
        if ($('#advanceSaleSearchRow').length) return;

        $('#advanceSaleBody').append(`
            <tr id="advanceSaleSearchRow">
                <td>
                    <div class="advance-grid-label-wrap">
                        <select class="form-select advance-grid-label-select">
                            <option value=""></option>
                        </select>
                    </div>
                </td>
                <td colspan="17" class="advance-grid-placeholder-cell">Select label or item from Label column</td>
            </tr>
        `);

        initAdvanceGridLabelSelect($('#advanceSaleSearchRow .advance-grid-label-select'));
    }

    function initAdvanceGridLabelSelect($select) {
        if (!$select.length || !$.fn.select2) return;

        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Search Label / Item',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                delay: 200,
                transport: function(params, success, failure) {
                    const term = params.data && params.data.term ? params.data.term : '';
                    const request = $.get(advanceItemSearchUrl, advanceSearchParams(term));
                    request.then(success);
                    request.fail(failure);
                    return request;
                },
                processResults: function(resp) {
                    const results = normalizeAdvanceSearchResponse(resp)
                        .map(normalizeAdvanceItem)
                        .filter(function(row) {
                            const key = resolveAdvanceRowKey(row);
                            return key && !selectedAdvanceItems[key];
                        })
                        .map(function(row) {
                            const key = resolveAdvanceRowKey(row);
                            const code = row.code || row.huid || row.name || '';
                            return {
                                id: key,
                                text: `${code}${row.name ? ' - ' + row.name : ''}`,
                                row: row
                            };
                        });

                    return { results: results };
                }
            },
            templateResult: function(data) {
                if (!data.id) return data.text;
                return $(advanceSuggestionLabel(data.row || data));
            },
            templateSelection: function(data) {
                return data.text || 'Search Label / Item';
            },
            escapeMarkup: function(markup) {
                return markup;
            }
        }).on('select2:select', function(e) {
            const row = e.params.data.row || {};
            $('#advanceSaleSearchRow').remove();
            appendAdvanceSaleRow(row);
            ensureAdvanceGridSearchRow();
        });
    }

    function recalcAdvanceRow(rowKey) {
        const row = selectedAdvanceItems[rowKey];
        if (!row) return;

        row.gross_weight = toNum($(`.advance-gross[data-id="${rowKey}"]`).val());
        row.other_weight = toNum($(`.advance-other-weight[data-id="${rowKey}"]`).val());
        row.net_weight = Math.max(0, row.gross_weight - row.other_weight);
        row.purity = toNum($(`.advance-purity[data-id="${rowKey}"]`).val());
        row.waste_percent = toNum($(`.advance-waste-percent[data-id="${rowKey}"]`).val());
        row.net_purity = row.purity + row.waste_percent;
        row.fine_weight = row.net_weight * row.net_purity / 100;
        row.metal_rate = toNum($(`.advance-metal-rate[data-id="${rowKey}"]`).val());
        row.apply_metal = $(`.advance-apply-metal[data-id="${rowKey}"]`).is(':checked');
        row.metal_amount = row.apply_metal ? row.fine_weight * row.metal_rate : 0;
        row.labour_rate = toNum($(`.advance-labour-rate[data-id="${rowKey}"]`).val());
        row.apply_labour = $(`.advance-apply-labour[data-id="${rowKey}"]`).is(':checked');
        row.labour_amount = row.apply_labour ? row.net_weight * row.labour_rate : 0;
        row.other_amount = toNum($(`.advance-other-amount[data-id="${rowKey}"]`).val());
        row.total_amount = row.metal_amount + row.labour_amount + row.other_amount;

        $(`#advance_net_${rowKey}`).val(nfix(row.net_weight, 3));
        $(`#advance_net_purity_${rowKey}`).val(nfix(row.net_purity, 3));
        $(`#advance_fine_${rowKey}`).val(nfix(row.fine_weight, 3));
        $(`#advance_metal_amt_${rowKey}`).val(nfix(row.metal_amount, 2));
        $(`#advance_labour_amt_${rowKey}`).val(nfix(row.labour_amount, 2));
        $(`#advance_total_amt_${rowKey}`).val(nfix(row.total_amount, 2));
        updateAdvanceGridTotals();
    }

    function updateAdvanceGridTotals() {
        let gross = 0;
        let other = 0;
        let net = 0;
        let fine = 0;
        let metal = 0;
        let labour = 0;
        let otherAmount = 0;
        let total = 0;

        Object.values(selectedAdvanceItems).forEach(function(row) {
            gross += toNum(row.gross_weight);
            other += toNum(row.other_weight);
            net += toNum(row.net_weight);
            fine += toNum(row.fine_weight);
            metal += toNum(row.metal_amount);
            labour += toNum(row.labour_amount);
            otherAmount += toNum(row.other_amount);
            total += toNum(row.total_amount);
        });

        $('#advanceTotalGrossWt').text(nfix(gross, 3));
        $('#advanceTotalOtherWt').text(nfix(other, 3));
        $('#advanceTotalNetWt').text(nfix(net, 3));
        $('#advanceTotalFineWt').text(nfix(fine, 3));
        $('#advanceTotalMetalAmt').text(nfix(metal, 2));
        $('#advanceTotalLabourAmt').text(nfix(labour, 2));
        $('#advanceTotalOtherAmt').text(nfix(otherAmount, 2));
        $('#advanceGrandTotal').text(nfix(total, 2));
    }

    function appendAdvanceSaleRow(row) {
        const normalized = normalizeAdvanceItem(row);
        const rowKey = resolveAdvanceRowKey(normalized);
        if (!rowKey || selectedAdvanceItems[rowKey]) return;

        selectedAdvanceItems[rowKey] = normalized;
        const tr = `
            <tr id="advance_row_${rowKey}">
                <td>
                    <strong>${esc(normalized.huid || '')}</strong><br>
                    <small>${esc(normalized.code || '')}</small><br>
                    <small>${esc(normalized.name || '')}</small>
                </td>
                <td><input type="number" step="0.001" class="form-control advance-gross" data-id="${rowKey}" value="${nfix(normalized.gross_weight, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control advance-other-weight" data-id="${rowKey}" value="${nfix(normalized.other_weight, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control" id="advance_net_${rowKey}" readonly value="${nfix(normalized.net_weight, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control advance-purity" data-id="${rowKey}" value="${nfix(normalized.purity, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control advance-waste-percent" data-id="${rowKey}" value="${nfix(normalized.waste_percent, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control" id="advance_net_purity_${rowKey}" readonly value="${nfix(normalized.net_purity, 3)}"></td>
                <td><input type="number" step="0.001" class="form-control" id="advance_fine_${rowKey}" readonly value="${nfix(normalized.fine_weight, 3)}"></td>
                <td><input type="number" step="0.01" class="form-control advance-metal-rate" data-id="${rowKey}" value="${nfix(normalized.metal_rate, 2)}"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input advance-apply-metal" data-id="${rowKey}" checked></td>
                <td><input type="number" step="0.01" class="form-control" id="advance_metal_amt_${rowKey}" readonly value="${nfix(normalized.metal_amount, 2)}"></td>
                <td><input type="number" step="0.01" class="form-control advance-labour-rate" data-id="${rowKey}" value="${nfix(normalized.labour_rate, 2)}"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input advance-apply-labour" data-id="${rowKey}" checked></td>
                <td><input type="number" step="0.01" class="form-control" id="advance_labour_amt_${rowKey}" readonly value="${nfix(normalized.labour_amount, 2)}"></td>
                <td><input type="number" step="0.01" class="form-control advance-other-amount" data-id="${rowKey}" value="${nfix(normalized.other_amount, 2)}"></td>
                <td><input type="number" step="0.01" class="form-control" id="advance_total_amt_${rowKey}" readonly value="${nfix(normalized.total_amount, 2)}"></td>
                <td><input type="text" class="form-control advance-remarks" data-id="${rowKey}" value="${esc(normalized.remarks)}"></td>
                <td><button type="button" class="btn btn-danger advance-remove-row" data-id="${rowKey}">X</button></td>
            </tr>
        `;

        if ($('#advanceSaleSearchRow').length) {
            $('#advanceSaleSearchRow').before(tr);
        } else {
            $('#advanceSaleBody').append(tr);
        }
        recalcAdvanceRow(rowKey);
    }

    $(document).on('input change', '.advance-gross, .advance-other-weight, .advance-purity, .advance-waste-percent, .advance-metal-rate, .advance-labour-rate, .advance-other-amount, .advance-apply-metal, .advance-apply-labour', function() {
        recalcAdvanceRow($(this).data('id'));
    });

    $(document).on('input', '.advance-remarks', function() {
        const row = selectedAdvanceItems[$(this).data('id')];
        if (row) row.remarks = $(this).val();
    });

    $(document).on('click', '.advance-remove-row', function() {
        const rowKey = String($(this).data('id'));
        delete selectedAdvanceItems[rowKey];
        $('#advance_row_' + rowKey).remove();
        ensureAdvanceGridSearchRow();
        updateAdvanceGridTotals();
    });

    $('#advance_item_name_search').on('input', function() {
        const term = String($(this).val() || '').trim().toLowerCase();
        $('#advanceSaleBody tr').not('#advanceSaleSearchRow').each(function() {
            $(this).toggle(!term || $(this).text().toLowerCase().includes(term));
        });
    });

    function syncAdvanceItemsPayload(targetSelector) {
        Object.keys(selectedAdvanceItems).forEach(recalcAdvanceRow);
        const rows = Object.values(selectedAdvanceItems);
        $(targetSelector).val(JSON.stringify(rows));
        return rows.length;
    }

    $('#advanceForm').on('submit', function() {
        syncAdvanceItemsPayload('#advance_items_payload');
    });

    $('#btnSaveAdvanceItems').on('click', function() {
        const rowCount = syncAdvanceItemsPayload('#advance_items_payload_only');
        if (!rowCount) {
            alert('Please select at least one item before saving.');
            return;
        }

        const customerId = $('#entry_customer_id').val() || $('#customer_id').val();
        if (!customerId) {
            alert('Please select party before saving item details.');
            return;
        }

        $('#advance_items_entry_date').val($('#advanceForm input[name="entry_date"]').val());
        $('#advance_items_customer_id').val(customerId);
        $('#advance_items_remarks').val($('#advanceForm input[name="remarks"]').val());
        $('#advanceItemsForm').trigger('submit');
    });

    function setBal(labelId, valueId, labelText, raw, decimals) {
        const type = raw >= 0 ? 'Credit' : 'Debit';
        $(labelId).text(labelText + ' ' + type);
        $(valueId).text(Math.abs(parseFloat(raw || 0)).toFixed(decimals));
    }

    let currentCustomerPdfKey = '';

    function updateHistoryPdfLink(customerKey) {
        const base = "{{ route('company.sales.advance.pdf', $company->slug) }}";
        if (!customerKey) {
            $('#btnAdvanceHistoryPdf').attr('href', '#');
            return;
        }
        $('#btnAdvanceHistoryPdf').attr('href', base + '?customer_key=' + encodeURIComponent(customerKey));
    }

    function toggleMetalCards(goldRaw, silverRaw, otherRaw) {
        const hasGold = Math.abs(parseFloat(goldRaw || 0)) > 0.000001;
        const hasSilver = Math.abs(parseFloat(silverRaw || 0)) > 0.000001;
        const hasOther = Math.abs(parseFloat(otherRaw || 0)) > 0.000001;
        $('#goldBalanceCard').toggle(hasGold);
        $('#silverBalanceCard').toggle(hasSilver);
        $('#otherBalanceCard').toggle(hasOther);
    }

    function initAdvanceTable() {
        if (!$.fn.DataTable) return;
        if (advanceLedgerDt) {
            advanceLedgerDt.destroy();
            advanceLedgerDt = null;
        }
        advanceLedgerDt = $('#advanceLedgerTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[1, 'desc'], [0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [0] }
            ]
        });
    }

    function setTableMessage(msg) {
        if (advanceLedgerDt) {
            advanceLedgerDt.destroy();
            advanceLedgerDt = null;
        }
        $('#advanceLedgerBody').html(
            '<tr>' +
                '<td></td><td></td><td></td><td></td><td></td><td></td>' +
                '<td class="text-center">' + msg + '</td>' +
                '<td></td><td></td><td></td><td></td><td></td>' +
            '</tr>'
        );
    }

    function loadCustomerLedger(customerId) {
        if (!customerId) {
            currentCustomerPdfKey = '';
            availableCashRaw = 0;
            availableMetal = { gold: 0, silver: 0, other: 0 };
            setBal('#cashBalanceLabel', '#cashBalance', 'Cash Balance', 0, 2);
            setBal('#goldBalanceLabel', '#goldBalance', 'Gold Fine Balance', 0, 3);
            setBal('#silverBalanceLabel', '#silverBalance', 'Silver Fine Balance', 0, 3);
            setBal('#otherBalanceLabel', '#otherBalance', 'Other Metal Balance', 0, 3);
            toggleMetalCards(0, 0, 0);
            setTableMessage('Select party and click Load');
            return;
        }

        setTableMessage('Loading...');
        $.get('{{ route('company.sales.advance.data', $company->slug) }}', { customer_id: customerId })
            .done(function (resp) {
                if (!resp || !resp.success) {
                    setTableMessage('No entries found');
                    return;
                }

                const b = resp.balance || {};
                const m = b.metal_balance || {};
                availableCashRaw = parseFloat(b.cash_balance || 0);
                availableMetal = {
                    gold: parseFloat(m.gold || 0),
                    silver: parseFloat(m.silver || 0),
                    other: parseFloat(m.other || 0)
                };
                setBal('#cashBalanceLabel', '#cashBalance', 'Cash Balance', parseFloat(b.cash_balance || 0), 2);
                  setBal('#goldBalanceLabel', '#goldBalance', 'Gold Fine Balance', parseFloat(m.gold || 0), 3);
                  setBal('#silverBalanceLabel', '#silverBalance', 'Silver Fine Balance', parseFloat(m.silver || 0), 3);
                  setBal('#otherBalanceLabel', '#otherBalance', 'Other Metal Balance', parseFloat(m.other || 0), 3);
                  toggleMetalCards(parseFloat(m.gold || 0), parseFloat(m.silver || 0), parseFloat(m.other || 0));
                  currentCustomerPdfKey = String(resp.customer_key || '');
                  updateHistoryPdfLink(currentCustomerPdfKey);
                  $('#advanceLedgerBody').html(resp.rows_html || (
                      '<tr>' +
                          '<td></td><td></td><td></td><td></td><td></td><td></td>' +
                        '<td class="text-center">No entries found</td>' +
                        '<td></td><td></td><td></td><td></td><td></td>' +
                    '</tr>'
                ));
                $('#entry_customer_id').val(String(customerId)).trigger('change.select2');
                $('#convert_customer_id').val(String(customerId));
                if (parseInt(resp.row_count || 0, 10) > 0) {
                    initAdvanceTable();
                } else {
                    setTableMessage('No entries found');
                }
            })
            .fail(function () {
                setTableMessage('Something went wrong. Please try again later.');
            });
    }

    $('#btnLoadCustomer').on('click', function () {
        const customerId = $('#customer_id').val();
        $('#entry_customer_id').val(customerId).trigger('change.select2');
        currentCustomerPdfKey = '';
        updateHistoryPdfLink('');
        loadCustomerLedger(customerId);
    });

    $('#customer_id').on('change', function () {
        const cid = $(this).val();
        $('#entry_customer_id').val(cid).trigger('change.select2');
        $('#convert_customer_id').val(cid);
        currentCustomerPdfKey = '';
        updateHistoryPdfLink('');
    });

    $('#btnAdvanceHistoryPdf').on('click', function (e) {
        const cid = $('#customer_id').val() || $('#entry_customer_id').val();
        if (!cid) {
            e.preventDefault();
            alert('Please select party and click Load first.');
            return;
        }
        if (!currentCustomerPdfKey) {
            e.preventDefault();
            alert('Please click Load first.');
            return;
        }
        updateHistoryPdfLink(currentCustomerPdfKey);
    });

    $('#btnOpenConvertModal').on('click', function () {
        const cid = $('#customer_id').val() || $('#entry_customer_id').val();
        if (!cid) {
            alert('Please select party and click Load first.');
            return;
        }
        $('#convert_customer_id').val(cid);
        updateAutoConvertRemark();
        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('convertModal'));
            modal.show();
        } else {
            $('#convertModal').modal('show');
        }
    });

    function updateFinePreview() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const amt = parseFloat($('#convert_amount').val() || 0);
        const rate = parseFloat($('#convert_rate').val() || 0);
        if (mode === 'convert_to_rupees') {
            const rupees = (amt > 0 && rate > 0) ? (amt * rate) : 0;
            $('#convert_fine_preview').val(rupees.toFixed(2));
        } else {
            const fine = (amt > 0 && rate > 0) ? (amt / rate) : 0;
            $('#convert_fine_preview').val(fine.toFixed(3));
        }
    }

    function syncConvertUiMode() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        $('#convert_entry_type').val(mode);
        if (mode === 'convert_to_rupees') {
            $('#convert_amount_label').text('Fine Weight From Metal');
            $('#convert_preview_label').text('Rupees (Auto)');
            $('#convert_amount').attr('step', '0.001');
        } else {
            $('#convert_amount_label').text('Amount From Advance');
            $('#convert_preview_label').text('Fine Weight (Auto)');
            $('#convert_amount').attr('step', '0.01');
        }
        updateAutoConvertRemark();
        updateFinePreview();
    }

    function updateAutoConvertRemark() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const metal = String($('#convert_metal_type').val() || '').trim().toLowerCase();
        if (!metal) {
            $('#convert_remarks').val('');
            return;
        }
        const metalTitle = metal.charAt(0).toUpperCase() + metal.slice(1);
        const text = mode === 'convert_to_rupees'
            ? `${metalTitle} To Rupees Convert`
            : `Rupees To ${metalTitle} Convert`;
        $('#convert_remarks').val(text);
    }

    $('#convert_amount, #convert_rate').on('input', updateFinePreview);
    $('#convert_type').on('change', syncConvertUiMode);
    $('#convert_metal_type').on('change', updateAutoConvertRemark);

    $('#convertForm').on('submit', function (e) {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const metalType = String($('#convert_metal_type').val() || '');
        const amt = parseFloat($('#convert_amount').val() || 0);
        if (mode === 'convert_to_rupees') {
            const availMetal = parseFloat((availableMetal[metalType] || 0));
            if (amt > availMetal) {
                e.preventDefault();
                alert('Fine weight exceeds available metal balance.');
                return;
            }
        } else {
            if (amt > availableCashRaw) {
                e.preventDefault();
                alert('Amount exceeds available cash advance balance.');
                return;
            }
        }
    });

    toggleMetalCards(
        parseFloat("{{ (float)data_get($balance, 'metal_balance.gold', 0) }}"),
        parseFloat("{{ (float)data_get($balance, 'metal_balance.silver', 0) }}"),
        parseFloat("{{ (float)data_get($balance, 'metal_balance.other', 0) }}")
    );

    const initialCustomerId = '{{ (int)$selectedCustomerId }}';
    initPartySelects();
    if (initialCustomerId && initialCustomerId !== '0') {
        $('#customer_id').val(initialCustomerId).trigger('change.select2');
        $('#entry_customer_id').val(initialCustomerId).trigger('change.select2');
        updateHistoryPdfLink('');
        loadCustomerLedger(initialCustomerId);
    } else {
        updateHistoryPdfLink('');
    }
    ensureAdvanceGridSearchRow();
    syncConvertUiMode();
});
</script>
@endpush
