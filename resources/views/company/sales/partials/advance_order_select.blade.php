@if(\Illuminate\Support\Facades\Route::has('company.orders.show') && isset($availableOrders) && $availableOrders->isNotEmpty())
<div class="col-12 mb-3">
    <label>Customer Order (optional)</label>
    <select name="order_id" class="form-select">
        <option value="">No order — customer advance only</option>
        @foreach($availableOrders as $availableOrder)
        <option value="{{ $availableOrder->id }}" @selected((string) old('order_id', $linkedOrder?->id) === (string) $availableOrder->id)>
            {{ $availableOrder->number }} — {{ $availableOrder->customer->name }}
        </option>
        @endforeach
    </select>
    <small>Select an order for this same customer to show this voucher in Order Management.</small>
</div>
@endif
