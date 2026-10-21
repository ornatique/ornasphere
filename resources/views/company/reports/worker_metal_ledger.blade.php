@extends('company_layout.admin')
@section('content')
<div class="content-wrapper worker-ledger-page">
@include('company.reports.partials.branch_filter')
<div class="card"><div class="card-header ledger-header">
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
    <div class="ledger-help"><strong>All weights in grams</strong><span><b>Dr</b> Metal pending from worker</span><span><b>Cr</b> Excess metal received</span><small>Opening balance includes transactions before the From Date. Loss is not automatically deducted.</small></div>
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
.worker-ledger-page .ledger-scroll { overflow:visible; border:0; padding:0; }
.worker-ledger-page .ledger-scroll:focus-visible { outline:2px solid #657bff; outline-offset:2px; }
.worker-ledger-page .ledger-scroll .table-responsive { overflow-x:auto; margin:0; }
.worker-ledger-page .ledger-table { min-width:900px; border-collapse:separate; border-spacing:0; }
.worker-ledger-page .ledger-table thead th { background:#303047; color:#fff; border-bottom:1px solid #50506a; }
.worker-ledger-page .ledger-table td, .worker-ledger-page .ledger-table th { padding:12px 14px; }
.worker-ledger-page .ledger-table .ledger-debit { background-color:rgba(214,89,110,.09); color:#ffbcc7; }
.worker-ledger-page .ledger-table .ledger-credit { background-color:rgba(75,150,238,.09); color:#b1d9ff; }
.worker-ledger-page .ledger-table thead .ledger-debit, .worker-ledger-page .ledger-table thead .ledger-credit { background:#303047; }
.worker-ledger-page .ledger-group + .ledger-group { margin-top:24px; }
.worker-ledger-page { padding-top:20px; }
.worker-ledger-page .ledger-group { border:1px solid #484860; border-radius:8px; overflow:hidden; background:#26263b; }
.worker-ledger-page .ledger-group-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; padding:18px 20px; background:#2e2e45; border-bottom:1px solid #484860; }
.worker-ledger-page .ledger-group-header h4 { color:#fff; font-size:17px; margin:0 0 6px; }
.worker-ledger-page .ledger-group-header p { margin:0; font-size:13px; }
.worker-ledger-page .ledger-pending { text-align:right; }
.worker-ledger-page .ledger-pending span { display:block; font-size:12px; margin-bottom:4px; }
.worker-ledger-page .ledger-pending strong { color:#fff; font-size:18px; font-variant-numeric:tabular-nums; }
.worker-ledger-page .ledger-table { margin-bottom:0; }
.worker-ledger-page .ledger-table td { border-bottom:1px solid #3c3c54; vertical-align:middle; }
.worker-ledger-page .ledger-table td:nth-last-child(-n+3), .worker-ledger-page .ledger-table th:nth-last-child(-n+3) { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.worker-ledger-page .ledger-table td:first-child { white-space:nowrap; }
.worker-ledger-page .ledger-table tbody tr:last-child { background:#323249; }
.worker-ledger-page .ledger-help { display:flex; gap:8px 24px; flex-wrap:wrap; background:#2c2c42; border:1px solid #42425b; border-radius:6px; padding:12px 16px; margin-bottom:20px; }
.worker-ledger-page .ledger-help small { flex-basis:100%; }
.worker-ledger-page .ledger-filters .form-control, .worker-ledger-page .ledger-filters .form-select { min-height:46px; color:#fff; }
.worker-ledger-page .ledger-actions .btn, .worker-ledger-page .ledger-header .btn { color:#fff; }
.worker-ledger-page .select2-container .select2-selection--single { height:46px!important; display:flex; align-items:center; }
.worker-ledger-page .select2-container .select2-selection__rendered { line-height:normal!important; padding-top:0!important; padding-bottom:0!important; color:#fff!important; }
@media(max-width:1100px) { .worker-ledger-page .ledger-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:575px) { .worker-ledger-page .ledger-filters { grid-template-columns:1fr; gap:12px; } .worker-ledger-page .card-body { padding:16px; } .worker-ledger-page .ledger-actions .btn { flex:1; } .worker-ledger-page .ledger-pending { text-align:left; } }
</style>
@endpush
