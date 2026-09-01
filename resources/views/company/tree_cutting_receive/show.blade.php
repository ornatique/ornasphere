@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header tree-cutting-receive-header">
            <div>
                <h4 class="card-title mb-1">Tree Cutting Receive</h4>
                <div class="tree-cutting-receive-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <a href="{{ route('company.tree-cutting-receive.index', $company->slug) }}" class="btn btn-secondary">Back</a>
        </div>

        <form id="treeCuttingReceiveForm" method="POST" action="{{ route('company.tree-cutting-receive.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
            @csrf
            <div class="card-body">
                @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
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

                <div class="tree-cutting-receive-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) ($voucher->items_count ?? $voucher->items->count()) }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="mb-2 text-end">
                    <button type="button" class="btn btn-primary" id="addCustomBhukoRow">+ Custom</button>
                </div>

                <div class="table-responsive tree-cutting-receive-scroll">
                    <table class="table table-bordered table-sm tree-cutting-receive-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sr. No</th>
                                <th style="width: 160px;">B. No</th>
                                <th style="width: 190px;">Worker</th>
                                <th style="width: 160px;">Office Cut Wt</th>
                                <th style="width: 170px;">Issue Tree Wt</th>
                                <th style="width: 180px;">Receive Pc Wt</th>
                                <th style="width: 190px;">Receive Tree Bhuko</th>
                                <th style="width: 160px;">Loss</th>
                            </tr>
                        </thead>
                        <tbody id="treeCuttingReceiveBody">
                            @php
                                $officeCutWtTotal = 0;
                                $issueTreeWtTotal = 0;
                                $receivePcWtTotal = 0;
                                $receiveTreeBhukoTotal = 0;
                                $lossTotal = 0;
                            @endphp
                            @forelse($issueItems as $issueItem)
                            @php
                                $receiveItem = $receiveItems->get($issueItem->id);
                                $officeCutWt = (float) ($issueItem->office_cut_wt ?? 0);
                                $issueTreeWt = (float) ($issueItem->receive_tree_wt ?? 0);
                                $receivePcWtValue = old('items.' . $issueItem->id . '.receive_pc_wt', $receiveItem?->receive_pc_wt);
                                $receiveTreeBhukoValue = old('items.' . $issueItem->id . '.receive_tree_bhuko', $receiveItem?->receive_tree_bhuko);
                                $lossValue = $receiveItem?->loss;
                                $officeCutWtTotal += $officeCutWt;
                                $issueTreeWtTotal += $issueTreeWt;
                                $receivePcWtTotal += $receivePcWtValue !== null && $receivePcWtValue !== '' ? (float) $receivePcWtValue : 0;
                                $receiveTreeBhukoTotal += $receiveTreeBhukoValue !== null && $receiveTreeBhukoValue !== '' ? (float) $receiveTreeBhukoValue : 0;
                                $lossTotal += $lossValue !== null && $lossValue !== '' ? (float) $lossValue : 0;
                            @endphp
                            <tr data-receive-row>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $issueItem->buch_no }}</td>
                                <td>{{ $issueItem->jobWorker?->name ?? '-' }}</td>
                                <td><span>{{ number_format($officeCutWt, 3, '.', '') }}</span></td>
                                <td><span data-issue-tree-wt>{{ number_format($issueTreeWt, 3, '.', '') }}</span></td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $issueItem->id }}][receive_pc_wt]"
                                        class="form-control"
                                        data-receive-pc-wt
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $receivePcWtValue }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $issueItem->id }}][receive_tree_bhuko]"
                                        class="form-control"
                                        data-receive-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $receiveTreeBhukoValue }}">
                                </td>
                                <td>
                                    <input type="number"
                                        class="form-control"
                                        data-loss
                                        value="{{ $lossValue !== null ? number_format((float) $lossValue, 3, '.', '') : '' }}"
                                        readonly>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No tree cutting issue rows found</td>
                            </tr>
                            @endforelse
                            @foreach($customReceiveItems ?? [] as $customItem)
                            @php
                                $receivePcWtValue = old('custom_items.' . $loop->index . '.receive_pc_wt', $customItem->receive_pc_wt);
                                $receiveTreeBhukoValue = old('custom_items.' . $loop->index . '.receive_tree_bhuko', $customItem->receive_tree_bhuko);
                                $customType = old('custom_items.' . $loop->index . '.custom_type', ((float) ($customItem->receive_pc_wt ?? 0) > 0 && (float) ($customItem->receive_tree_bhuko ?? 0) <= 0) ? 'pc_weight' : 'bhuko');
                                $lossValue = $customItem->loss;
                                $receivePcWtTotal += $receivePcWtValue !== null && $receivePcWtValue !== '' ? (float) $receivePcWtValue : 0;
                                $receiveTreeBhukoTotal += $receiveTreeBhukoValue !== null && $receiveTreeBhukoValue !== '' ? (float) $receiveTreeBhukoValue : 0;
                                $lossTotal += $lossValue !== null && $lossValue !== '' ? (float) $lossValue : 0;
                            @endphp
                            <tr class="custom-bhuko-row" data-receive-row>
                                <td>Custom</td>
                                <td>
                                    <input type="hidden" name="custom_items[{{ $loop->index }}][id]" value="{{ $customItem->id }}">
                                    <select name="custom_items[{{ $loop->index }}][custom_type]"
                                        class="form-control mb-2 casting-search-select"
                                        data-custom-type>
                                        <option value="bhuko" @selected($customType === 'bhuko')>Bhuko</option>
                                        <option value="pc_weight" @selected($customType === 'pc_weight')>PC Weight</option>
                                    </select>
                                    <input type="text"
                                        name="custom_items[{{ $loop->index }}][custom_buch_no]"
                                        class="form-control"
                                        data-custom-buch-no
                                        maxlength="100"
                                        value="{{ old('custom_items.' . $loop->index . '.custom_buch_no', $customItem->custom_buch_no) }}"
                                        placeholder="B. No">
                                </td>
                                <td>-</td>
                                <td><span>0.000</span></td>
                                <td><span data-issue-tree-wt>0.000</span></td>
                                <td>
                                    <input type="number"
                                        name="custom_items[{{ $loop->index }}][receive_pc_wt]"
                                        class="form-control"
                                        data-receive-pc-wt
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $receivePcWtValue }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="custom_items[{{ $loop->index }}][receive_tree_bhuko]"
                                        class="form-control"
                                        data-receive-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $receiveTreeBhukoValue }}">
                                </td>
                                <td>
                                    <div class="custom-loss-actions">
                                        <input type="number"
                                            class="form-control"
                                            data-loss
                                            value="{{ $lossValue !== null ? number_format((float) $lossValue, 3, '.', '') : '' }}"
                                            readonly>
                                        <button type="button" class="btn btn-sm btn-danger remove-custom-bhuko-row">Remove</button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td><strong>{{ number_format($officeCutWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong>{{ number_format($issueTreeWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="treeReceivePcWtTotal">{{ number_format($receivePcWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="treeReceiveBhukoTotal">{{ number_format($receiveTreeBhukoTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="treeReceiveLossTotal">{{ number_format($lossTotal, 3, '.', '') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.tree-cutting-receive.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tree-cutting-receive-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .tree-cutting-receive-subtitle { color: #b8b8d4; font-size: 13px; }
    .tree-cutting-receive-summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
    .tree-cutting-receive-summary > div { border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.035); padding: 10px 12px; }
    .tree-cutting-receive-summary span { display: block; color: #b8b8d4; font-size: 12px; margin-bottom: 3px; }
    .tree-cutting-receive-summary strong { color: #fff; font-size: 14px; }
    .tree-cutting-receive-scroll { max-height: calc(100vh - 430px); overflow-y: auto; border: 1px solid rgba(255, 255, 255, 0.08); }
    .tree-cutting-receive-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
    .tree-cutting-receive-table thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .tree-cutting-receive-table tfoot td { position: sticky; bottom: 0; z-index: 2; background: #25263a; color: #fff; font-weight: 700; }
    .tree-cutting-receive-table th, .tree-cutting-receive-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
    .custom-bhuko-row td { background: rgba(42, 78, 116, 0.24); }
    .custom-loss-actions { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
    .custom-loss-actions [data-loss] { width: 100%; min-width: 96px; text-align: right; }
    .custom-bhuko-row .btn-danger { min-width: 78px; }
    @media (max-width: 991px) { .tree-cutting-receive-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 575px) { .tree-cutting-receive-summary { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
    const toReceiveNum = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const receiveNfix = (value) => {
        const number = toReceiveNum(value);
        return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
    };
    function recalcReceiveRow(row) {
        const issueTreeWt = toReceiveNum(row.querySelector('[data-issue-tree-wt]')?.textContent);
        const receivePcWt = toReceiveNum(row.querySelector('[data-receive-pc-wt]')?.value);
        const bhuko = toReceiveNum(row.querySelector('[data-receive-tree-bhuko]')?.value);
        const loss = row.querySelector('[data-loss]');
        if (loss) {
            loss.value = receiveNfix(receivePcWt + bhuko - issueTreeWt);
        }
        updateTreeReceiveTotals();
    }
    function nextCustomIndex() {
        let maxIndex = -1;
        document.querySelectorAll('[name^="custom_items["]').forEach((input) => {
            const match = input.name.match(/^custom_items\[(\d+)]/);
            if (match) {
                maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
            }
        });
        return maxIndex + 1;
    }
    function customBhukoRow(index) {
        return `
            <tr class="custom-bhuko-row" data-receive-row>
                <td>Custom</td>
                <td>
                    <input type="hidden" name="custom_items[${index}][id]" value="">
                    <select name="custom_items[${index}][custom_type]"
                        class="form-control mb-2 casting-search-select"
                        data-custom-type>
                        <option value="bhuko" selected>Bhuko</option>
                        <option value="pc_weight">PC Weight</option>
                    </select>
                    <input type="text"
                        name="custom_items[${index}][custom_buch_no]"
                        class="form-control"
                        data-custom-buch-no
                        maxlength="100"
                        placeholder="B. No">
                </td>
                <td>-</td>
                <td><span>0.000</span></td>
                <td><span data-issue-tree-wt>0.000</span></td>
                <td>
                    <input type="number"
                        name="custom_items[${index}][receive_pc_wt]"
                        class="form-control"
                        data-receive-pc-wt
                        step="0.001"
                        min="0"
                        inputmode="decimal">
                </td>
                <td>
                    <input type="number"
                        name="custom_items[${index}][receive_tree_bhuko]"
                        class="form-control"
                        data-receive-tree-bhuko
                        step="0.001"
                        min="0"
                        inputmode="decimal">
                </td>
                <td>
                    <div class="custom-loss-actions">
                        <input type="number" class="form-control" data-loss readonly>
                        <button type="button" class="btn btn-sm btn-danger remove-custom-bhuko-row">Remove</button>
                    </div>
                </td>
            </tr>
        `;
    }

    function initCastingSelects(context = document) {
        if (!window.jQuery || !$.fn.select2) {
            return;
        }

        $(context).find('.casting-search-select').each(function () {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                minimumResultsForSearch: 0
            });
        });
    }

    function updateTreeReceiveTotals() {
        let receivePcWtTotal = 0;
        let bhukoTotal = 0;
        let lossTotal = 0;

        document.querySelectorAll('.tree-cutting-receive-table tbody [data-receive-row]').forEach((row) => {
            receivePcWtTotal += toReceiveNum(row.querySelector('[data-receive-pc-wt]')?.value);
            bhukoTotal += toReceiveNum(row.querySelector('[data-receive-tree-bhuko]')?.value);
            lossTotal += toReceiveNum(row.querySelector('[data-loss]')?.value);
        });

        document.getElementById('treeReceivePcWtTotal').textContent = receiveNfix(receivePcWtTotal);
        document.getElementById('treeReceiveBhukoTotal').textContent = receiveNfix(bhukoTotal);
        document.getElementById('treeReceiveLossTotal').textContent = receiveNfix(lossTotal);
    }
    function applyCustomTypeState(row) {
        if (!row?.classList.contains('custom-bhuko-row')) {
            return;
        }

        const type = row.querySelector('[data-custom-type]')?.value || 'bhuko';
        const pcWtInput = row.querySelector('[data-receive-pc-wt]');
        const bhukoInput = row.querySelector('[data-receive-tree-bhuko]');

        if (type === 'pc_weight') {
            if (bhukoInput) {
                bhukoInput.value = '';
                bhukoInput.disabled = true;
            }
            if (pcWtInput) {
                pcWtInput.disabled = false;
            }
        } else {
            if (pcWtInput) {
                pcWtInput.value = '';
                pcWtInput.disabled = true;
            }
            if (bhukoInput) {
                bhukoInput.disabled = false;
            }
        }

        recalcReceiveRow(row);
    }
    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-receive-pc-wt], [data-receive-tree-bhuko]')) {
            recalcReceiveRow(event.target.closest('[data-receive-row]'));
        }
    });
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-custom-type]')) {
            applyCustomTypeState(event.target.closest('[data-receive-row]'));
        }
    });
    document.getElementById('addCustomBhukoRow')?.addEventListener('click', function () {
        const body = document.getElementById('treeCuttingReceiveBody');
        if (!body) {
            return;
        }

        body.insertAdjacentHTML('beforeend', customBhukoRow(nextCustomIndex()));
        const newRow = body.lastElementChild;
        initCastingSelects(newRow);
        newRow?.querySelector('[data-custom-buch-no]')?.focus();
        applyCustomTypeState(newRow);
    });
    document.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-custom-bhuko-row');
        if (removeButton) {
            removeButton.closest('.custom-bhuko-row')?.remove();
            updateTreeReceiveTotals();
        }
    });
    document.getElementById('treeCuttingReceiveForm')?.addEventListener('submit', function (event) {
        for (const row of document.querySelectorAll('.custom-bhuko-row')) {
            const nameInput = row.querySelector('[data-custom-buch-no]');
            const type = row.querySelector('[data-custom-type]')?.value || 'bhuko';
            const valueInput = type === 'pc_weight'
                ? row.querySelector('[data-receive-pc-wt]')
                : row.querySelector('[data-receive-tree-bhuko]');
            const hasName = (nameInput?.value || '').trim() !== '';
            const hasValue = toReceiveNum(valueInput?.value) > 0;

            if (!hasName || !hasValue) {
                event.preventDefault();
                alert('Please enter custom B. No and value, or remove the custom row.');
                (hasName ? valueInput : nameInput)?.focus();
                return;
            }
        }
    });
    initCastingSelects();
    document.querySelectorAll('.custom-bhuko-row').forEach(applyCustomTypeState);
    updateTreeReceiveTotals();
</script>
@endpush
