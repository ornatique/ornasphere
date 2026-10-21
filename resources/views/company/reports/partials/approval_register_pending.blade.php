<h3 style="margin-top:24px">Pending Approval — {{ $pendingItems->count() }} items</h3>
<p class="note">Current pending items from approvals matching the customer, voucher, search and issue-date filters. This is a current status snapshot, not a historical closing balance; the transaction type filter does not restrict this section.</p>
<div class="table-responsive">
<table class="table table-bordered">
<thead><tr><th>Date</th><th>Approval Voucher</th><th>Customer</th><th>Item</th><th>Label / QR</th><th>HUID</th><th>Status</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Fine Wt</th><th>Amount</th></tr></thead>
<tbody>
@forelse($pendingItems as $item)
<tr><td>{{ \Carbon\Carbon::parse($item->entry_date)->format('d-m-Y') }}</td><td>{{ $item->voucher_no }}</td><td>{{ $item->customer_name }}</td><td>{{ $item->item_name ?? '-' }}</td><td>{{ $item->qr_code ?: '-' }}</td><td>{{ $item->huid ?: '-' }}</td><td>Pending</td>
@foreach(['gross','other','net','fine','amount'] as $key)
<td class="number text-end">{{ number_format($item->$key, $key === 'amount' ? 2 : 3) }}</td>
@endforeach
</tr>
@empty
<tr><td colspan="12">No pending approval items match these filters.</td></tr>
@endforelse
</tbody></table>
</div>
