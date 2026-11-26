@extends('company_layout.admin')
@push('styles')
<style>
.order-search-wrap{position:relative}
#order-customer-picker,#order-item-picker{position:relative}
#order-customer-picker .select2-results>.select2-results__options,#order-item-picker .select2-results>.select2-results__options{max-height:200px;overflow-y:auto;overscroll-behavior:contain}
#order-customer-picker .select2-search__field,#order-item-picker .select2-search__field{width:100%}
#new-customer .new-customer-scroll{max-height:220px;overflow-y:auto;overflow-x:hidden;padding-right:8px;overscroll-behavior:contain;scrollbar-gutter:stable}
#new-customer .form-group{margin-bottom:12px}
#new-customer .form-control{height:38px;padding:8px 12px}
#new-customer .new-customer-scroll>p{font-size:12px;margin-bottom:10px}
@media(max-width:767px){#new-customer .new-customer-scroll{max-height:min(260px,45vh)}}
#item-results{position:absolute;top:100%;left:15px;right:15px;z-index:1050;max-height:280px;overflow-y:auto;box-shadow:0 5px 12px #0002}
#item-results[hidden]{display:none}
#item-results button{text-align:left;padding:9px 16px;background:var(--bs-body-bg, #2b2e47);color:inherit;border:1px solid #ffffff25}
#item-results button:hover,#item-results button:focus{background:#4b5fc0;color:white}
.order-summary{display:flex;flex-wrap:wrap;gap:16px;margin:16px 0}
.order-summary-chip{min-width:170px;padding:8px 12px;border:1px solid #ffffff20;border-radius:8px;background:#88888812}
.order-summary-chip span{display:block;font-size:12px}.order-summary-chip strong{display:block;font-size:24px}
#selected-items td{vertical-align:middle}#selected-items .order-label-name{display:block;font-size:12px;margin-top:4px}
#selected-items .order-weight{min-width:105px}#selected-items .form-control{padding:10px}
[data-order-apply]{padding:2px 14px;font-size:10px;line-height:1.2;margin-left:5px}
#order-image-preview{border:0;border-radius:12px;padding:0;width:800px;max-width:90vw;max-height:90vh;background:#fff;color:#263247;box-shadow:0 15px 60px #0006}
#order-image-preview::backdrop{background:rgba(0,0,0,.7)}
.order-preview-heading{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #ddd;gap:16px}
.order-preview-heading h4{margin:0;overflow-wrap:anywhere}.order-preview-body{padding:20px;text-align:center}
#order-preview-image{max-width:100%;max-height:70vh;object-fit:contain}
</style>
@endpush
@section('content')
<div class="content-wrapper"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between mb-4"><h3>{{ $order ? 'Edit Order '.$order->number : 'Add Order' }}</h3><a href="{{ route('company.order-management.index', $company->slug) }}" class="btn btn-secondary">Order List</a></div>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form id="managed-order-form" method="POST" action="{{ $order ? route('company.order-management.update', [$company->slug, $order->id]) : route('company.order-management.store', $company->slug) }}">
        @csrf @if($order) @method('PUT') @endif
        <div class="border rounded p-3 mb-3 d-flex flex-wrap align-items-center" style="gap:16px">
            <strong>Order price:</strong>
            <label class="mb-0"><input type="radio" id="order-price-sale" name="price_mode" value="sale" @checked($priceMode === 'sale')> Sale Price</label>
            @if($wholesaleEnabled)<label class="mb-0"><input type="radio" id="order-price-wholesale" name="price_mode" value="wholesale" @checked($priceMode === 'wholesale')> Wholesale Price</label>@endif
            <small class="ml-auto">Choose the fixed price for items that have both prices. If unavailable, the other available price is used.</small>
        </div>
        <div class="row">
            <div class="col-md-8 form-group" id="order-customer-picker"><label for="order-customer">Customer *</label><select id="order-customer" name="customer_id" class="form-control" required><option value="">Select customer</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $order?->customer_id) == $customer->id)>{{ $customer->name }}</option>@endforeach
                <option value="other" @selected(old('customer_id') === 'other')>Other — Create new customer</option></select></div>
            <div class="col-md-4 form-group"><label for="order-date">Order date *</label><input id="order-date" type="date" name="order_date" class="form-control" required value="{{ old('order_date', $order?->order_date?->format('Y-m-d') ?? date('Y-m-d')) }}"></div>
        </div>
        <fieldset id="new-customer" class="border rounded p-3 mb-4" hidden><legend class="h5">New Customer</legend>
            <div class="new-customer-scroll" role="region" aria-label="New customer details" tabindex="0">
            <p>Saving this order will also create and select this customer.</p>
            <div class="row">
                <div class="col-md-6 form-group"><label for="customer-name">Customer name *</label><input id="customer-name" name="new_customer[name]" class="form-control" maxlength="255" value="{{ old('new_customer.name') }}"></div>
                <div class="col-md-6 form-group"><label for="customer-email">Email</label><input id="customer-email" type="email" name="new_customer[email]" class="form-control" maxlength="255" value="{{ old('new_customer.email') }}"></div>
                <div class="col-md-6 form-group"><label for="customer-mobile">Mobile number</label><input id="customer-mobile" name="new_customer[mobile_no]" class="form-control" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" value="{{ old('new_customer.mobile_no') }}"></div>
                <div class="col-md-6 form-group"><label for="customer-city">City</label><input id="customer-city" name="new_customer[city]" class="form-control" maxlength="191" value="{{ old('new_customer.city') }}"></div>
                <div class="col-md-9 form-group"><label for="customer-address">Address</label><input id="customer-address" name="new_customer[address]" class="form-control" maxlength="2000" autocomplete="off" value="{{ old('new_customer.address') }}"></div>
                <div class="col-md-3 form-group"><label for="customer-pincode">Pincode</label><input id="customer-pincode" name="new_customer[pincode]" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="{{ old('new_customer.pincode') }}"></div>
            </div>
            </div>
        </fieldset>
        <div class="row">
            <div class="col-md-5 form-group" id="order-item-picker"><label for="item-filter">Item name</label><select id="item-filter" class="form-control"><option value="">All Items</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->item_name }}</option>@endforeach</select></div>
            <div class="col-md-7 form-group order-search-wrap"><label for="item-scan">Scan / Search (HUID / QR)</label><input id="item-scan" class="form-control" placeholder="Scan or enter HUID / QR / item name" maxlength="100" autocomplete="off" aria-controls="item-results" aria-expanded="false"><div id="item-results" class="list-group" hidden></div></div>
        </div>
        <p id="search-status" role="status" aria-live="polite"></p>
        <div class="form-group"><label for="order-remarks">Remarks</label><textarea id="order-remarks" name="remarks" class="form-control" maxlength="5000">{{ old('remarks', $order?->remarks) }}</textarea></div>
        <div class="order-summary" id="order-totals" aria-live="polite">
            <div class="order-summary-chip"><span>Total Count</span><strong id="order-total-count">0</strong></div>
            <div class="order-summary-chip"><span>Total Gross Wt</span><strong id="order-total-gross">0.000</strong></div>
            <div class="order-summary-chip"><span>Total Other Wt</span><strong id="order-total-other">0.000</strong></div>
            <div class="order-summary-chip"><span>Total Net Wt</span><strong id="order-total-net">0.000</strong></div>
            <div class="order-summary-chip"><span>Total Amount</span><strong id="order-total-amount">0.00</strong></div>
        </div>
        <div class="d-flex justify-content-end mb-2"><input id="selected-item-search" class="form-control" style="max-width:280px" placeholder="Search Item Name" aria-label="Search selected items"></div>
        <div class="table-responsive"><table class="table table-bordered"><thead><tr>
            <th>Label</th><th>Qty</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th>
            <th>Purity <button type="button" class="btn btn-info btn-sm" data-order-apply="purity">All</button></th>
            <th>Waste % <button type="button" class="btn btn-info btn-sm" data-order-apply="waste_percent">All</button></th>
            <th>Net Purity</th><th>Fine Wt</th>
            <th>Metal Rate <button type="button" class="btn btn-info btn-sm" data-order-apply="metal_rate">All</button></th><th>Metal Amt</th>
            <th>Labour Rate <button type="button" class="btn btn-info btn-sm" data-order-apply="labour_rate">All</button></th><th>Labour Amt</th>
            <th>Other Amt <button type="button" class="btn btn-info btn-sm" data-order-apply="other_amount">All</button></th><th id="order-sale-heading">Sale Price</th><th id="order-wholesale-heading" hidden>Wholesale Price</th><th>Total Amt</th><th>Remarks</th><th>Action</th><th>Image</th>
        </tr></thead><tbody id="selected-items"></tbody></table></div>
        <p class="text-muted">This order does not reserve or reduce stock.</p>
        <button id="save-order" class="btn btn-success" type="submit">{{ $order ? 'Update Order' : 'Save Order' }}</button>
    </form>
</div></div></div>
<dialog id="order-image-preview" aria-labelledby="order-preview-title">
    <div class="order-preview-heading"><h4 id="order-preview-title">Item image</h4><button id="order-preview-close" type="button" class="btn btn-sm btn-secondary" aria-label="Close image preview">Close ×</button></div>
    <div class="order-preview-body"><img id="order-preview-image" alt="Item image"><p id="order-preview-message" role="status"></p></div>
</dialog>
@endsection
@include('company.customers.partials.address_autofill', ['addressFieldId' => 'customer-address', 'cityFieldId' => 'customer-city', 'pincodeFieldId' => 'customer-pincode'])
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.OrderManagement.init({rows: @json($rows), priceMode: @json($priceMode), searchUrl: @json(route($order ? 'company.order-management.items.edit' : 'company.order-management.items.create', $company->slug))});
});
</script>
<script src="{{ asset(ltrim(trim((string) config('app.order_management_asset_prefix', ''), '/').'/js/order-management.js', '/')) }}?v=6&amp;customer_dropdown=1&amp;item_dropdown=1&amp;price_display=3"></script>
@endpush
