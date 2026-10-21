@extends('company_layout.admin')
@section('content')
<style>
    .register-export-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .register-export-actions { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }
    .register-export-actions .btn { min-width: 120px; display: inline-flex; align-items: center; justify-content: center; min-height: 44px; }
    #registerDetailsModal .modal-header { padding: 20px 24px; background: #292b40; border-bottom: 1px solid #626780; }
    #registerDetailsModal .modal-title { margin: 0; font-size: 22px; font-weight: 700; color: #fff; }
    #registerDetailsModal .btn-close { filter: invert(1) grayscale(1); opacity: .9; }
    #registerDetailsModal .modal-body { padding: 24px; background: #26283c; color: #e9edf7; }
    #registerDetailsModal .voucher-heading { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px 20px; margin-bottom: 18px; }
    #registerDetailsModal .voucher-heading strong { font-size: 21px; color: #fff; }
    #registerDetailsModal .voucher-heading span { color: #c6cce0; }
    #registerDetailsModal .voucher-stat { height: 100%; border: 1px solid #626780; border-radius: 7px; background: #30334b; padding: 16px; }
    #registerDetailsModal .voucher-stat > div { color: #c6cce0; margin-bottom: 8px; font-size: 13px; }
    #registerDetailsModal .voucher-stat strong { display: block; color: #fff; font-size: 23px; font-weight: 700; line-height: 1.25; font-variant-numeric: tabular-nums; }
    #registerDetailsModal .voucher-stat small { font-size: 13px; color: #c6cce0; }
    #registerDetailsModal #voucher-search-count { display: block; margin-top: 8px; color: #c6cce0; }
    #registerDetailsModal .table { border-collapse: collapse; border: 1px solid #70758e; margin-bottom: 0; }
    #registerDetailsModal .table th, #registerDetailsModal .table td { border: 1px solid #70758e !important; padding: 12px 14px; color: #f0f2fa; vertical-align: middle; }
    #registerDetailsModal .table thead th, #registerDetailsModal .table tfoot th { background: #22253b; font-weight: 700; }
    #registerDetailsModal .table tbody td { background: #30334b; }
    #registerDetailsModal .table tbody tr:nth-child(even) td { background: #2b2e44; }
    #registerDetailsModal .table-responsive { overflow-x: auto; scrollbar-gutter: stable; }
    .approval-register { min-width: 0; max-width: 100%; }
    .approval-register .card-body { min-width: 0; }
    .register-filters { display: grid; grid-template-columns: minmax(180px, 1.4fr) repeat(2, minmax(140px, 1fr)) minmax(170px, 1.2fr) minmax(160px, 1fr) auto; gap: 16px; align-items: end; }
    .register-filters > div { min-width: 0; }
    .register-filters label { display: block; margin-bottom: 6px; }
    .register-filters .form-control { height: 46px; color: #fff; background: #2e2d4d; }
    .register-actions { display: flex; gap: 8px; }
    .register-actions .btn { height: 46px; }
    .approval-register .select2-container .select2-selection--single { height: 46px; background: #2e2d4d; border: 1px solid #44435f; }
    .approval-register .select2-container .select2-selection__rendered { color: #fff; line-height: 44px; padding-left: 12px; }
    .approval-register .select2-container .select2-selection__arrow { height: 44px; }
    .register-dropdown, .register-dropdown .select2-search__field { background: #2e2d4d; color: #fff; }
    .register-dropdown .select2-results__option { color: #fff; }
    .register-dropdown .select2-results__option[aria-selected=true] { background: #454463; }
    .register-dropdown .select2-results__option--highlighted[aria-selected] { background: #2864df; }
    .register-table { overflow-x: auto; max-width: 100%; }
    .register-table table { margin-bottom: 0; }
    .register-table th, .register-table td { padding: 12px 14px; vertical-align: middle; }
    .register-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .register-summary span { display: block; color: #b8bdd1; }
    .register-summary strong { display: block; color: #fff; font-size: 18px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .register-pagination { overflow-x: auto; }
    @media (max-width: 1500px) { .register-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .register-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .register-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 480px) { .register-filters { grid-template-columns: 1fr; } }
</style>
<div class="content-wrapper">
@php($reportCompanyIds = \App\Services\ReportBranches::ids($company))
@include('company.reports.partials.branch_filter')
<div class="card approval-register">
    <div class="card-header"><h4 class="card-title">Approval &amp; Return Register</h4></div>
    <div class="card-body">

        <form method="GET" id="register-filters" class="register-filters mb-3">
            <div><label for="summary-customer">Customer</label><select name="customer_id" id="summary-customer" class="form-control"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) $customer->id) }}" @selected($selectedCustomerId == $customer->id)>{{ $customer->name }}{{ count($reportCompanyIds) > 1 ? ' — '.\App\Services\ReportBranches::name($customer->company_id) : '' }}</option>@endforeach</select></div>
            <div><label for="summary-from">From date</label><input id="summary-from" type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
            <div><label for="summary-to">To date</label><input id="summary-to" type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
            <div><label for="summary-voucher">Voucher</label><input id="summary-voucher" name="voucher" value="{{ request('voucher') }}" class="form-control" placeholder="Approval voucher number"></div>
            <div><label for="entry-type">Entry type</label><select id="entry-type" name="entry_type" class="form-control"><option value="">All types</option>@foreach(['Approval','Approval Return','Sale from Approval'] as $type)<option @selected(request('entry_type') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="register-actions"><button class="btn btn-success">Filter</button> <a class="btn btn-secondary" href="{{ route('company.reports.approval-return-register.index', $company->slug) }}">Reset</a></div>
        </form>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="register-export-toolbar mb-3">
            <div><strong>Approval issues, returns and sales</strong><p class="mb-0 mt-1">Each row shows one transaction voucher against one original approval. A return covering multiple approvals appears on multiple rows. Row count is not the number of distinct return vouchers.</p></div>
            <div class="register-export-actions" role="group" aria-label="Export report">
                <a class="btn btn-primary" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}">Export CSV</a>
                <a class="btn btn-info" href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null]) }}" target="_blank" rel="noopener">Export PDF</a>
            </div>
        </div>
        <details class="mb-3" open><summary>View approval, return and sale totals</summary><div class="row mt-3">
            @foreach($totals as $type => $values)
            <div class="col-md-6 col-xl-4 mb-3"><div class="border rounded p-3"><h5 class="mb-3">{{ $type }}</h5><div class="register-summary">
                @foreach($metrics as $key => $label)
                <div><span>{{ $label }}</span><strong>{{ number_format($values[$key], $key === 'qty' ? 0 : ($key === 'amount' ? 2 : 3)) }}</strong></div>
                @endforeach
            </div></div></div>
            @endforeach
        </div>
        </details>
        <div class="border rounded p-3 mb-3" style="background:rgba(255,255,255,.025)">
            <strong>Custom Report Columns</strong>
            <div class="d-flex flex-wrap mt-2" style="gap:12px">
            @foreach(array_merge(['Date','Type','Voucher','Approval Voucher','Customer','Branch'], array_values($metrics), ['Pending Qty']) as $label)
                <label class="mb-0"><input type="checkbox" class="register-column-toggle" value="{{ $loop->index }}" checked @disabled($label === 'Voucher')> {{ $label }}</label>
            @endforeach
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap:12px">
            <label class="mb-0">Show <select name="per_page" form="register-filters" onchange="this.form.submit()" style="background:#2e2d4d;color:white">@foreach([10,25,50,100] as $size)<option @selected(request('per_page',10)==$size)>{{ $size }}</option>@endforeach</select> entries</label>
            <label class="d-flex align-items-center mb-0" style="gap:8px">Search: <input class="form-control" type="search" name="search" form="register-filters" value="{{ request('search') }}" placeholder="Search and press Enter"></label>
        </div>
        <div class="register-table" tabindex="0" role="region" aria-label="Approval and return entries, scroll horizontally for more columns">
            <table id="register-table" class="table table-bordered text-nowrap">
                <thead><tr><th>Date</th><th>Type</th><th>Voucher</th><th>Approval Voucher</th><th>Customer</th><th>Branch</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach<th>Pending Qty</th></tr></thead>
                <tbody>@forelse($rows as $row)<tr><td>{{ \Carbon\Carbon::parse($row->entry_date)->format('d-m-Y') }}</td><td><span class="badge {{ $row->entry_type === 'Approval' ? 'bg-primary' : ($row->entry_type === 'Approval Return' ? 'bg-success' : 'bg-info') }}">{{ $row->entry_type }}</span></td><td><a class="register-voucher-link text-info" href="{{ route('company.reports.approval-return-register.details', [$company->slug, $row->entry_type === 'Approval' ? 'approval' : ($row->entry_type === 'Approval Return' ? 'return' : 'sale'), $row->voucher_id]) }}?{{ http_build_query(['approval_no' => $row->approval_no]) }}">{{ $row->voucher_no }}</a></td><td>{{ $row->approval_no }}</td><td>{{ $row->customer_name ?? '-' }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id) }}</td>@foreach($metrics as $key => $label)<td class="text-end">{{ number_format($row->$key, $key === 'qty' ? 0 : ($key === 'amount' ? 2 : 3)) }}</td>@endforeach<td class="text-end">{{ $row->entry_type === 'Approval' ? $pendingItems->where('voucher_id', $row->voucher_id)->count() : '-' }}</td></tr>@empty<tr><td colspan="13" class="text-center">No entries match these filters.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="mt-3">Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} approval-wise rows</div><div class="mt-3 register-pagination">{{ $rows->links('pagination::bootstrap-4') }}</div>
        @include('company.reports.partials.approval_register_pending')
    </div>
</div>
</div>
<div class="modal fade" id="registerDetailsModal" tabindex="-1" aria-label="Voucher details">
<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Voucher Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body" id="registerDetailsBody"></div>
</div></div></div>
@endsection
@push('scripts')
<script>$(function () { if ($.fn.select2) $('#summary-customer').select2({theme:'bootstrap4',width:'100%',minimumResultsForSearch:0}); $('#registerDetailsBody').on('input', '#voucher-barcode-search', function () {
    const term = this.value.trim().toLowerCase();
    const rows = $('#registerDetailsBody .voucher-detail-row');
    let matches = 0;
    rows.each(function () {
        this.hidden = !String(this.dataset.barcode || '').toLowerCase().includes(term);
        if (!this.hidden) matches++;
    });
    $('#voucher-search-empty').prop('hidden', matches > 0);
    $('#voucher-search-count').text('Showing ' + matches + ' of ' + rows.length + ' items. Totals above are for all voucher items.');
}).on('keydown', '#voucher-barcode-search', function (event) {
    if (event.key === 'Enter') event.preventDefault();
});
let detailRequest = null;
$('.register-voucher-link').on('click', function (event) {
    event.preventDefault();
    if (detailRequest) detailRequest.abort();
    $('#registerDetailsBody').text('Loading voucher details...');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('registerDetailsModal')).show();
    detailRequest = $.get(this.href).done(function (html) { $('#registerDetailsBody').html(html); })
        .fail(function (xhr, status) { if (status !== 'abort') $('#registerDetailsBody').text('Unable to load voucher details. Please try again.'); });
});
$('.register-column-toggle').on('change', function () {
    const index = Number(this.value) + 1;
    $('#register-table tr').each((_, row) => $(row).children(':nth-child(' + index + ')').not('[colspan]').toggle(this.checked));
});
});</script>
@endpush
