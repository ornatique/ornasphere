@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">@include('company.orders.nav')
@php($formLines = old('lines', isset($order) ? $order->lines->toArray() : [[]]))
<div class="card"><div class="card-body"><h3>{{ isset($order) ? 'Edit '.$order->number : 'New Customer Order' }}</h3>
<form method="POST" action="{{ isset($order) ? route('company.orders.draft.update', [$company->slug, $order->id]) : route('company.orders.store', $company->slug) }}">@csrf
@isset($order) @method('PUT') @endisset
<div class="row">
<div class="col-md-6 form-group"><label>Customer *</label><select name="customer_id" class="form-control" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $order->customer_id ?? '') == $customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
<div class="col-md-3 form-group"><label>Order date *</label><input type="date" name="order_date" class="form-control" value="{{ old('order_date', $order->order_date ?? date('Y-m-d')) }}" required></div>
<div class="col-md-3 form-group"><label>Expected delivery</label><input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', $order->delivery_date ?? '') }}"></div>
<div class="col-md-6 form-group"><label>Customer reference</label><input name="customer_reference" class="form-control" maxlength="255" value="{{ old('customer_reference', $order->customer_reference ?? '') }}"></div>
<div class="col-md-6 form-group"><label>Remarks</label><textarea name="remarks" class="form-control" maxlength="5000">{{ old('remarks', $order->remarks ?? '') }}</textarea></div>
</div>
<h4>Order items</h4><p>Expected weight is total finished jewellery net weight in grams. Upload reference images after saving.</p>
<div id="order-lines">
@foreach($formLines as $index => $line)
@include('company.orders.line_fields', ['index' => $index, 'line' => $line])
@endforeach
</div>
<button type="button" id="add-order-line" class="btn btn-secondary">Add Item</button>
<button class="btn btn-primary">Save Draft Order</button>
</form>
<template id="order-line-template">@include('company.orders.line_fields', ['index' => '__INDEX__', 'line' => []])</template>
</div></div></div>
<script>
(() => {
 const rows = document.getElementById('order-lines');
 let next = {{ count($formLines) ? max(array_keys($formLines)) + 1 : 0 }};
 document.getElementById('add-order-line').addEventListener('click', () => {
   if (rows.children.length >= 100) return;
   rows.insertAdjacentHTML('beforeend', document.getElementById('order-line-template').innerHTML.replaceAll('__INDEX__', next++));
 });
 rows.addEventListener('click', event => { if(event.target.matches('[data-remove-line]') && rows.children.length > 1) event.target.closest('fieldset').remove(); });
 rows.addEventListener('change', event => {
   if (!event.target.matches('[data-item]')) return;
   const row = event.target.closest('fieldset'), option = event.target.selectedOptions[0];
   row.querySelector('[data-metal]').value = (option.dataset.metal || '').toLowerCase();
   row.querySelector('[data-purity]').value = option.dataset.purity || '';
 });
})();
</script>
@endsection
