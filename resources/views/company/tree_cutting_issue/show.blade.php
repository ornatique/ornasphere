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

                <div class="mb-2 text-end">
                    <button type="button" class="btn btn-info btn-sm" id="add-custom-tree-row">+ Custom</button>
                </div>

                <div class="table-responsive tree-cutting-scroll">
                    <table class="table table-bordered table-sm tree-cutting-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Sr. No</th>
                                <th style="width: 220px;">B. No</th>
                                <th style="width: 240px;">Receive Tree Wt</th>
                                <th style="width: 260px;">Assign Worker</th>
                            </tr>
                        </thead>
                        <tbody id="tree-cutting-issue-rows">
                            @php
                                $issueRowNo = 0;
                            @endphp
                            @foreach($voucher->items as $item)
                            @php
                                $receiveItem = $receiveItems->get($item->id);
                                if (!$receiveItem) {
                                    continue;
                                }
                                $treeCuttingItem = $treeCuttingItems->get($item->id);
                                $defaultReceiveTreeWt = $treeCuttingItem?->receive_tree_wt ?? $receiveItem?->release_tree_wt;
                                $defaultWorkerId = $treeCuttingItem?->job_worker_id ?? $voucher->job_worker_id;
                                $issueRowNo++;
                            @endphp
                            <tr>
                                <td data-row-no>{{ $issueRowNo }}</td>
                                <td>{{ $item->buch_no }}</td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][receive_tree_wt]"
                                        class="form-control tree-cutting-input"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                         value="{{ old('items.' . $item->id . '.receive_tree_wt', $defaultReceiveTreeWt) }}">
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
                                $issueRowNo++;
                            @endphp
                            <tr data-custom-existing-row>
                                <td data-row-no>{{ $issueRowNo }}</td>
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
                                        value="{{ old('custom_existing.' . $customItem->id . '.receive_tree_wt', $customItem->receive_tree_wt) }}">
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
                                <td colspan="4" class="text-center">No casting receive rows found</td>
                            </tr>
                            @endif
                        </tbody>
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
    .tree-cutting-scroll { max-height: calc(100vh - 430px); overflow-y: auto; border: 1px solid rgba(255, 255, 255, 0.08); }
    .tree-cutting-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
    .tree-cutting-table thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .tree-cutting-table th, .tree-cutting-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
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

    document.getElementById('add-custom-tree-row')?.addEventListener('click', function () {
        const tbody = document.getElementById('tree-cutting-issue-rows');
        const index = customTreeRowIndex++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td data-row-no></td>
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
    });
</script>
@endpush
