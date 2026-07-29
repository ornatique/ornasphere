@extends('company_layout.admin')

@section('content')
@php
    $totalIssueNet = (float) ($row->net_wt_sum ?? 0);
    $receiveOptionsById = collect($receiveItemOptions)->keyBy('id');
    $receiveRows = old('items');
    if ($receiveRows === null) {
        $receiveRows = $receive->items
            ->filter(fn($item) => $item->item_id || $item->jobworkIssueItem)
            ->groupBy(fn($item) => $item->item_id ?: $item->jobworkIssueItem->item_id)
            ->map(function ($items) {
                $first = $items->first();

            return [
                'item_id' => $first->item_id ?: $first->jobworkIssueItem?->item_id,
                'receive_gross_wt' => $items->sum('receive_gross_wt'),
                'other_wt' => $items->sum('other_wt'),
                'other_amt' => $items->sum('other_amt'),
                'receive_net_wt' => $items->sum('receive_net_wt'),
                'receive_fine_wt' => $items->sum('receive_fine_wt'),
                'receive_qty_pcs' => $items->sum('receive_qty_pcs'),
                'remarks' => $items->pluck('remarks')->filter()->implode(', '),
            ];
        })->values()->toArray();
    }
    if (empty($receiveRows)) {
        $receiveRows = [[
            'item_id' => '',
            'receive_gross_wt' => 0,
            'other_wt' => 0,
            'other_amt' => 0,
            'receive_net_wt' => 0,
            'receive_fine_wt' => 0,
            'receive_qty_pcs' => 0,
            'remarks' => '',
        ]];
    }
@endphp
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Jobwork Receive</h4>
                <div>{{ $row->voucher_no }} | {{ optional($row->jobwork_date)->format('d-m-Y') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('company.jobwork-receive.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)]) }}" class="btn btn-success">PDF</a>
                <a href="{{ route('company.jobwork-receive.index', $company->slug) }}" class="btn btn-info">Back</a>
            </div>
        </div>

        <form method="POST" action="{{ route('company.jobwork-receive.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)]) }}">
            @csrf
            @method('PUT')

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

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label mb-1">Receive Date</label>
                        <input type="date" name="receive_date" class="form-control" value="{{ old('receive_date', optional($receive->receive_date)->toDateString() ?: now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3"><strong>Jobworker:</strong><br>{{ $row->jobWorker?->name ?? '-' }}</div>
                    <div class="col-md-3"><strong>Production Step:</strong><br>{{ $row->productionStep?->name ?? '-' }}</div>
                    <div class="col-md-3"><strong>Created At:</strong><br>{{ optional($row->created_at)->format('d-m-Y h:i A') }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Issue Net Wt:</strong><br>{{ number_format($totalIssueNet, 3, '.', '') }}</div>
                    <div class="col-md-3"><strong>Receive Net Wt:</strong><br><span id="topReceiveNet">0.000</span></div>
                    <div class="col-md-3"><strong>Pending Net Wt:</strong><br><span id="topPendingNet">0.000</span></div>
                    <div class="col-md-3"><strong>Total Amt:</strong><br>{{ number_format((float) ($row->total_amt_sum ?? 0), 2, '.', '') }}</div>
                </div>

                <h5 class="section-title">Issue Jobwork List</h5>
                <div class="table-responsive mb-4 issue-list-scroll">
                    <table class="table table-bordered jobwork-issue-summary-table">
                        <thead>
                            <tr>
                                <th>Sr. No</th>
                                <th>Item</th>
                                <th>Issue Net Wt</th>
                                <th>Issue Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($row->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->item?->item_name ?? '-' }}</td>
                                    <td>{{ number_format((float) ($item->net_wt ?? 0), 3, '.', '') }}</td>
                                    <td>{{ (int) ($item->qty_pcs ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th>{{ number_format($totalIssueNet, 3, '.', '') }}</th>
                                <th>{{ (int) $row->items->sum('qty_pcs') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <h5 class="section-title">Jobwork Receive</h5>
                <div class="table-responsive receive-list-scroll">
                    <table class="table table-bordered jobwork-receive-table" id="jobworkReceiveTable">
                        <thead>
                            <tr>
                                <th>Sr. No</th>
                                <th>Item</th>
                                <th>Issue Net Wt</th>
                                <th>Purity</th>
                                <th>Gross Wt</th>
                                <th>Other</th>
                                <th>Net Wt</th>
                                <th>Fine Wt</th>
                                <th>Qty Pcs</th>
                                <th>Pending Net Wt</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receiveRows as $index => $receiveRow)
                                @php
                                    $selectedItemId = $receiveRow['item_id'] ?? '';
                                    $selectedOption = $receiveOptionsById->get((int) $selectedItemId);
                                    $issueNet = (float) ($selectedOption['issue_net_wt'] ?? 0);
                                    $purity = (float) ($selectedOption['purity'] ?? 0);
                                    $receiveGross = (float) ($receiveRow['receive_gross_wt'] ?? 0);
                                    $otherWt = (float) ($receiveRow['other_wt'] ?? 0);
                                    $otherAmt = (float) ($receiveRow['other_amt'] ?? 0);
                                    $receiveNet = max(0, $receiveGross - $otherWt);
                                    $receiveFine = (float) ($receiveRow['receive_fine_wt'] ?? ($receiveNet * $purity / 100));
                                    $pendingNet = $issueNet - $receiveNet;
                                @endphp
                                <tr class="receive-row">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <select name="items[{{ $index }}][item_id]" class="form-select receive-item-select">
                                            <option value="">Select Item</option>
                                            @foreach($receiveItemOptions as $option)
                                                <option value="{{ $option['id'] }}"
                                                    data-issue-net="{{ number_format((float) $option['issue_net_wt'], 3, '.', '') }}"
                                                    data-purity="{{ number_format((float) $option['purity'], 3, '.', '') }}"
                                                    data-issue-qty="{{ (int) $option['issue_qty'] }}"
                                                    {{ (string) $selectedItemId === (string) $option['id'] ? 'selected' : '' }}>
                                                    {{ $option['item_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="issue-net-wt" data-value="{{ number_format($issueNet, 3, '.', '') }}">{{ number_format($issueNet, 3, '.', '') }}</td>
                                    <td><input type="number" step="0.001" class="form-control receive-purity" value="{{ number_format($purity, 3, '.', '') }}" readonly></td>
                                    <td>
                                        <input type="number" step="0.001" min="0" name="items[{{ $index }}][receive_gross_wt]" class="form-control receive-gross-wt" value="{{ number_format($receiveGross, 3, '.', '') }}">
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][other_wt]" class="other-wt" value="{{ number_format((float) $otherWt, 3, '.', '') }}">
                                        <input type="hidden" name="items[{{ $index }}][other_amt]" class="other-amt" value="{{ number_format((float) $otherAmt, 2, '.', '') }}">
                                        <button type="button" class="btn btn-warning receive-other-btn">Wt | Amt</button>
                                        <div class="receive-other-summary">Wt: <span class="summary-other-wt">{{ number_format((float) $otherWt, 3, '.', '') }}</span> | Amt: <span class="summary-other-amt">{{ number_format((float) $otherAmt, 2, '.', '') }}</span></div>
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][receive_net_wt]" class="receive-net-wt" value="{{ number_format($receiveNet, 3, '.', '') }}">
                                        <input type="text" class="form-control receive-net-view" value="{{ number_format($receiveNet, 3, '.', '') }}" readonly>
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][receive_fine_wt]" class="receive-fine-wt" value="{{ number_format($receiveFine, 3, '.', '') }}">
                                        <input type="text" class="form-control receive-fine-view" value="{{ number_format($receiveFine, 3, '.', '') }}" readonly>
                                    </td>
                                    <td>
                                        <input type="number" step="1" min="0" name="items[{{ $index }}][receive_qty_pcs]" class="form-control receive-qty-pcs" value="{{ (int) ($receiveRow['receive_qty_pcs'] ?? 0) }}">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control pending-net-wt" value="{{ number_format($pendingNet, 3, '.', '') }}" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $receiveRow['remarks'] ?? '' }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th>{{ number_format($totalIssueNet, 3, '.', '') }}</th>
                                <th></th>
                                <th id="totalReceiveGross">0.000</th>
                                <th></th>
                                <th id="totalReceiveNet">0.000</th>
                                <th id="totalReceiveFine">0.000</th>
                                <th id="totalReceiveQty">0</th>
                                <th id="totalPendingNet">0.000</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $receive->remarks) }}</textarea>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('company.jobwork-receive.index', $company->slug) }}" class="btn btn-info">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="receiveOtherModal" tabindex="-1" role="dialog" aria-labelledby="receiveOtherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiveOtherModalLabel">Other Weight</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Other Wt</label>
                <input type="number" step="0.001" min="0" class="form-control" id="receiveOtherWeight" value="0.000">
                <label class="form-label mt-2">Other Amt</label>
                <input type="number" step="0.01" min="0" class="form-control" id="receiveOtherAmount" value="0.00">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="applyReceiveOther">Apply</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .section-title {
        margin: 18px 0 10px;
        color: #fff;
        font-size: 16px;
        font-weight: 700;
    }

    .jobwork-issue-summary-table {
        min-width: 760px;
        margin-bottom: 0;
    }

    .jobwork-receive-table {
        min-width: 1500px;
        margin-bottom: 0;
    }

    .issue-list-scroll {
        max-width: 860px;
        max-height: 520px;
        overflow: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .receive-list-scroll {
        max-height: 560px;
        overflow: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .issue-list-scroll .jobwork-issue-summary-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263b;
    }

    .issue-list-scroll .jobwork-issue-summary-table tfoot th {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #25263b;
    }

    .receive-list-scroll .jobwork-receive-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263b;
    }

    .receive-list-scroll .jobwork-receive-table tfoot th {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #25263b;
    }

    .jobwork-issue-summary-table th,
    .jobwork-issue-summary-table td,
    .jobwork-receive-table th,
    .jobwork-receive-table td {
        vertical-align: middle;
        white-space: nowrap;
    }

    .jobwork-issue-summary-table th:first-child,
    .jobwork-issue-summary-table td:first-child {
        width: 90px;
        text-align: center;
    }

    .jobwork-receive-table th:first-child,
    .jobwork-receive-table td:first-child {
        width: 90px;
        text-align: center;
    }

    .jobwork-receive-table .receive-item-select {
        min-width: 220px;
    }

    .jobwork-receive-table .select2-container {
        width: 100% !important;
        min-width: 220px;
    }

    .jobwork-receive-table .form-control {
        min-width: 150px;
    }

    .receive-other-btn {
        min-width: 88px;
        padding: 8px 10px;
    }

    .receive-other-summary {
        margin-top: 4px;
        color: #cfd3e8;
        font-size: 12px;
    }

    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        opacity: 1;
    }

</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let activeReceiveRow = null;
    let receiveRowIndex = document.querySelectorAll('.receive-row').length;
    const issueItemOptions = @json($receiveItemOptions);
    const totalIssueNet = {{ number_format($totalIssueNet, 3, '.', '') }};
    const receiveOtherModalEl = document.getElementById('receiveOtherModal');
    const receiveOtherModal = window.bootstrap && bootstrap.Modal
        ? bootstrap.Modal.getOrCreateInstance(receiveOtherModalEl)
        : null;

    function numberValue(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function fixed(value, digits = 3) {
        return numberValue(value).toFixed(digits);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function optionHtml(selectedId = '') {
        let html = '<option value="">Select Item</option>';
        issueItemOptions.forEach(function (option) {
            const selected = String(selectedId) === String(option.id) ? ' selected' : '';
            html += `<option value="${option.id}" data-issue-net="${fixed(option.issue_net_wt)}" data-purity="${fixed(option.purity)}" data-issue-qty="${parseInt(option.issue_qty || 0, 10)}"${selected}>${escapeHtml(option.item_name)}</option>`;
        });
        return html;
    }

    function initReceiveItemSelect(context = document) {
        if (!window.jQuery || !$.fn.select2) {
            return;
        }

        $(context).find('.receive-item-select').each(function () {
            const $select = $(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select Item',
                allowClear: true
            });
        });
    }

    function selectedOption(row) {
        return row.querySelector('.receive-item-select')?.selectedOptions?.[0] || null;
    }

    function updateIssueData(row) {
        const option = selectedOption(row);
        const issueNet = option && option.value ? numberValue(option.dataset.issueNet) : 0;
        const purity = option && option.value ? numberValue(option.dataset.purity) : 0;

        row.querySelector('.issue-net-wt').dataset.value = fixed(issueNet);
        row.querySelector('.issue-net-wt').textContent = fixed(issueNet);
        row.querySelector('.receive-purity').value = fixed(purity);
    }

    function recalculateRow(row) {
        updateIssueData(row);
        const issueNet = numberValue(row.querySelector('.issue-net-wt')?.dataset.value);
        const purity = numberValue(row.querySelector('.receive-purity')?.value);
        const gross = numberValue(row.querySelector('.receive-gross-wt')?.value);
        const other = numberValue(row.querySelector('.other-wt')?.value);
        const receiveNet = Math.max(0, gross - other);
        const receiveFine = receiveNet * purity / 100;
        const pendingNet = issueNet - receiveNet;

        row.querySelector('.receive-net-wt').value = fixed(receiveNet);
        row.querySelector('.receive-net-view').value = fixed(receiveNet);
        row.querySelector('.receive-fine-wt').value = fixed(receiveFine);
        row.querySelector('.receive-fine-view').value = fixed(receiveFine);
        row.querySelector('.pending-net-wt').value = fixed(pendingNet);
    }

    function rowHasData(row) {
        return Boolean(row.querySelector('.receive-item-select')?.value)
            || numberValue(row.querySelector('.receive-gross-wt')?.value) > 0
            || numberValue(row.querySelector('.receive-qty-pcs')?.value) > 0
            || (row.querySelector('input[name$="[remarks]"]')?.value || '').trim() !== '';
    }

    function renumberRows() {
        document.querySelectorAll('.receive-row').forEach(function (row, index) {
            row.querySelector('.receive-sr').textContent = index + 1;
            row.querySelectorAll('select[name^="items["], input[name^="items["]').forEach(function (field) {
                field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
        receiveRowIndex = document.querySelectorAll('.receive-row').length;
    }

    function bindRow(row) {
        row.querySelector('.receive-item-select')?.addEventListener('change', function () {
            recalculateTotals();
        });

        row.querySelectorAll('.receive-gross-wt, .receive-qty-pcs, input[name$="[remarks]"]').forEach(function (input) {
            input.addEventListener('input', function () {
                recalculateTotals();
            });
        });

        row.querySelector('.receive-other-btn')?.addEventListener('click', function () {
            activeReceiveRow = row;
            document.getElementById('receiveOtherWeight').value = row.querySelector('.other-wt').value || '0.000';
            document.getElementById('receiveOtherAmount').value = row.querySelector('.other-amt').value || '0.00';
            if (receiveOtherModal) {
                receiveOtherModal.show();
            } else {
                $('#receiveOtherModal').modal('show');
            }
        });
    }

    function createReceiveRow() {
        const tbody = document.querySelector('#jobworkReceiveTable tbody');
        const index = receiveRowIndex++;
        const row = document.createElement('tr');
        row.className = 'receive-row';
        row.innerHTML = `
            <td class="receive-sr">${index + 1}</td>
            <td>
                <select name="items[${index}][item_id]" class="form-select receive-item-select">${optionHtml()}</select>
            </td>
            <td class="issue-net-wt" data-value="0.000">0.000</td>
            <td><input type="number" step="0.001" class="form-control receive-purity" value="0.000" readonly></td>
            <td><input type="number" step="0.001" min="0" name="items[${index}][receive_gross_wt]" class="form-control receive-gross-wt" value="0.000"></td>
            <td>
                <input type="hidden" name="items[${index}][other_wt]" class="other-wt" value="0.000">
                <input type="hidden" name="items[${index}][other_amt]" class="other-amt" value="0.00">
                <button type="button" class="btn btn-warning receive-other-btn">Wt | Amt</button>
                <div class="receive-other-summary">Wt: <span class="summary-other-wt">0.000</span> | Amt: <span class="summary-other-amt">0.00</span></div>
            </td>
            <td>
                <input type="hidden" name="items[${index}][receive_net_wt]" class="receive-net-wt" value="0.000">
                <input type="text" class="form-control receive-net-view" value="0.000" readonly>
            </td>
            <td>
                <input type="hidden" name="items[${index}][receive_fine_wt]" class="receive-fine-wt" value="0.000">
                <input type="text" class="form-control receive-fine-view" value="0.000" readonly>
            </td>
            <td><input type="number" step="1" min="0" name="items[${index}][receive_qty_pcs]" class="form-control receive-qty-pcs" value="0"></td>
            <td><input type="text" class="form-control pending-net-wt" value="0.000" readonly></td>
            <td><input type="text" name="items[${index}][remarks]" class="form-control" value=""></td>
        `;
        tbody.appendChild(row);
        bindRow(row);
        initReceiveItemSelect(row);
        return row;
    }

    function recalculateTotals() {
        let receiveGross = 0;
        let receiveNet = 0;
        let receiveFine = 0;
        let receiveQty = 0;

        document.querySelectorAll('.receive-row').forEach(function (row) {
            recalculateRow(row);
            receiveGross += numberValue(row.querySelector('.receive-gross-wt')?.value);
            receiveNet += numberValue(row.querySelector('.receive-net-wt')?.value);
            receiveFine += numberValue(row.querySelector('.receive-fine-wt')?.value);
            receiveQty += numberValue(row.querySelector('.receive-qty-pcs')?.value);
        });

        const pendingNet = totalIssueNet - receiveNet;

        document.getElementById('totalReceiveGross').textContent = fixed(receiveGross);
        document.getElementById('totalReceiveNet').textContent = fixed(receiveNet);
        document.getElementById('totalReceiveFine').textContent = fixed(receiveFine);
        document.getElementById('totalReceiveQty').textContent = String(Math.round(receiveQty));
        document.getElementById('totalPendingNet').textContent = fixed(pendingNet);
        document.getElementById('topReceiveNet').textContent = fixed(receiveNet);
        document.getElementById('topPendingNet').textContent = fixed(pendingNet);
    }

    document.getElementById('applyReceiveOther').addEventListener('click', function () {
        if (!activeReceiveRow) {
            return;
        }

        const other = fixed(document.getElementById('receiveOtherWeight').value);
        const otherAmt = fixed(document.getElementById('receiveOtherAmount').value, 2);
        activeReceiveRow.querySelector('.other-wt').value = other;
        activeReceiveRow.querySelector('.other-amt').value = otherAmt;
        activeReceiveRow.querySelector('.summary-other-wt').textContent = other;
        activeReceiveRow.querySelector('.summary-other-amt').textContent = otherAmt;
        if (receiveOtherModal) {
            receiveOtherModal.hide();
        } else {
            $('#receiveOtherModal').modal('hide');
        }
        recalculateTotals();
    });

    document.querySelectorAll('.receive-row').forEach(bindRow);
    document.getElementById('jobworkReceiveTable')?.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowDown') {
            return;
        }

        const currentRow = event.target.closest('.receive-row');
        const rows = Array.from(document.querySelectorAll('.receive-row'));
        if (!currentRow || currentRow !== rows[rows.length - 1]) {
            return;
        }

        event.preventDefault();
        const newRow = createReceiveRow();
        recalculateTotals();
        const $newSelect = window.jQuery ? $(newRow).find('.receive-item-select') : null;
        if ($newSelect && $newSelect.data('select2')) {
            $newSelect.select2('open');
        } else {
            newRow.querySelector('.receive-item-select')?.focus();
        }
    });
    initReceiveItemSelect(document);

    document.querySelectorAll('.receive-row td:first-child').forEach(function (cell) {
        cell.classList.add('receive-sr');
    });
    document.querySelector('form').addEventListener('submit', function () {
        document.querySelectorAll('.receive-row').forEach(function (row) {
            if (!rowHasData(row)) {
                row.remove();
            }
        });
        renumberRows();
    });

    recalculateTotals();
});
</script>
@endpush
