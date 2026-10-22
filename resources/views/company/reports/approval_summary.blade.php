@extends('company_layout.admin')
@section('content')
<style>
#approval-summary-page .summary-filters .select2-selection--single { position:relative !important; }
#approval-summary-page .summary-filters .select2-selection--single .select2-selection__rendered { position:absolute !important; top:50% !important; left:0 !important; right:0 !important; bottom:auto !important; transform:translateY(-50%) !important; text-align:left !important; height:20px !important; }
#approval-summary-page .summary-filters { display:grid; grid-template-columns:minmax(200px,1.4fr) repeat(2,minmax(155px,1fr)) minmax(180px,1.2fr) auto; gap:16px; align-items:end; }
#approval-summary-page .summary-filters > div { width:auto; max-width:none; padding:0; min-width:0; }
#approval-summary-page .summary-filters label { display:block; margin-bottom:7px; }
#approval-summary-page .summary-actions { display:flex; gap:8px; padding-top:0 !important; }
#approval-summary-page .form-control, #approval-summary-page .summary-actions .btn { min-height:46px; }
#approval-summary-page .summary-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
#approval-summary-page .summary-stat { background:#292b42; border:1px solid #464964; border-radius:8px; padding:18px; height:100%; }
#approval-summary-page .summary-stat h5 { font-size:14px; color:#c6cee3; margin:0 0 12px; }
#approval-summary-page .summary-quantity { font-size:26px; font-weight:600; color:#fff; margin-bottom:14px; }
#approval-summary-page .summary-quantity small { font-size:13px; font-weight:400; color:#c6cee3; }
#approval-summary-page .summary-weight { display:flex; justify-content:space-between; gap:12px; padding:5px 0; font-size:13px; }
#approval-summary-page .summary-weight strong { color:#fff; font-variant-numeric:tabular-nums; white-space:nowrap; }
#approval-summary-page .summary-table { max-height:65vh; overflow:auto; border:1px solid #454861; border-radius:6px; }
#approval-summary-page .summary-table table { margin:0; }
#approval-summary-page .summary-table th, #approval-summary-page .summary-table td { padding:14px 16px; border-color:#454861; }
#approval-summary-page .summary-table thead th { position:sticky; top:0; z-index:1; background:#2e3049; }
#approval-summary-page .summary-table .text-end, #approval-summary-page .summary-table tfoot th { text-align:right; font-variant-numeric:tabular-nums; }
#approval-summary-page .summary-table tfoot th:first-child { text-align:left; }
#approval-summary-page #summary-customer, #approval-summary-page .select2-selection--single { background:#2e2d4d !important; border:1px solid #454861 !important; color:#fff !important; min-height:46px; border-radius:5px; }
#approval-summary-page .summary-filters .select2-container { width:100% !important; display:block; }
#approval-summary-page .summary-filters .select2-selection--single { display:flex !important; align-items:center !important; box-sizing:border-box; height:46px !important; min-height:46px !important; padding:0 !important; margin:0 !important; }
#approval-summary-page .summary-filters .select2-selection--single .select2-selection__rendered { display:block !important; width:100%; color:#fff !important; font-size:14px; line-height:20px !important; padding:0 36px 0 14px !important; margin:0 !important; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
#approval-summary-page .summary-filters .select2-selection--single .select2-selection__arrow { top:0 !important; right:8px !important; height:46px !important; }
#approval-summary-page .select2-dropdown { background:#2e2d4d; border-color:#555976; color:#fff; }
#approval-summary-page .select2-results__option { color:#fff; font-size:14px; padding:10px 12px; }
#approval-summary-page .select2-results__option--highlighted { background:#306fdd !important; }
#approval-summary-page .select2-search__field { background:#25263c; color:#fff; border:1px solid #646b91; }
@media(max-width:1400px) { #approval-summary-page .summary-filters { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media(max-width:767px) { #approval-summary-page .summary-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:480px) { #approval-summary-page .summary-filters { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="content-wrapper" id="approval-summary-page">
@php($reportCompanyIds = \App\Services\ReportBranches::ids($company))
@include('company.reports.partials.branch_filter')
<div class="card">
    <div class="card-header"><h4 class="card-title">Approval Summary</h4></div>
    <div class="card-body">
        <p class="text-muted">Filter by approval date. Figures show the current status of items on those vouchers, including later returns and sales. Qty counts approval item rows; all weights are in grams.</p>
        <form method="GET" class="summary-filters mb-4">
            <div class="col-md-3"><label for="summary-customer">Customer</label><select name="customer_id" id="summary-customer" class="form-control"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}{{ count($reportCompanyIds) > 1 ? ' — '.\App\Services\ReportBranches::name($customer->company_id) : '' }}</option>@endforeach</select></div>
            <div class="col-md-2"><label for="summary-from">From date</label><input id="summary-from" type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
            <div class="col-md-2"><label for="summary-to">To date</label><input id="summary-to" type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
            <div class="col-md-3"><label for="summary-voucher">Voucher</label><input id="summary-voucher" name="voucher" value="{{ request('voucher') }}" class="form-control" placeholder="Approval voucher number"></div>
            <div class="summary-actions"><button class="btn btn-success">Filter</button> <a class="btn btn-secondary" href="{{ route('company.reports.approval-summary.index', $company->slug) }}">Reset</a></div>
        </form>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="summary-toolbar mb-3"><strong>Total vouchers: {{ number_format($totals['vouchers']) }}</strong><div class="d-flex flex-wrap" style="gap:10px"><a class="btn btn-success" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx', 'page' => null]) }}">Export Excel</a><a class="btn btn-primary" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}">Export CSV</a><a class="btn btn-info" href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'page' => null]) }}">Export PDF</a></div></div>
        <div class="row">
            @foreach(['issued' => 'Issued on Approval', 'returned' => 'Returned', 'sold' => 'Sold', 'pending' => 'Pending with Customer'] as $status => $title)
            <div class="col-md-6 col-xl-3 mb-3"><div class="summary-stat">
                <h5>{{ $title }}</h5><div class="summary-quantity">{{ number_format($totals[$status.'_qty']) }} <small>items</small></div>
                @foreach(['gross' => 'Gross', 'other' => 'Other', 'net' => 'Net', 'fine' => 'Fine'] as $key => $label)
                <div class="summary-weight"><span>{{ $label }} Wt</span><strong>{{ number_format($totals[$status.'_'.$key], 3) }} g</strong></div>
                @endforeach
            </div></div>
            @endforeach
        </div>
        <div class="table-responsive summary-table" tabindex="0" aria-label="Approval summary entries">
            <table class="table table-bordered text-nowrap">
                <thead><tr><th>Voucher</th><th>Date</th><th>Customer</th><th>Branch</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($rows as $row)<tr><td>{{ $row->approval_no }}</td><td>{{ \Carbon\Carbon::parse($row->approval_date)->format('d-m-Y') }}</td><td>{{ $row->customer_name ?? '-' }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id) }}</td>@foreach($metrics as $key => $label)<td class="text-end">{{ number_format($row->$key, str_ends_with($key, '_qty') ? 0 : 3) }}</td>@endforeach</tr>@empty<tr><td colspan="24" class="text-center">No approvals match these filters.</td></tr>@endforelse</tbody>
                <tfoot><tr><th colspan="4">Total (all matching vouchers)</th>@foreach($metrics as $key => $label)<th>{{ number_format($totals[$key], str_ends_with($key, '_qty') ? 0 : 3) }}</th>@endforeach</tr></tfoot>
            </table>
        </div>
        <div class="mt-3">{{ $rows->links('pagination::bootstrap-4') }}</div>
    </div>
</div>
</div>
@endsection
@push('scripts')
<script>$(function () { if ($.fn.select2) $('#summary-customer').select2({width:'100%',minimumResultsForSearch:0,dropdownParent:$('#approval-summary-page')}); });</script>
@endpush
