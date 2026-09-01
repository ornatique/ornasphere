@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header casting-release-header">
            <div>
                <h4 class="card-title mb-1">Casting Receive</h4>
                <div class="casting-release-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <a href="{{ route('company.casting-release.index', $company->slug) }}" class="btn btn-secondary">Back</a>
        </div>

        <form id="castingReleaseForm" method="POST" action="{{ route('company.casting-release.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
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

                <div class="casting-release-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) ($voucher->items_count ?? $voucher->items->count()) }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="mb-2 text-end">
                    <button type="button" class="btn btn-primary" id="addCustomBhukoRow">+ Custom</button>
                </div>

                <div class="table-responsive casting-release-scroll">
                    <table class="table table-bordered table-sm casting-release-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sr. No</th>
                                <th style="width: 180px;">B. No</th>
                                <th style="width: 170px;">Issue Silver Wt</th>
                                <th style="width: 180px;">Release Tree Wt</th>
                                <th style="width: 190px;">Release Tree Bhuko</th>
                                <th style="width: 160px;">Loss</th>
                            </tr>
                        </thead>
                        <tbody id="castingReleaseBody">
                            @php
                                $issueSilverWtTotal = 0;
                                $releaseTreeWtTotal = 0;
                                $releaseTreeBhukoTotal = 0;
                                $lossTotal = 0;
                            @endphp
                            @forelse($voucher->items as $item)
                            @php
                                $issueItem = $issueItems->get($item->id);
                                if (!$issueItem) {
                                    continue;
                                }
                                $releaseItem = $releaseItems->get($item->id);
                                $issueSilverWt = (float) ($issueItem->issue_silver_wt ?? 0);
                                $releaseTreeWtValue = old('items.' . $item->id . '.release_tree_wt', $releaseItem?->release_tree_wt);
                                $releaseTreeBhukoValue = old('items.' . $item->id . '.release_tree_bhuko', $releaseItem?->release_tree_bhuko);
                                $lossValue = $releaseItem?->loss;
                                $issueSilverWtTotal += $issueSilverWt;
                                $releaseTreeWtTotal += $releaseTreeWtValue !== null && $releaseTreeWtValue !== '' ? (float) $releaseTreeWtValue : 0;
                                $releaseTreeBhukoTotal += $releaseTreeBhukoValue !== null && $releaseTreeBhukoValue !== '' ? (float) $releaseTreeBhukoValue : 0;
                                $lossTotal += $lossValue !== null && $lossValue !== '' ? (float) $lossValue : 0;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->buch_no }}</td>
                                <td>
                                    <span data-issue-wt>{{ number_format($issueSilverWt, 3, '.', '') }}</span>
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][release_tree_wt]"
                                        class="form-control release-input"
                                        data-release-tree-wt
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $releaseTreeWtValue }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][release_tree_bhuko]"
                                        class="form-control release-input"
                                        data-release-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $releaseTreeBhukoValue }}">
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
                                <td colspan="6" class="text-center">No casting metal issue rows found</td>
                            </tr>
                            @endforelse
                            @foreach($customReleaseItems ?? [] as $customItem)
                            @php
                                $releaseTreeWtValue = old('custom_items.' . $loop->index . '.release_tree_wt', $customItem->release_tree_wt);
                                $releaseTreeBhukoValue = old('custom_items.' . $loop->index . '.release_tree_bhuko', $customItem->release_tree_bhuko);
                                $customType = old('custom_items.' . $loop->index . '.custom_type', $customItem->custom_type ?: (((float) ($customItem->release_tree_wt ?? 0) > 0 && (float) ($customItem->release_tree_bhuko ?? 0) <= 0) ? 'pc_weight' : 'bhuko'));
                                $lossValue = $customItem->loss;
                                $releaseTreeWtTotal += $releaseTreeWtValue !== null && $releaseTreeWtValue !== '' ? (float) $releaseTreeWtValue : 0;
                                $releaseTreeBhukoTotal += $releaseTreeBhukoValue !== null && $releaseTreeBhukoValue !== '' ? (float) $releaseTreeBhukoValue : 0;
                                $lossTotal += $lossValue !== null && $lossValue !== '' ? (float) $lossValue : 0;
                            @endphp
                            <tr class="custom-bhuko-row">
                                <td>Custom</td>
                                <td>
                                    <input type="hidden" name="custom_items[{{ $loop->index }}][id]" value="{{ $customItem->id }}">
                                    <select name="custom_items[{{ $loop->index }}][custom_type]"
                                        class="form-control release-input mb-2 casting-search-select"
                                        data-custom-type>
                                        <option value="bhuko" @selected($customType === 'bhuko')>Bhuko</option>
                                        <option value="pc_weight" @selected($customType === 'pc_weight')>PC Weight</option>
                                    </select>
                                    <input type="text"
                                        name="custom_items[{{ $loop->index }}][custom_buch_no]"
                                        class="form-control release-input"
                                        data-custom-buch-no
                                        maxlength="100"
                                        value="{{ old('custom_items.' . $loop->index . '.custom_buch_no', $customItem->custom_buch_no) }}"
                                        placeholder="B. No">
                                </td>
                                <td><span data-issue-wt>0.000</span></td>
                                <td>
                                    <input type="number"
                                        name="custom_items[{{ $loop->index }}][release_tree_wt]"
                                        class="form-control release-input"
                                        data-release-tree-wt
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $releaseTreeWtValue }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="custom_items[{{ $loop->index }}][release_tree_bhuko]"
                                        class="form-control release-input"
                                        data-release-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $releaseTreeBhukoValue }}">
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
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
                            <tr class="casting-release-total-row">
                                <td colspan="2">Total</td>
                                <td><strong>{{ number_format($issueSilverWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="releaseTreeWtTotal">{{ number_format($releaseTreeWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="releaseTreeBhukoTotal">{{ number_format($releaseTreeBhukoTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="releaseLossTotal">{{ number_format($lossTotal, 3, '.', '') }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.casting-release.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .casting-release-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .casting-release-subtitle {
        color: #b8b8d4;
        font-size: 13px;
    }

    .casting-release-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 10px;
    }

    .casting-release-summary > div {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.035);
        padding: 10px 12px;
    }

    .casting-release-summary span {
        display: block;
        color: #b8b8d4;
        font-size: 12px;
        margin-bottom: 3px;
    }

    .casting-release-summary strong {
        color: #fff;
        font-size: 14px;
    }

    .casting-release-scroll {
        max-height: calc(100vh - 430px);
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .casting-release-table {
        margin-bottom: 0;
        table-layout: fixed;
        width: 100%;
    }

    .casting-release-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
    }

    .casting-release-total-row td {
        background: #25263a;
        color: #fff;
        font-weight: 700;
        border-top: 2px solid rgba(255, 255, 255, 0.18);
    }

    .casting-release-table th,
    .casting-release-table td {
        padding: 0.65rem 0.8rem;
        vertical-align: middle;
    }

    .custom-bhuko-row td {
        background: rgba(42, 78, 116, 0.24);
    }

    .custom-bhuko-row .btn-danger {
        min-width: 78px;
    }

    @media (max-width: 991px) {
        .casting-release-summary {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .casting-release-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const toNum = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const nfix = (value) => {
        const number = toNum(value);
        return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
    };

    function recalcReleaseRow(row) {
        const issueWt = toNum(row.querySelector('[data-issue-wt]')?.textContent);
        const treeWt = toNum(row.querySelector('[data-release-tree-wt]')?.value);
        const bhuko = toNum(row.querySelector('[data-release-tree-bhuko]')?.value);
        const loss = row.querySelector('[data-loss]');
        if (loss) {
            loss.value = nfix(treeWt + bhuko - issueWt);
        }
        updateReleaseTotals();
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
            <tr class="custom-bhuko-row">
                <td>Custom</td>
                <td>
                    <input type="hidden" name="custom_items[${index}][id]" value="">
                    <select name="custom_items[${index}][custom_type]"
                        class="form-control release-input mb-2 casting-search-select"
                        data-custom-type>
                        <option value="bhuko" selected>Bhuko</option>
                        <option value="pc_weight">PC Weight</option>
                    </select>
                    <input type="text"
                        name="custom_items[${index}][custom_buch_no]"
                        class="form-control release-input"
                        data-custom-buch-no
                        maxlength="100"
                        placeholder="B. No">
                </td>
                <td><span data-issue-wt>0.000</span></td>
                <td>
                    <input type="number"
                        name="custom_items[${index}][release_tree_wt]"
                        class="form-control release-input"
                        data-release-tree-wt
                        step="0.001"
                        min="0"
                        inputmode="decimal">
                </td>
                <td>
                    <input type="number"
                        name="custom_items[${index}][release_tree_bhuko]"
                        class="form-control release-input"
                        data-release-tree-bhuko
                        step="0.001"
                        min="0"
                        inputmode="decimal">
                </td>
                <td>
                    <div class="d-flex gap-2">
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

    function updateReleaseTotals() {
        let releaseTreeWtTotal = 0;
        let releaseTreeBhukoTotal = 0;
        let releaseLossTotal = 0;

        document.querySelectorAll('.casting-release-table tbody tr').forEach((row) => {
            if (!row.querySelector('[data-issue-wt]')) {
                return;
            }

            releaseTreeWtTotal += toNum(row.querySelector('[data-release-tree-wt]')?.value);
            releaseTreeBhukoTotal += toNum(row.querySelector('[data-release-tree-bhuko]')?.value);
            releaseLossTotal += toNum(row.querySelector('[data-loss]')?.value);
        });

        document.getElementById('releaseTreeWtTotal').textContent = nfix(releaseTreeWtTotal);
        document.getElementById('releaseTreeBhukoTotal').textContent = nfix(releaseTreeBhukoTotal);
        document.getElementById('releaseLossTotal').textContent = nfix(releaseLossTotal);
    }

    function applyCustomTypeState(row) {
        const type = row.querySelector('[data-custom-type]')?.value || 'bhuko';
        const treeWtInput = row.querySelector('[data-release-tree-wt]');
        const bhukoInput = row.querySelector('[data-release-tree-bhuko]');

        if (!row.classList.contains('custom-bhuko-row')) {
            return;
        }

        if (type === 'pc_weight') {
            if (bhukoInput) {
                bhukoInput.value = '';
                bhukoInput.disabled = true;
            }
            if (treeWtInput) {
                treeWtInput.disabled = false;
            }
        } else {
            if (treeWtInput) {
                treeWtInput.value = '';
                treeWtInput.disabled = true;
            }
            if (bhukoInput) {
                bhukoInput.disabled = false;
            }
        }

        recalcReleaseRow(row);
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-release-tree-wt], [data-release-tree-bhuko]')) {
            recalcReleaseRow(event.target.closest('tr'));
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-custom-type]')) {
            applyCustomTypeState(event.target.closest('tr'));
        }
    });

    document.getElementById('addCustomBhukoRow')?.addEventListener('click', function () {
        const totalRow = document.querySelector('.casting-release-total-row');
        totalRow.insertAdjacentHTML('beforebegin', customBhukoRow(nextCustomIndex()));
        const newRow = totalRow.previousElementSibling;
        initCastingSelects(newRow);
        newRow?.querySelector('[data-custom-buch-no]')?.focus();
        applyCustomTypeState(newRow);
        updateReleaseTotals();
    });

    document.addEventListener('click', function (event) {
        if (!event.target.matches('.remove-custom-bhuko-row')) {
            return;
        }

        event.target.closest('tr')?.remove();
        updateReleaseTotals();
    });

    document.getElementById('castingReleaseForm')?.addEventListener('submit', function (event) {
        for (const row of document.querySelectorAll('.custom-bhuko-row')) {
            const nameInput = row.querySelector('[data-custom-buch-no]');
            const type = row.querySelector('[data-custom-type]')?.value || 'bhuko';
            const valueInput = type === 'pc_weight'
                ? row.querySelector('[data-release-tree-wt]')
                : row.querySelector('[data-release-tree-bhuko]');

            const hasName = (nameInput?.value || '').trim() !== '';
            const hasValue = toNum(valueInput?.value) > 0;

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
    updateReleaseTotals();
</script>
@endpush
