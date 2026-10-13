@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
@include('company.orders.nav')
<div class="card"><div class="card-body">
<h3>Customer Orders</h3>
<p>Customer Order → CAD Design &amp; Approval → RPT Production → Ready for Casting</p>
<a class="btn btn-primary mb-3" href="{{ route('company.orders.create', $company->slug) }}">New Customer Order</a>
<form method="GET" class="mb-3"><label for="order-search">Order number or customer</label><input id="order-search" class="form-control" name="search" value="{{ request('search') }}" maxlength="100"><button class="btn btn-secondary mt-2">Search</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Order date</th><th>Delivery</th><th>Progress</th><th>Accepted / Ordered pcs</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td><a href="{{ route('company.orders.show', [$company->slug, $order->id]) }}">{{ $order->number }}</a></td><td>{{ $order->customer->name }}</td><td>{{ $order->order_date }}</td><td>{{ $order->delivery_date ?: '—' }}</td><td>{{ ucwords(str_replace('_', ' ', $order->progress)) }}</td><td>{{ $order->lines->sum('accepted') }} / {{ $order->lines->sum('pieces') }}</td></tr>
@empty<tr><td colspan="6">No orders found.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}
</div></div></div>
@endsection
