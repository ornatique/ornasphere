@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header casting-heating-header">
            <div>
                <h4 class="card-title mb-1">Casting Heating Voucher Details</h4>
                <div class="casting-heating-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <div class="casting-heating-actions">
                @php
                    $encryptedVoucherId = \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id);
                @endphp
                <a href="{{ route('company.casting-heating.show', [$company->slug, $encryptedVoucherId, 'download' => 'pdf']) }}" class="btn btn-primary">Download PDF</a>
                <a href="{{ route('company.casting-heating.index', $company->slug) }}" class="btn btn-secondary">Back</a>
            </div>
        </div>

        <form method="POST" action="{{ route('company.casting-heating.update', [$company->slug, $encryptedVoucherId]) }}">
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

                @php
                    $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
                @endphp
                <div class="casting-heating-overview mb-3">
                    <div class="casting-heating-progress">
                        <span>In Bhati</span>
                        <strong><span id="inBhatiPcs">{{ (int) $inBhatiCount }}</span><small>/ {{ $totalPcs }}</small></strong>
                        <div class="casting-heating-progress-bar">
                            <div id="inBhatiProgress" style="width: {{ $totalPcs > 0 ? min(100, round(((int) $inBhatiCount / $totalPcs) * 100)) : 0 }}%;"></div>
                        </div>
                    </div>
                    <div class="casting-heating-summary">
                        <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                        <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                        <div><span>Total Pcs</span><strong id="totalPcs">{{ $totalPcs }}</strong></div>
                        <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                    </div>
                </div>

                <div class="casting-heating-table-title">
                    <h5>Buch Status</h5>
                    <span>{{ $totalPcs }} {{ $totalPcs === 1 ? 'row' : 'rows' }}</span>
                </div>
                <div class="table-responsive casting-heating-scroll">
                    <table class="table table-bordered table-sm casting-heating-table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Sr. No</th>
                                <th>Buch No</th>
                                <th class="text-center" style="width: 110px;">In Bhati</th>
                                <th style="width: 190px;">Check Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($voucher->items as $item)
                            @php
                                $heatingItem = $heatingItems->get($item->id);
                                $checkedAt = $heatingItem?->checked_at;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->buch_no }}</td>
                                <td class="text-center">
                                    <div class="form-check casting-heating-check">
                                        <input type="checkbox"
                                            class="form-check-input"
                                            data-bhati-checkbox
                                            id="item_{{ $item->id }}"
                                            name="items[]"
                                            value="{{ $item->id }}"
                                            {{ in_array((int) $item->id, $checkedItemIds, true) ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="check-time-cell" data-check-time-for="{{ $item->id }}">
                                    {{ $checkedAt ? $checkedAt->format('d-m-Y h:i A') : '-' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No Buch rows found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.casting-heating.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .casting-heating-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .casting-heating-subtitle {
        color: #b8b8d4;
        font-size: 13px;
        line-height: 1.3;
    }

    .casting-heating-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .casting-heating-actions .btn,
    .card-footer .btn {
        min-width: 96px;
        padding: 0.55rem 1rem;
    }

    .casting-heating-overview {
        display: grid;
        grid-template-columns: minmax(220px, 280px) 1fr;
        gap: 14px;
    }

    .casting-heating-progress,
    .casting-heating-summary > div {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.035);
    }

    .casting-heating-progress {
        padding: 14px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .casting-heating-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 10px;
    }

    .casting-heating-summary > div {
        padding: 10px 12px;
    }

    .casting-heating-progress span,
    .casting-heating-summary span {
        display: block;
        color: #b8b8d4;
        font-size: 12px;
        margin-bottom: 3px;
    }

    .casting-heating-progress strong {
        color: #fff;
        font-size: 28px;
        line-height: 1.1;
    }

    .casting-heating-progress small {
        color: #b8b8d4;
        font-size: 14px;
        margin-left: 4px;
    }

    .casting-heating-progress-bar {
        height: 6px;
        background: rgba(255, 255, 255, 0.08);
        margin-top: 12px;
        overflow: hidden;
    }

    .casting-heating-progress-bar > div {
        height: 100%;
        background: #2f80ed;
        transition: width 0.2s ease;
    }

    .casting-heating-summary strong {
        color: #fff;
        font-size: 14px;
    }

    .casting-heating-table-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 2px 0 8px;
    }

    .casting-heating-table-title h5 {
        color: #fff;
        font-size: 15px;
        margin: 0;
    }

    .casting-heating-table-title span {
        color: #b8b8d4;
        font-size: 12px;
    }

    .casting-heating-scroll {
        max-height: calc(100vh - 470px);
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .casting-heating-table {
        margin-bottom: 0;
        table-layout: fixed;
        width: 100%;
    }

    .casting-heating-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
    }

    .casting-heating-table th,
    .casting-heating-table td {
        padding: 0.65rem 0.8rem;
        vertical-align: middle;
    }

    .casting-heating-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.025);
    }

    .casting-heating-table th:nth-child(1),
    .casting-heating-table td:nth-child(1) {
        width: 90px;
    }

    .casting-heating-table th:nth-child(2),
    .casting-heating-table td:nth-child(2) {
        width: 330px;
    }

    .casting-heating-table th:nth-child(3),
    .casting-heating-table td:nth-child(3) {
        width: 110px;
    }

    .casting-heating-table th:nth-child(4),
    .casting-heating-table td:nth-child(4) {
        width: 190px;
        white-space: nowrap;
    }

    .casting-heating-table .form-check {
        min-height: auto;
        margin: 0;
    }

    .casting-heating-check {
        display: flex;
        align-items: center;
        justify-content: center;
        padding-left: 0;
    }

    .casting-heating-check .form-check-input {
        float: none;
        margin-left: 0;
        margin-top: 0;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    @media (max-width: 991px) {
        .casting-heating-overview {
            grid-template-columns: 1fr;
        }

        .casting-heating-summary {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .casting-heating-header,
        .casting-heating-actions {
            align-items: stretch;
        }

        .casting-heating-actions,
        .casting-heating-actions .btn {
            width: 100%;
        }

        .casting-heating-summary {
            grid-template-columns: 1fr;
        }

        .card-footer {
            display: grid;
            gap: 8px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function refreshInBhatiPcs() {
        const count = document.querySelectorAll('[data-bhati-checkbox]:checked').length;
        const target = document.getElementById('inBhatiPcs');
        if (target) {
            target.textContent = count;
        }
        const total = Number(document.getElementById('totalPcs')?.textContent || 0);
        const progress = document.getElementById('inBhatiProgress');
        if (progress) {
            progress.style.width = `${total > 0 ? Math.min(100, Math.round((count / total) * 100)) : 0}%`;
        }
    }

    function currentDisplayTime() {
        const now = new Date();
        const date = new Intl.DateTimeFormat('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).format(now).replace(/\//g, '-');
        const time = new Intl.DateTimeFormat('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }).format(now);

        return `${date} ${time}`;
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches('[data-bhati-checkbox]')) {
            const cell = document.querySelector(`[data-check-time-for="${event.target.value}"]`);
            if (cell) {
                cell.textContent = event.target.checked ? currentDisplayTime() : '-';
            }
            refreshInBhatiPcs();
        }
    });
</script>
@endpush
