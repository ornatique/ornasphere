@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Order Management</h3>
            <a class="btn btn-primary" href="{{ route('company.order-management.create', $company->slug) }}">Add Order</a>
        </div>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <form id="order-list-filters" method="GET" action="{{ route('company.order-management.index', $company->slug) }}" class="row mb-3" role="search">
            <div class="col-md-3 col-lg-2 mb-2"><label for="order-list-from">From Date</label>
                <input id="order-list-from" name="from_date" type="date" class="form-control" value="{{ $fromDate }}">
            </div>
            <div class="col-md-3 col-lg-2 mb-2"><label for="order-list-to">To Date</label>
                <input id="order-list-to" name="to_date" type="date" class="form-control" value="{{ $toDate }}">
            </div>
            <div class="col-md-6 col-lg-3 mb-2"><label for="order-list-customer">Customer</label>
                <select id="order-list-customer" name="customer_id" class="form-control"><option value="">All Customers</option>
                    @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-12 col-lg-5 mb-2"><label for="order-list-search">Search</label>
                <input id="order-list-search" class="form-control" name="search" value="{{ $search }}" placeholder="Customer or Order number (e.g. OM-000001)" aria-label="Search customer or order number" maxlength="100" autocomplete="off">
            </div>
        </form>
        <p id="order-list-status" role="status" aria-live="polite"></p>
        <div id="order-list-results">@include('company.order_management.list')</div>
    </div></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset(ltrim(trim((string) config('app.order_management_asset_prefix', ''), '/').'/js/order-management-list.js', '/')) }}?v=2"></script>
@endpush
