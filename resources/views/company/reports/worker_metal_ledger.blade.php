@extends('company_layout.admin')
@section('content')
<div class="content-wrapper worker-ledger-page"><div class="card"><div class="card-header ledger-header">
    <h4 class="card-title mb-0">Worker Metal Ledger</h4>
    <a href="{{ url()->current() }}" class="btn btn-secondary">Loss Details</a>
</div><div class="card-body">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="GET" class="ledger-filters">
        <input type="hidden" name="mode" value="ledger">
        <div><label for="ledger-from">From Date</label><input id="ledger-from" class="form-control" type="date" name="from_date" value="{{ request('from_date') }}"></div>
        <div><label for="ledger-to">To Date</label><input id="ledger-to" class="form-control" type="date" name="to_date" value="{{ request('to_date') }}"></div>
        <div><label for="ledger-worker">Worker</label><select id="ledger-worker" name="worker_id" class="form-select"><option value="">All Workers</option>@foreach($workers as $worker)<option value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) $worker->id) }}" @selected(($ledger['filters']['worker_id'] ?? null) == $worker->id)>{{ $worker->name }}</option>@endforeach</select></div>
        <div><label for="ledger-format">Format</label><select id="ledger-format" name="format" class="form-select"><option value="detailed" @selected(request('format', 'detailed') === 'detailed')>Detailed Ledger</option><option value="daily" @selected(request('format') === 'daily')>Date-wise Summary</option></select></div>
        <div class="ledger-actions"><button class="btn btn-primary">Filter</button>
        <button class="btn btn-success" formaction="{{ url()->current() }}/export/pdf">PDF</button>
        <button class="btn btn-info" formaction="{{ url()->current() }}/export/excel">Excel</button></div>
    </form>
    <p>Weights in grams. Dr: metal pending from worker. Cr: excess received. Opening includes transactions before From Date. Loss is not automatically deducted.</p>
    <div class="ledger-scroll" tabindex="0" role="region" aria-label="Worker ledger transactions">
        @include('company.reports.partials.worker_metal_ledger')
    </div>
</div></div></div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#ledger-worker').select2({
        theme: 'bootstrap4',
        width: '100%',
        minimumResultsForSearch: 0
    }).on('select2:open', function () {
        const search = document.querySelector('.select2-container--open .select2-search__field');
        if (search) {
            search.placeholder = 'Search worker...';
            search.setAttribute('aria-label', 'Search worker');
            search.focus();
        }
    });
});
</script>
@endpush

@push('styles')
<style>
.worker-ledger-page .ledger-header { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; padding:16px 24px; }
.worker-ledger-page .ledger-header .card-title { float:none; margin:0; }
.worker-ledger-page .ledger-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)) auto; gap:20px; align-items:end; margin-bottom:20px; }
.worker-ledger-page .ledger-filters label { display:block; margin-bottom:8px; }
.worker-ledger-page .ledger-actions { display:flex; gap:8px; flex-wrap:wrap; }
.worker-ledger-page .ledger-actions .btn { min-height:44px; }
.worker-ledger-page .ledger-scroll { max-height:max(260px,calc(100vh - 360px)); overflow:auto; scrollbar-gutter:stable; border:1px solid #393952; padding:12px; }
.worker-ledger-page .ledger-scroll:focus-visible { outline:2px solid #657bff; outline-offset:2px; }
.worker-ledger-page .ledger-scroll .table-responsive { overflow:visible; }
.worker-ledger-page .ledger-table { min-width:900px; border-collapse:separate; border-spacing:0; }
.worker-ledger-page .ledger-table thead th { position:sticky; top:-12px; z-index:2; background:#29293f; box-shadow:0 1px 0 #45455f; }
.worker-ledger-page .ledger-table td, .worker-ledger-page .ledger-table th { padding:12px 14px; }
.worker-ledger-page .ledger-table .ledger-debit { background-color:#642d38; color:#fff; }
.worker-ledger-page .ledger-table .ledger-credit { background-color:#234c7b; color:#fff; }
.worker-ledger-page .ledger-table thead .ledger-debit { background-color:#8a2638; }
.worker-ledger-page .ledger-table thead .ledger-credit { background-color:#185ba3; }
.worker-ledger-page .ledger-group + .ledger-group { margin-top:24px; }
@media(max-width:1100px) { .worker-ledger-page .ledger-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:575px) { .worker-ledger-page .ledger-filters { grid-template-columns:1fr; gap:12px; } .worker-ledger-page .card-body { padding:16px; } .worker-ledger-page .ledger-scroll { max-height:60vh; } }
</style>
@endpush
