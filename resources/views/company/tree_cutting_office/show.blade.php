@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header tree-office-header">
            <div>
                <h4 class="card-title mb-1">Tree Cutting Issue Office</h4>
                <div class="tree-office-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('company.tree-cutting-office.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}" class="btn btn-success">PDF</a>
                <a href="{{ route('company.tree-cutting-office.index', $company->slug) }}" class="btn btn-secondary">Back</a>
            </div>
        </div>

        <form method="POST" action="{{ route('company.tree-cutting-office.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
            @csrf
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

                <div class="tree-office-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) ($voucher->items_count ?? $voucher->items->count()) }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="tree-office-actions mb-2">
                    <button type="button" class="btn btn-secondary btn-sm" id="apply-tree-office-group">Apply To Checked</button>
                </div>

                <div class="table-responsive tree-office-scroll">
                    <table class="table table-bordered table-sm tree-office-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Sr. No</th>
                                <th style="width: 70px;">
                                    <input type="checkbox" id="check-all-tree-office" aria-label="Check all office tree rows">
                                </th>
                                <th style="width: 220px;">B. No</th>
                                <th style="width: 220px;">Tree Wt</th>
                                <th style="width: 240px;">Tree Bhuko</th>
                                <th style="width: 240px;">Remaining Tree Wt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $rowNo = 0;
                                $treeWtTotal = 0;
                                $officeCutWtTotal = 0;
                                $remainingTreeWtTotal = 0;
                            @endphp
                            @foreach($releaseItems as $rowKey => $releaseItem)
                            @php
                                $officeItem = $officeItems->get($rowKey);
                                $isCustom = (bool) $releaseItem->is_custom;
                                $buchNo = $isCustom ? $releaseItem->custom_buch_no : ($releaseItem->voucherItem?->buch_no ?? $rowKey);
                                $treeWt = (float) ($releaseItem->release_tree_wt ?? 0);
                                $officeCutWtValue = old('items.' . $rowKey . '.office_cut_wt', $officeItem?->office_cut_wt);
                                $issueGroupKey = old('items.' . $rowKey . '.issue_group_key', $officeItem?->issue_group_key);
                                $officeCutWt = $officeCutWtValue !== null && $officeCutWtValue !== '' ? (float) $officeCutWtValue : 0;
                                $remainingTreeWt = max($treeWt - $officeCutWt, 0);
                                $treeWtTotal += $treeWt;
                                $officeCutWtTotal += $officeCutWt;
                                $remainingTreeWtTotal += $remainingTreeWt;
                                $rowNo++;
                            @endphp
                            <tr data-tree-office-row data-tree-wt="{{ number_format($treeWt, 3, '.', '') }}" data-group-key="{{ $issueGroupKey }}">
                                <td>{{ $rowNo }}</td>
                                <td>
                                    <input type="hidden"
                                        name="items[{{ $rowKey }}][group_checked]"
                                        class="tree-office-group-checked"
                                        value="0">
                                    <input type="hidden"
                                        name="items[{{ $rowKey }}][selected_for_group]"
                                        class="tree-office-selected-for-group"
                                        value="{{ $issueGroupKey ? '1' : '0' }}">
                                    <input type="checkbox"
                                        name="items[{{ $rowKey }}][keep_group]"
                                        value="1"
                                        class="tree-office-checkbox"
                                        @checked((bool) $issueGroupKey)
                                        @if($issueGroupKey) data-saved-group="1" @endif
                                        aria-label="Select {{ $buchNo }}">
                                    <input type="hidden"
                                        name="items[{{ $rowKey }}][issue_group_key]"
                                        class="tree-office-group-key"
                                        value="{{ $issueGroupKey }}">
                                    <input type="hidden"
                                        name="items[{{ $rowKey }}][bulk_batch_key]"
                                        class="tree-office-batch-key"
                                        value="{{ $issueGroupKey }}">
                                </td>
                                <td>{{ $buchNo }}</td>
                                <td>{{ number_format($treeWt, 3, '.', '') }}</td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $rowKey }}][office_cut_wt]"
                                        class="form-control tree-office-cut-input"
                                        step="0.001"
                                        min="0"
                                        max="{{ number_format($treeWt, 3, '.', '') }}"
                                        inputmode="decimal"
                                        value="{{ $officeCutWtValue }}">
                                </td>
                                <td><strong class="tree-office-remaining">{{ number_format($remainingTreeWt, 3, '.', '') }}</strong></td>
                            </tr>
                            @endforeach

                            @if($rowNo === 0)
                            <tr>
                                <td colspan="6" class="text-center">No casting receive rows found</td>
                            </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td><strong id="treeOfficeTreeWtTotal">{{ number_format($treeWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="treeOfficeCutWtTotal">{{ number_format($officeCutWtTotal, 3, '.', '') }}</strong></td>
                                <td><strong id="treeOfficeRemainingWtTotal">{{ number_format($remainingTreeWtTotal, 3, '.', '') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.tree-cutting-office.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tree-office-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .tree-office-subtitle { color: #b8b8d4; font-size: 13px; }
    .tree-office-summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
    .tree-office-summary > div { border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.035); padding: 10px 12px; }
    .tree-office-summary span { display: block; color: #b8b8d4; font-size: 12px; margin-bottom: 3px; }
    .tree-office-summary strong { color: #fff; font-size: 14px; }
    .tree-office-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tree-office-scroll { max-height: calc(100vh - 390px); overflow-y: auto; border: 1px solid rgba(255, 255, 255, 0.08); }
    .tree-office-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
    .tree-office-table thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .tree-office-table tfoot td { position: sticky; bottom: 0; z-index: 2; background: #25263a; color: #fff; font-weight: 700; }
    .tree-office-table th, .tree-office-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
    .tree-office-table tbody tr.tree-office-group-color-1 td { background: rgba(59, 130, 246, 0.12); }
    .tree-office-table tbody tr.tree-office-group-color-2 td { background: rgba(16, 185, 129, 0.12); }
    .tree-office-table tbody tr.tree-office-group-color-3 td { background: rgba(245, 158, 11, 0.13); }
    .tree-office-table tbody tr.tree-office-group-color-4 td { background: rgba(236, 72, 153, 0.12); }
    .tree-office-table tbody tr.tree-office-group-color-5 td { background: rgba(139, 92, 246, 0.13); }
    .tree-office-table tbody tr.tree-office-group-color-6 td { background: rgba(20, 184, 166, 0.12); }
    .tree-office-table tbody tr[class*="tree-office-group-color-"] td:first-child { box-shadow: inset 4px 0 0 rgba(255, 255, 255, 0.25); }
    .tree-office-cut-input { max-width: 220px; }
    @media (max-width: 991px) { .tree-office-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 575px) { .tree-office-summary { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
    const toTreeOfficeNum = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const treeOfficeNfix = (value) => {
        const number = toTreeOfficeNum(value);
        return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
    };

    function refreshTreeOfficeTotals() {
        let treeWtTotal = 0;
        let officeCutTotal = 0;
        let remainingTotal = 0;

        document.querySelectorAll('[data-tree-office-row]').forEach((row) => {
            const treeWt = toTreeOfficeNum(row.dataset.treeWt);
            const input = row.querySelector('.tree-office-cut-input');
            const officeCut = Math.min(toTreeOfficeNum(input?.value), treeWt);
            const remaining = Math.max(treeWt - officeCut, 0);

            treeWtTotal += treeWt;
            officeCutTotal += officeCut;
            remainingTotal += remaining;

            const remainingEl = row.querySelector('.tree-office-remaining');
            if (remainingEl) {
                remainingEl.textContent = treeOfficeNfix(remaining);
            }
        });

        document.getElementById('treeOfficeTreeWtTotal').textContent = treeOfficeNfix(treeWtTotal);
        document.getElementById('treeOfficeCutWtTotal').textContent = treeOfficeNfix(officeCutTotal);
        document.getElementById('treeOfficeRemainingWtTotal').textContent = treeOfficeNfix(remainingTotal);
    }

    function refreshTreeOfficeGroupColors() {
        const groupColorMap = new Map();
        let nextColor = 1;

        document.querySelectorAll('[data-tree-office-row]').forEach((row) => {
            row.classList.remove(
                'tree-office-group-color-1',
                'tree-office-group-color-2',
                'tree-office-group-color-3',
                'tree-office-group-color-4',
                'tree-office-group-color-5',
                'tree-office-group-color-6'
            );

            const groupKey = row.querySelector('.tree-office-group-key')?.value || row.dataset.groupKey || '';
            if (!groupKey) {
                return;
            }

            if (!groupColorMap.has(groupKey)) {
                groupColorMap.set(groupKey, nextColor);
                nextColor = nextColor === 6 ? 1 : nextColor + 1;
            }

            row.dataset.groupKey = groupKey;
            row.classList.add(`tree-office-group-color-${groupColorMap.get(groupKey)}`);
        });
    }

    function clearTreeOfficeGroup(checkbox) {
        const row = checkbox.closest('tr');
        const rowGroupKey = row?.querySelector('.tree-office-group-key');
        const rowBatchKey = row?.querySelector('.tree-office-batch-key');
        const rowGroupChecked = row?.querySelector('.tree-office-group-checked');
        const rowSelectedForGroup = row?.querySelector('.tree-office-selected-for-group');

        if (rowGroupKey) {
            rowGroupKey.value = '';
        }
        if (row) {
            row.dataset.groupKey = '';
        }
        if (rowBatchKey) {
            rowBatchKey.value = '';
        }
        if (rowGroupChecked) {
            rowGroupChecked.value = '0';
        }
        if (rowSelectedForGroup) {
            rowSelectedForGroup.value = '0';
        }

        delete checkbox.dataset.savedGroup;
        delete checkbox.dataset.activeSelection;
    }

    function applyTreeOfficeGroupToCheckedRows() {
        const activeBoxes = Array.from(document.querySelectorAll('.tree-office-checkbox:checked[data-active-selection="1"]'));
        const checkedBoxes = activeBoxes;
        if (checkedBoxes.length <= 1) {
            return false;
        }

        const existingGroupKey = checkedBoxes
            .map((checkbox) => checkbox.closest('tr')?.querySelector('.tree-office-group-key')?.value || '')
            .find((groupKey) => groupKey);
        const groupKey = existingGroupKey || `office_${Date.now()}_${Math.random().toString(36).substring(2, 10)}`;

        checkedBoxes.forEach((checkbox) => {
            const row = checkbox.closest('tr');
            const rowGroupKey = row?.querySelector('.tree-office-group-key');
            const rowBatchKey = row?.querySelector('.tree-office-batch-key');
            const rowGroupChecked = row?.querySelector('.tree-office-group-checked');
            const rowSelectedForGroup = row?.querySelector('.tree-office-selected-for-group');

            if (rowGroupKey) {
                rowGroupKey.value = groupKey;
            }
            if (row) {
                row.dataset.groupKey = groupKey;
            }
            if (rowBatchKey) {
                rowBatchKey.value = groupKey;
            }
            if (rowGroupChecked) {
                rowGroupChecked.value = '1';
            }
            if (rowSelectedForGroup) {
                rowSelectedForGroup.value = '1';
            }

            checkbox.checked = true;
            checkbox.dataset.savedGroup = '1';
            delete checkbox.dataset.activeSelection;
        });

        refreshTreeOfficeGroupColors();
        return true;
    }

    document.addEventListener('input', function (event) {
        if (!event.target.matches('.tree-office-cut-input')) {
            return;
        }

        const row = event.target.closest('[data-tree-office-row]');
        const treeWt = toTreeOfficeNum(row?.dataset.treeWt);
        const value = toTreeOfficeNum(event.target.value);
        if (value > treeWt) {
            event.target.value = treeOfficeNfix(treeWt);
        }

        refreshTreeOfficeTotals();
    });

    document.getElementById('check-all-tree-office')?.addEventListener('change', function () {
        document.querySelectorAll('.tree-office-checkbox').forEach((checkbox) => {
            checkbox.checked = this.checked;
            if (this.checked) {
                const rowGroupChecked = checkbox.closest('tr')?.querySelector('.tree-office-group-checked');
                const rowSelectedForGroup = checkbox.closest('tr')?.querySelector('.tree-office-selected-for-group');
                if (rowGroupChecked) {
                    rowGroupChecked.value = '1';
                }
                if (rowSelectedForGroup) {
                    rowSelectedForGroup.value = '1';
                }
                checkbox.dataset.activeSelection = '1';
                delete checkbox.dataset.savedGroup;
            } else {
                clearTreeOfficeGroup(checkbox);
            }
        });
        refreshTreeOfficeGroupColors();
    });

    document.getElementById('apply-tree-office-group')?.addEventListener('click', function () {
        applyTreeOfficeGroupToCheckedRows();
    });

    document.addEventListener('change', function (event) {
        if (!event.target.matches('.tree-office-checkbox')) {
            return;
        }

        const rowGroupChecked = event.target.closest('tr')?.querySelector('.tree-office-group-checked');
        const rowSelectedForGroup = event.target.closest('tr')?.querySelector('.tree-office-selected-for-group');

        if (event.target.checked) {
            if (rowGroupChecked) {
                rowGroupChecked.value = '1';
            }
            if (rowSelectedForGroup) {
                rowSelectedForGroup.value = '1';
            }
            event.target.dataset.activeSelection = '1';
        } else {
            clearTreeOfficeGroup(event.target);
        }

        refreshTreeOfficeGroupColors();
    });

    document.querySelector('form')?.addEventListener('submit', function () {
        const checkedCount = document.querySelectorAll('.tree-office-checkbox:checked[data-active-selection="1"]').length;
        if (checkedCount > 1) {
            applyTreeOfficeGroupToCheckedRows();
        }
    });

    refreshTreeOfficeTotals();
    refreshTreeOfficeGroupColors();
</script>
@endpush
