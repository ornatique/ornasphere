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

        <form method="POST" action="{{ route('company.casting-release.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
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
                        <tbody>
                            @forelse($voucher->items as $item)
                            @php
                                $issueItem = $issueItems->get($item->id);
                                if (!$issueItem) {
                                    continue;
                                }
                                $releaseItem = $releaseItems->get($item->id);
                                $issueSilverWt = (float) ($issueItem->issue_silver_wt ?? 0);
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
                                        value="{{ old('items.' . $item->id . '.release_tree_wt', $releaseItem?->release_tree_wt) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][release_tree_bhuko]"
                                        class="form-control release-input"
                                        data-release-tree-bhuko
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $item->id . '.release_tree_bhuko', $releaseItem?->release_tree_bhuko) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        class="form-control"
                                        data-loss
                                        value="{{ $releaseItem?->loss !== null ? number_format((float) $releaseItem->loss, 3, '.', '') : '' }}"
                                        readonly>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">No casting metal issue rows found</td>
                            </tr>
                            @endforelse
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

    .casting-release-table th,
    .casting-release-table td {
        padding: 0.65rem 0.8rem;
        vertical-align: middle;
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
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-release-tree-wt], [data-release-tree-bhuko]')) {
            recalcReleaseRow(event.target.closest('tr'));
        }
    });
</script>
@endpush
