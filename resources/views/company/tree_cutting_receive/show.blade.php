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

        <form method="POST" action="{{ route('company.tree-cutting-receive.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
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

                <div class="table-responsive tree-cutting-receive-scroll">
                    <table class="table table-bordered table-sm tree-cutting-receive-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sr. No</th>
                                <th style="width: 160px;">B. No</th>
                                <th style="width: 190px;">Worker</th>
                                <th style="width: 170px;">Issue Tree Wt</th>
                                <th style="width: 180px;">Receive Pc Wt</th>
                                <th style="width: 190px;">Receive Tree Bhuko</th>
                                <th style="width: 160px;">Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issueItems as $issueItem)
                            @php
                                $receiveItem = $receiveItems->get($issueItem->id);
                                $issueTreeWt = (float) ($issueItem->receive_tree_wt ?? 0);
                                $buchNo = $issueItem->is_custom ? $issueItem->custom_buch_no : ($issueItem->voucherItem?->buch_no ?? '-');
                            @endphp
                            <tr data-receive-row>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $buchNo }}</td>
                                <td>{{ $issueItem->jobWorker?->name ?? '-' }}</td>
                                <td><span data-issue-tree-wt>{{ number_format($issueTreeWt, 3, '.', '') }}</span></td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $issueItem->id }}][receive_pc_wt]"
                                        class="form-control"
                                        data-receive-pc-wt
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $issueItem->id . '.receive_pc_wt', $receiveItem?->receive_pc_wt) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $issueItem->id }}][receive_tree_bhuko]"
                                        class="form-control"
                                        data-receive-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $issueItem->id . '.receive_tree_bhuko', $receiveItem?->receive_tree_bhuko) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        class="form-control"
                                        data-loss
                                        value="{{ $receiveItem?->loss !== null ? number_format((float) $receiveItem->loss, 3, '.', '') : '' }}"
                                        readonly>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center">No tree cutting issue rows found</td>
                            </tr>
                            @endforelse
                        </tbody>
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
    .tree-cutting-receive-table th, .tree-cutting-receive-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
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
    }
    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-receive-pc-wt], [data-receive-tree-bhuko]')) {
            recalcReceiveRow(event.target.closest('[data-receive-row]'));
        }
    });
</script>
@endpush
