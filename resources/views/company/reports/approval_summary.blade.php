@extends('company_layout.admin')
@section('content')
<div class="card">
    <div class="card-header"><h4 class="card-title">Approval Summary</h4></div>
    <div class="card-body">
        <p class="text-muted">Filter by approval date. Figures show the current status of items on those vouchers, including later returns and sales. Qty counts approval item rows; all weights are in grams.</p>
        <form method="GET" class="row mb-4">
            <div class="col-md-3"><label for="summary-customer">Customer</label><select name="customer_id" id="summary-customer" class="form-control"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label for="summary-from">From date</label><input id="summary-from" type="date" name="from_date" value="{{ request('from_date') }}" class="form-control"></div>
            <div class="col-md-2"><label for="summary-to">To date</label><input id="summary-to" type="date" name="to_date" value="{{ request('to_date') }}" class="form-control"></div>
            <div class="col-md-3"><label for="summary-voucher">Voucher</label><input id="summary-voucher" name="voucher" value="{{ request('voucher') }}" class="form-control" placeholder="Approval voucher number"></div>
            <div class="col-md-2 pt-4"><button class="btn btn-success">Filter</button> <a class="btn btn-secondary" href="{{ route('company.reports.approval-summary.index', $company->slug) }}">Reset</a></div>
        </form>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="d-flex justify-content-between mb-3"><strong>Total vouchers: {{ number_format($totals['vouchers']) }}</strong><a class="btn btn-primary" href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}">Export CSV</a></div>
        <div class="row">
            @foreach(['issued' => 'Issued on Approval', 'returned' => 'Returned', 'sold' => 'Sold', 'pending' => 'Pending with Customer'] as $status => $title)
            <div class="col-md-6 col-xl-3 mb-3"><div class="border rounded p-3 h-100">
                <h5>{{ $title }}</h5><div class="h3">{{ number_format($totals[$status.'_qty']) }} <small>items</small></div>
                @foreach(['gross' => 'Gross', 'other' => 'Other', 'net' => 'Net', 'fine' => 'Fine'] as $key => $label)
                <div class="d-flex justify-content-between"><span>{{ $label }} Wt</span><strong>{{ number_format($totals[$status.'_'.$key], 3) }} g</strong></div>
                @endforeach
            </div></div>
            @endforeach
        </div>
        <div class="table-responsive" style="max-height:65vh;overflow:auto">
            <table class="table table-bordered text-nowrap">
                <thead><tr><th>Voucher</th><th>Date</th><th>Customer</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($rows as $row)<tr><td>{{ $row->approval_no }}</td><td>{{ \Carbon\Carbon::parse($row->approval_date)->format('d-m-Y') }}</td><td>{{ $row->customer_name ?? '-' }}</td>@foreach($metrics as $key => $label)<td class="text-end">{{ number_format($row->$key, str_ends_with($key, '_qty') ? 0 : 3) }}</td>@endforeach</tr>@empty<tr><td colspan="23" class="text-center">No approvals match these filters.</td></tr>@endforelse</tbody>
                <tfoot><tr><th colspan="3">Total (all matching vouchers)</th>@foreach($metrics as $key => $label)<th>{{ number_format($totals[$key], str_ends_with($key, '_qty') ? 0 : 3) }}</th>@endforeach</tr></tfoot>
            </table>
        </div>
        <div class="mt-3">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
@push('scripts')
<script>$(function () { if ($.fn.select2) $('#summary-customer').select2({theme:'bootstrap4',width:'100%'}); });</script>
@endpush
