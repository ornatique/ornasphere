@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card voucher-history-card">
        <div class="card-header voucher-history-header">
            <h4 class="card-title mb-0">Voucher Process History</h4>
        </div>

        <div class="card-body">
            <form id="voucher-history-form"
                class="voucher-history-filter mb-2"
                data-history-url="{{ route('company.voucher-history.data', [$company->slug, '__VOUCHER_ID__']) }}"
                data-pdf-url="{{ route('company.voucher-history.pdf', [$company->slug, '__VOUCHER_ID__']) }}">
                <div>
                    <label>Select Voucher</label>
                    <select name="voucher_id" id="voucher-history-voucher" class="form-control">
                        <option value="">Select Voucher</option>
                        @foreach($vouchers as $row)
                        <option value="{{ $row->id }}" @selected((int) $selectedVoucherId === (int) $row->id)>
                            {{ $row->voucher_no }}{{ $row->voucher_date ? ' | ' . $row->voucher_date->format('d-m-Y') : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Date</label>
                    <input type="text" id="voucher-history-date" class="form-control" value="{{ $voucher?->voucher_date ? $voucher->voucher_date->format('d-m-Y') : '' }}" readonly>
                </div>
                <div>
                    <label>Process</label>
                    <input type="text" id="voucher-history-process" class="form-control" value="{{ $voucher?->process?->name ?? '' }}" readonly>
                </div>
                <div>
                    <label>Worker</label>
                    <input type="text" id="voucher-history-worker" class="form-control" value="{{ $voucher?->jobWorker?->name ?? '' }}" readonly>
                </div>
                <button type="submit" id="voucher-history-view-btn" class="btn btn-primary">View</button>
                <a href="{{ $voucher ? route('company.voucher-history.pdf', [$company->slug, $voucher->id]) : '#' }}"
                    id="voucher-history-pdf-btn"
                    class="btn btn-success {{ $voucher ? '' : 'd-none' }}">
                    PDF
                </a>
                <button type="button" id="voucher-history-reset-btn" class="btn btn-secondary">Reset</button>
            </form>

            <div id="voucher-history-content">
            @if(!$voucher)
                <div class="voucher-history-empty">
                    Select a voucher to view complete process history.
                </div>
            @else
                @include('company.voucher_history.partials.history', ['voucher' => $voucher, 'history' => $history])
            @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .voucher-history-card { border: 1px solid rgba(255,255,255,0.08); }
    .voucher-history-header { display: flex; align-items: center; justify-content: space-between; }
    .voucher-history-filter {
        display: grid;
        grid-template-columns: minmax(220px, 2fr) repeat(3, minmax(150px, 1fr)) 80px 80px 86px;
        gap: 10px;
        align-items: end;
        padding: 12px;
        border: 1px solid rgba(255,255,255,0.08);
    }
    .voucher-history-filter label { display: block; margin-bottom: 5px; color: #b8b8d4; font-size: 12px; }
    .voucher-history-filter .form-control {
        height: 48px;
        min-height: 48px;
        padding: 0 14px;
        line-height: 48px;
        font-size: 14px;
        width: 100%;
    }
    .voucher-history-filter select.form-control {
        appearance: auto;
        line-height: normal;
    }
    .voucher-history-filter .btn {
        height: 44px;
        min-height: 44px;
        width: 100%;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        margin: 0;
    }
    .voucher-summary-grid { display: grid; grid-template-columns: repeat(8, minmax(110px, 1fr)); border: 1px solid rgba(255,255,255,0.08); }
    .voucher-summary-grid > div { padding: 9px 12px; border-right: 1px solid rgba(255,255,255,0.08); }
    .voucher-summary-grid span { display: block; color: #b8b8d4; font-size: 12px; margin-bottom: 4px; }
    .voucher-summary-grid strong { color: #fff; font-size: 14px; }
    .voucher-history-empty { padding: 24px; text-align: center; color: #b8b8d4; border: 1px dashed rgba(255,255,255,0.14); }
    .voucher-history-timeline { display: grid; gap: 8px; max-height: calc(100vh - 365px); overflow-y: auto; padding-right: 4px; }
    .history-section { display: grid; grid-template-columns: 42px 240px 1fr; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.015); }
    .history-step { display: flex; justify-content: center; padding-top: 14px; position: relative; }
    .history-step::after { content: ""; position: absolute; top: 44px; bottom: -10px; width: 2px; background: rgba(255,255,255,0.18); }
    .history-section:last-child .history-step::after { display: none; }
    .history-step span { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #ff1765; color: #fff; font-weight: 700; z-index: 1; }
    .history-title { padding: 16px 14px; border-right: 1px solid rgba(255,255,255,0.08); }
    .history-title h5 { margin: 0 0 10px; color: #fff; font-size: 16px; }
    .status-pill { display: inline-flex; padding: 5px 10px; border-radius: 6px; background: rgba(0, 200, 110, 0.15); color: #00d084; border: 1px solid rgba(0, 200, 110, 0.35); font-weight: 700; font-size: 12px; }
    .history-table-wrap { overflow-x: auto; }
    .history-table { margin-bottom: 0; min-width: 620px; }
    .history-table th, .history-table td { padding: 0.45rem 0.7rem; vertical-align: middle; white-space: nowrap; }
    .history-table thead th { background: #25263a; color: #fff; }
    .history-table tfoot td { background: #25263a; color: #fff; font-weight: 700; }
    @media (max-width: 1200px) {
        .voucher-history-filter { grid-template-columns: repeat(2, minmax(180px, 1fr)); }
        .voucher-summary-grid { grid-template-columns: repeat(4, minmax(120px, 1fr)); }
        .history-section { grid-template-columns: 42px 190px minmax(0, 1fr); }
    }
    @media (max-width: 768px) {
        .voucher-history-filter, .voucher-summary-grid, .history-section { grid-template-columns: 1fr; }
        .history-step { display: none; }
        .history-title { border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('voucher-history-form');
        const voucherSelect = document.getElementById('voucher-history-voucher');
        const dateInput = document.getElementById('voucher-history-date');
        const processInput = document.getElementById('voucher-history-process');
        const workerInput = document.getElementById('voucher-history-worker');
        const content = document.getElementById('voucher-history-content');
        const viewButton = document.getElementById('voucher-history-view-btn');
        const pdfButton = document.getElementById('voucher-history-pdf-btn');
        const resetButton = document.getElementById('voucher-history-reset-btn');
        const emptyHtml = '<div class="voucher-history-empty">Select a voucher to view complete process history.</div>';

        function resetHistory() {
            voucherSelect.value = '';
            dateInput.value = '';
            processInput.value = '';
            workerInput.value = '';
            content.innerHTML = emptyHtml;
            pdfButton.href = '#';
            pdfButton.classList.add('d-none');
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const voucherId = voucherSelect.value;
            if (!voucherId) {
                resetHistory();
                return;
            }

            const url = form.dataset.historyUrl.replace('__VOUCHER_ID__', encodeURIComponent(voucherId));
            const originalText = viewButton.textContent;
            viewButton.disabled = true;
            viewButton.textContent = 'Loading';

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Unable to load voucher history.');
                    }

                    dateInput.value = payload.voucher.date || '';
                    processInput.value = payload.voucher.process || '';
                    workerInput.value = payload.voucher.worker || '';
                    content.innerHTML = payload.html || emptyHtml;
                    pdfButton.href = payload.pdf_url || form.dataset.pdfUrl.replace('__VOUCHER_ID__', encodeURIComponent(voucherId));
                    pdfButton.classList.remove('d-none');
                    window.history.replaceState({}, document.title, window.location.pathname);
                })
                .catch((error) => {
                    content.innerHTML = '<div class="voucher-history-empty">' + error.message + '</div>';
                    pdfButton.href = '#';
                    pdfButton.classList.add('d-none');
                })
                .finally(() => {
                    viewButton.disabled = false;
                    viewButton.textContent = originalText;
                });
        });

        resetButton.addEventListener('click', resetHistory);
    });
</script>
@endpush
