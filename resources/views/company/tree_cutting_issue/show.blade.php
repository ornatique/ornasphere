@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header tree-cutting-header">
            <div>
                <h4 class="card-title mb-1">Tree Cutting Issue</h4>
                <div class="tree-cutting-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <a href="{{ route('company.tree-cutting-issue.index', $company->slug) }}" class="btn btn-secondary">Back</a>
        </div>

        <form method="POST" action="{{ route('company.tree-cutting-issue.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
            @csrf
            <input type="hidden" name="group_action_worker_id" id="tree-group-action-worker-id">
            <input type="hidden" name="group_action_item_ids" id="tree-group-action-item-ids">
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

                <div class="tree-cutting-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) ($voucher->items_count ?? $voucher->items->count()) }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="tree-cutting-actions mb-2">
                    <div class="tree-cutting-bulk">
                        <select id="bulk-tree-worker" class="form-control tree-cutting-worker-select">
                            <option value="">Select Worker</option>
                            @foreach($jobWorkers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" id="apply-bulk-tree-worker">Apply To Checked</button>
                    </div>
                    <button type="button" class="btn btn-info btn-sm" id="add-custom-tree-row">+ Custom</button>
                </div>

                <div class="table-responsive tree-cutting-scroll">
                    <table class="table table-bordered table-sm tree-cutting-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Sr. No</th>
                                <th style="width: 70px;">
                                    <input type="checkbox" id="check-all-tree-issue" aria-label="Check all tree rows">
                                </th>
                                <th style="width: 220px;">B. No</th>
                                <th style="width: 240px;">Receive Tree Wt</th>
                                <th style="width: 260px;">Assign Worker</th>
                            </tr>
                        </thead>
                        <tbody id="tree-cutting-issue-rows">
                            @php
                                $issueRowNo = 0;
                                $receiveTreeWtTotal = 0;
                            @endphp
                            @foreach($voucher->items as $item)
                            @php
                                $receiveItem = $receiveItems->get($item->id);
                                if (!$receiveItem) {
                                    continue;
                                }
                                $treeCuttingItem = $treeCuttingItems->get($item->id);
                                $defaultReceiveTreeWt = $receiveItem?->release_tree_wt;
                                $receiveTreeWtValue = old('items.' . $item->id . '.receive_tree_wt', $defaultReceiveTreeWt);
                                $defaultWorkerId = $treeCuttingItem?->job_worker_id;
                                $issueGroupKey = old('items.' . $item->id . '.issue_group_key', $treeCuttingItem?->issue_group_key);
                                $receiveTreeWtTotal += $receiveTreeWtValue !== null && $receiveTreeWtValue !== '' ? (float) $receiveTreeWtValue : 0;
                                $issueRowNo++;
                            @endphp
                            <tr data-tree-issue-row data-group-key="{{ $issueGroupKey }}">
                                <td data-row-no>{{ $issueRowNo }}</td>
                                <td>
                                    <input type="hidden"
                                        name="items[{{ $item->id }}][group_checked]"
                                        class="tree-issue-group-checked"
                                        value="0">
                                    <input type="hidden"
                                        name="items[{{ $item->id }}][selected_for_group]"
                                        class="tree-issue-selected-for-group"
                                        value="{{ $issueGroupKey ? '1' : '0' }}">
                                    <input type="checkbox"
                                        name="items[{{ $item->id }}][keep_group]"
                                        value="1"
                                        class="tree-issue-checkbox"
                                        @checked((bool) $issueGroupKey)
                                        @if($issueGroupKey) data-saved-group="1" @endif
                                        aria-label="Select {{ $item->buch_no }}">
                                    <input type="hidden"
                                        name="items[{{ $item->id }}][issue_group_key]"
                                        class="tree-issue-group-key"
                                        value="{{ $issueGroupKey }}">
                                    <input type="hidden"
                                        name="items[{{ $item->id }}][bulk_batch_key]"
                                        class="tree-issue-batch-key"
                                        value="{{ $issueGroupKey }}">
                                </td>
                                <td>{{ $item->buch_no }}</td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][receive_tree_wt]"
                                        class="form-control tree-cutting-input"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                         value="{{ $receiveTreeWtValue }}">
                                </td>
                                <td>
                                    <select name="items[{{ $item->id }}][job_worker_id]" class="form-control tree-cutting-worker-select">
                                        <option value="">Select Worker</option>
                                        @foreach($jobWorkers as $worker)
                                        <option value="{{ $worker->id }}" @selected((string) old('items.' . $item->id . '.job_worker_id', $defaultWorkerId) === (string) $worker->id)>{{ $worker->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endforeach

                            @foreach($customTreeCuttingItems as $customItem)
                            @php
                                $customReceiveTreeWtValue = old('custom_existing.' . $customItem->id . '.receive_tree_wt', $customItem->receive_tree_wt);
                                $receiveTreeWtTotal += $customReceiveTreeWtValue !== null && $customReceiveTreeWtValue !== '' ? (float) $customReceiveTreeWtValue : 0;
                                $issueRowNo++;
                            @endphp
                            <tr data-custom-existing-row>
                                <td data-row-no>{{ $issueRowNo }}</td>
                                <td></td>
                                <td>
                                    <input type="text"
                                        name="custom_existing[{{ $customItem->id }}][custom_buch_no]"
                                        class="form-control"
                                        value="{{ old('custom_existing.' . $customItem->id . '.custom_buch_no', $customItem->custom_buch_no) }}"
                                        placeholder="Custom B No">
                                </td>
                                <td>
                                    <input type="number"
                                        name="custom_existing[{{ $customItem->id }}][receive_tree_wt]"
                                        class="form-control tree-cutting-input"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ $customReceiveTreeWtValue }}">
                                </td>
                                <td>
                                    <select name="custom_existing[{{ $customItem->id }}][job_worker_id]" class="form-control tree-cutting-worker-select">
                                        <option value="">Select Worker</option>
                                        @foreach($jobWorkers as $worker)
                                        <option value="{{ $worker->id }}" @selected((string) old('custom_existing.' . $customItem->id . '.job_worker_id', $customItem->job_worker_id) === (string) $worker->id)>{{ $worker->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endforeach

                            @if($issueRowNo === 0)
                            <tr>
                                <td colspan="5" class="text-center">No casting receive rows found</td>
                            </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td><strong id="treeIssueReceiveTreeWtTotal">{{ number_format($receiveTreeWtTotal, 3, '.', '') }}</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.tree-cutting-issue.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .tree-cutting-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .tree-cutting-subtitle { color: #b8b8d4; font-size: 13px; }
    .tree-cutting-summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
    .tree-cutting-summary > div { border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.035); padding: 10px 12px; }
    .tree-cutting-summary span { display: block; color: #b8b8d4; font-size: 12px; margin-bottom: 3px; }
    .tree-cutting-summary strong { color: #fff; font-size: 14px; }
    .tree-cutting-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .tree-cutting-bulk { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .tree-cutting-scroll { max-height: calc(100vh - 430px); overflow-y: auto; border: 1px solid rgba(255, 255, 255, 0.08); }
    .tree-cutting-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
    .tree-cutting-table thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .tree-cutting-table tfoot td { position: sticky; bottom: 0; z-index: 2; background: #25263a; color: #fff; font-weight: 700; }
    .tree-cutting-table th, .tree-cutting-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
    .tree-cutting-table tbody tr.tree-group-color-1 td { background: rgba(59, 130, 246, 0.12); }
    .tree-cutting-table tbody tr.tree-group-color-2 td { background: rgba(16, 185, 129, 0.12); }
    .tree-cutting-table tbody tr.tree-group-color-3 td { background: rgba(245, 158, 11, 0.13); }
    .tree-cutting-table tbody tr.tree-group-color-4 td { background: rgba(236, 72, 153, 0.12); }
    .tree-cutting-table tbody tr.tree-group-color-5 td { background: rgba(139, 92, 246, 0.13); }
    .tree-cutting-table tbody tr.tree-group-color-6 td { background: rgba(20, 184, 166, 0.12); }
    .tree-cutting-table tbody tr[class*="tree-group-color-"] td:first-child { box-shadow: inset 4px 0 0 rgba(255, 255, 255, 0.25); }
    .tree-cutting-input { max-width: 220px; }
    .tree-cutting-worker-select { max-width: 240px; }
    @media (max-width: 991px) { .tree-cutting-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 575px) { .tree-cutting-summary { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
    const workerOptionsHtml = @json($jobWorkers->map(fn($worker) => ['id' => $worker->id, 'name' => $worker->name])->values());
    let customTreeRowIndex = 0;

    function refreshTreeIssueRowNumbers() {
        document.querySelectorAll('#tree-cutting-issue-rows [data-row-no]').forEach((cell, index) => {
            cell.textContent = index + 1;
        });
    }

    function workerOptions(selected = '') {
        return '<option value="">Select Worker</option>' + workerOptionsHtml.map((worker) => {
            const isSelected = String(worker.id) === String(selected) ? ' selected' : '';
            const label = document.createElement('span');
            label.textContent = worker.name;
            return `<option value="${worker.id}"${isSelected}>${label.innerHTML}</option>`;
        }).join('');
    }

    const toTreeIssueNum = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const treeIssueNfix = (value) => {
        const number = toTreeIssueNum(value);
        return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
    };

    function updateTreeIssueTotals() {
        let total = 0;
        document.querySelectorAll('#tree-cutting-issue-rows .tree-cutting-input').forEach((input) => {
            total += toTreeIssueNum(input.value);
        });

        const totalEl = document.getElementById('treeIssueReceiveTreeWtTotal');
        if (totalEl) {
            totalEl.textContent = treeIssueNfix(total);
        }
    }

    function refreshTreeIssueGroupColors() {
        const groupColorMap = new Map();
        let nextColor = 1;

        document.querySelectorAll('#tree-cutting-issue-rows [data-tree-issue-row]').forEach((row) => {
            row.classList.remove(
                'tree-group-color-1',
                'tree-group-color-2',
                'tree-group-color-3',
                'tree-group-color-4',
                'tree-group-color-5',
                'tree-group-color-6'
            );

            const groupKey = row.querySelector('.tree-issue-group-key')?.value || row.dataset.groupKey || '';
            if (!groupKey) {
                return;
            }

            if (!groupColorMap.has(groupKey)) {
                groupColorMap.set(groupKey, nextColor);
                nextColor = nextColor === 6 ? 1 : nextColor + 1;
            }

            row.dataset.groupKey = groupKey;
            row.classList.add(`tree-group-color-${groupColorMap.get(groupKey)}`);
        });
    }

    function clearTreeIssueGroup(checkbox) {
        const row = checkbox.closest('tr');
        const rowGroupKey = row?.querySelector('.tree-issue-group-key');
        const rowBatchKey = row?.querySelector('.tree-issue-batch-key');
        const rowGroupChecked = row?.querySelector('.tree-issue-group-checked');
        const rowSelectedForGroup = row?.querySelector('.tree-issue-selected-for-group');

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

    function applyWorkerToCheckedTreeRows(workerId) {
        const checkedBoxes = Array.from(document.querySelectorAll('.tree-issue-checkbox:checked'));
        if (!workerId || checkedBoxes.length === 0) {
            return false;
        }

        const groupKey = checkedBoxes.length > 1
            ? `issue_${Date.now()}_${Math.random().toString(36).substring(2, 10)}`
            : '';
        const itemIds = [];

        checkedBoxes.forEach((checkbox) => {
            const row = checkbox.closest('tr');
            const rowWorker = row?.querySelector('.tree-cutting-worker-select');
            const rowGroupKey = row?.querySelector('.tree-issue-group-key');
            const rowBatchKey = row?.querySelector('.tree-issue-batch-key');
            const rowGroupChecked = row?.querySelector('.tree-issue-group-checked');
            const rowSelectedForGroup = row?.querySelector('.tree-issue-selected-for-group');
            const itemMatch = rowWorker?.name.match(/^items\[(\d+)\]\[job_worker_id\]$/);

            if (rowWorker) {
                rowWorker.value = workerId;
            }
            if (itemMatch) {
                itemIds.push(itemMatch[1]);
            }
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
                rowGroupChecked.value = groupKey ? '1' : '0';
            }
            if (rowSelectedForGroup) {
                rowSelectedForGroup.value = checkedBoxes.length > 1 ? '1' : '0';
            }

            checkbox.checked = Boolean(groupKey);
            if (groupKey) {
                checkbox.dataset.savedGroup = '1';
            } else {
                delete checkbox.dataset.savedGroup;
            }
            delete checkbox.dataset.activeSelection;
        });

        const actionWorkerField = document.getElementById('tree-group-action-worker-id');
        const actionItemIdsField = document.getElementById('tree-group-action-item-ids');
        if (actionWorkerField) {
            actionWorkerField.value = workerId;
        }
        if (actionItemIdsField) {
            actionItemIdsField.value = itemIds.join(',');
        }

        return true;
    }

    function checkedTreeIssuePayload(workerId) {
        const itemIds = [];
        const receiveTreeWt = {};

        document.querySelectorAll('.tree-issue-checkbox:checked').forEach((checkbox) => {
            const row = checkbox.closest('tr');
            const workerSelect = row?.querySelector('.tree-cutting-worker-select');
            const itemMatch = workerSelect?.name.match(/^items\[(\d+)\]\[job_worker_id\]$/);
            if (!itemMatch) {
                return;
            }

            const itemId = itemMatch[1];
            const receiveInput = row?.querySelector('.tree-cutting-input');
            itemIds.push(itemId);
            receiveTreeWt[itemId] = receiveInput?.value || '';
        });

        return {
            worker_id: workerId,
            item_ids: itemIds,
            receive_tree_wt: receiveTreeWt,
        };
    }

    function syncAppliedTreeIssueRows(responseData) {
        const itemIds = (responseData.item_ids || []).map((itemId) => String(itemId));
        const groupKey = responseData.group_key || '';
        const workerId = responseData.worker_id ? String(responseData.worker_id) : '';

        itemIds.forEach((itemId) => {
            const workerSelect = document.querySelector(`[name="items[${itemId}][job_worker_id]"]`);
            const row = workerSelect?.closest('tr');
            const checkbox = row?.querySelector('.tree-issue-checkbox');
            const rowGroupKey = row?.querySelector('.tree-issue-group-key');
            const rowBatchKey = row?.querySelector('.tree-issue-batch-key');
            const rowGroupChecked = row?.querySelector('.tree-issue-group-checked');
            const rowSelectedForGroup = row?.querySelector('.tree-issue-selected-for-group');

            if (workerSelect) {
                workerSelect.value = workerId;
            }
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
                rowGroupChecked.value = groupKey ? '1' : '0';
            }
            if (rowSelectedForGroup) {
                rowSelectedForGroup.value = groupKey ? '1' : '0';
            }
            if (checkbox) {
                checkbox.checked = false;
                if (groupKey) {
                    checkbox.dataset.savedGroup = '1';
                } else {
                    delete checkbox.dataset.savedGroup;
                }
                delete checkbox.dataset.activeSelection;
            }
        });

        refreshTreeIssueGroupColors();
    }

    document.getElementById('add-custom-tree-row')?.addEventListener('click', function () {
        const tbody = document.getElementById('tree-cutting-issue-rows');
        const index = customTreeRowIndex++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td data-row-no></td>
            <td></td>
            <td>
                <input type="text" name="custom_items[${index}][custom_buch_no]" class="form-control" placeholder="Custom B No">
            </td>
            <td>
                <input type="number" name="custom_items[${index}][receive_tree_wt]" class="form-control tree-cutting-input" step="0.001" min="0" inputmode="decimal">
            </td>
            <td>
                <select name="custom_items[${index}][job_worker_id]" class="form-control tree-cutting-worker-select">
                    ${workerOptions()}
                </select>
            </td>
        `;
        tbody.appendChild(row);
        refreshTreeIssueRowNumbers();
        updateTreeIssueTotals();
    });

    document.getElementById('check-all-tree-issue')?.addEventListener('change', function () {
        document.querySelectorAll('.tree-issue-checkbox').forEach((checkbox) => {
            checkbox.checked = this.checked;
            if (this.checked) {
                const rowGroupChecked = checkbox.closest('tr')?.querySelector('.tree-issue-group-checked');
                const rowSelectedForGroup = checkbox.closest('tr')?.querySelector('.tree-issue-selected-for-group');
                if (rowGroupChecked) {
                    rowGroupChecked.value = '1';
                }
                if (rowSelectedForGroup) {
                    rowSelectedForGroup.value = '1';
                }
                checkbox.dataset.activeSelection = '1';
                delete checkbox.dataset.savedGroup;
            } else {
                clearTreeIssueGroup(checkbox);
            }
        });
        refreshTreeIssueGroupColors();
    });

    document.getElementById('apply-bulk-tree-worker')?.addEventListener('click', async function () {
        const workerSelect = document.getElementById('bulk-tree-worker');
        const workerId = workerSelect?.value || '';
        if (!workerId) {
            workerSelect?.focus();
            return;
        }

        const payload = checkedTreeIssuePayload(workerId);
        if (payload.item_ids.length === 0) {
            return;
        }

        this.disabled = true;
        try {
            const response = await fetch("{{ route('company.tree-cutting-issue.apply-group', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.closest('form')?.querySelector('[name="_token"]')?.value || '',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Unable to apply group');
            }

            syncAppliedTreeIssueRows(data);
        } catch (error) {
            alert(error.message || 'Unable to apply group');
            return;
        } finally {
            this.disabled = false;
        }

        const checkAll = document.getElementById('check-all-tree-issue');
        if (checkAll) {
            checkAll.checked = false;
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('.tree-issue-checkbox')) {
            if (event.target.checked) {
                const rowGroupChecked = event.target.closest('tr')?.querySelector('.tree-issue-group-checked');
                const rowSelectedForGroup = event.target.closest('tr')?.querySelector('.tree-issue-selected-for-group');
                if (rowGroupChecked) {
                    rowGroupChecked.value = '1';
                }
                if (rowSelectedForGroup) {
                    rowSelectedForGroup.value = '1';
                }
                event.target.dataset.activeSelection = '1';
                delete event.target.dataset.savedGroup;
            } else {
                clearTreeIssueGroup(event.target);
            }
        }

        if (event.target.matches('#tree-cutting-issue-rows .tree-cutting-worker-select')) {
            const rowCheckbox = event.target.closest('tr')?.querySelector('.tree-issue-checkbox');
            if (rowCheckbox?.checked && applyWorkerToCheckedTreeRows(event.target.value)) {
                return;
            }

            const rowGroupKey = event.target.closest('tr')?.querySelector('.tree-issue-group-key');
            const rowBatchKey = event.target.closest('tr')?.querySelector('.tree-issue-batch-key');
            const rowGroupChecked = event.target.closest('tr')?.querySelector('.tree-issue-group-checked');
            const rowSelectedForGroup = event.target.closest('tr')?.querySelector('.tree-issue-selected-for-group');
            if (rowGroupKey) {
                rowGroupKey.value = '';
            }
            const row = event.target.closest('tr');
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
            if (rowCheckbox) {
                rowCheckbox.checked = false;
                delete rowCheckbox.dataset.savedGroup;
                delete rowCheckbox.dataset.activeSelection;
            }
            refreshTreeIssueGroupColors();
        }
    });

    document.querySelector('form')?.addEventListener('submit', function () {
        document.querySelectorAll('.tree-issue-checkbox').forEach((checkbox) => {
            const rowGroupChecked = checkbox.closest('tr')?.querySelector('.tree-issue-group-checked');
            const rowSelectedForGroup = checkbox.closest('tr')?.querySelector('.tree-issue-selected-for-group');
            const rowGroupKey = checkbox.closest('tr')?.querySelector('.tree-issue-group-key');
            const alreadyGrouped = Boolean(rowGroupKey?.value);
            if (checkbox.checked || alreadyGrouped) {
                if (rowGroupChecked) {
                    rowGroupChecked.value = alreadyGrouped || checkbox.checked ? '1' : '0';
                }
                if (rowSelectedForGroup) {
                    rowSelectedForGroup.value = alreadyGrouped || checkbox.checked ? '1' : '0';
                }
            }
        });

        const checkedRows = Array.from(document.querySelectorAll('.tree-issue-checkbox:checked'));
        const checkedRowsByWorker = checkedRows.reduce((groups, checkbox) => {
            const workerId = checkbox.closest('tr')?.querySelector('.tree-cutting-worker-select')?.value || '';
            if (!workerId) {
                return groups;
            }

            groups[workerId] = groups[workerId] || [];
            groups[workerId].push(checkbox);
            return groups;
        }, {});

        Object.values(checkedRowsByWorker).forEach((checkboxes) => {
            if (checkboxes.length <= 1) {
                return;
            }

            const existingGroupKey = checkboxes
                .map((checkbox) => checkbox.closest('tr')?.querySelector('.tree-issue-group-key')?.value || '')
                .find((value) => value !== '');
            const groupKey = existingGroupKey || `issue_${Date.now()}_${Math.random().toString(36).substring(2, 10)}`;

            checkboxes.forEach((checkbox) => {
                const rowGroupKey = checkbox.closest('tr')?.querySelector('.tree-issue-group-key');
                const rowBatchKey = checkbox.closest('tr')?.querySelector('.tree-issue-batch-key');
                const rowGroupChecked = checkbox.closest('tr')?.querySelector('.tree-issue-group-checked');
                const rowSelectedForGroup = checkbox.closest('tr')?.querySelector('.tree-issue-selected-for-group');

                if (rowGroupKey) {
                    rowGroupKey.value = groupKey;
                }
                const row = checkbox.closest('tr');
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
            });
        });
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('#tree-cutting-issue-rows .tree-cutting-input')) {
            updateTreeIssueTotals();
        }
    });

    updateTreeIssueTotals();
    refreshTreeIssueGroupColors();
</script>
@endpush
