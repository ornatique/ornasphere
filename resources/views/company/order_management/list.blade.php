<div class="table-responsive"><table class="table">
    <thead><tr><th>Order number</th><th>Date</th><th>Customer</th><th>Items</th><th>Action</th></tr></thead>
    <tbody>@forelse($orders as $order)
        <tr><td>{{ $order->number }}</td><td>{{ $order->order_date->format('d-m-Y') }}</td><td>{{ $order->customer?->name ?? 'Customer unavailable' }}</td><td>{{ $order->items_count }}</td>
        <td><a class="btn btn-sm btn-primary" href="{{ route('company.order-management.edit', [$company->slug, $order->id]) }}">Edit</a>
            <a class="btn btn-sm btn-info" href="{{ route('company.order-management.pdf', [$company->slug, $order->id]) }}" target="_blank" rel="noopener">PDF</a></td></tr>
    @empty<tr><td colspan="5" class="text-center">No orders found.</td></tr>@endforelse</tbody>
</table></div>
{{ $orders->links() }}
