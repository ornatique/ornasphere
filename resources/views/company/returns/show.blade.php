@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
<h4 class="card-title">Edit Approval Return</h4>
<a href="{{ route('company.returns.index', $company->slug) }}" class="btn btn-primary">Back</a>
</div><div class="card-body">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php
$customers = collect([$return->approval?->customer, $return->sale?->customer])->merge($return->items->map(fn($row) => $row->approvalItem?->approval?->customer))->filter()->unique('id')->pluck('name')->implode(' / ');
@endphp
<p><strong>Voucher:</strong> {{ $return->return_voucher_no }} &nbsp; <strong>Customer:</strong> {{ $customers ?: '-' }}</p>
<form method="POST" action="{{ route('company.returns.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $return->id)]) }}">
@csrf @method('PUT')
<div class="row mb-3">
<div class="col-md-3"><label for="return_date">Return date</label><input id="return_date" type="date" name="return_date" class="form-control" required value="{{ old('return_date', \Carbon\Carbon::parse($return->return_date)->format('Y-m-d')) }}"></div>
<div class="col-md-9"><label for="remarks">Voucher remarks</label><textarea id="remarks" name="remarks" class="form-control" rows="2">{{ old('remarks', $return->remarks) }}</textarea></div>
</div>
<p>Select items to remove, then save. They will move back to pending approval. Item weights and prices are shown from the original approval.</p>
<div class="table-responsive"><table class="table table-bordered" style="min-width:1500px">
<thead><tr><th>Remove</th><th>Item / Label</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Purity</th><th>Waste %</th><th>Fine Wt</th><th>Metal Rate</th><th>Metal Amt</th><th>Labour Rate</th><th>Labour Amt</th><th>Other Amt</th><th>Total Amt</th></tr></thead>
<tbody>
@foreach($return->items as $row)
@php
$a = $row->approvalItem;
$set = $row->itemSet ?? $a?->itemSet ?? $a?->legacyItemSet;
@endphp
<tr class="return-row" data-amount="{{ (float)$row->return_amount }}">
<td><input type="checkbox" class="remove-item" name="remove_items[]" value="{{ $row->id }}" aria-label="Remove item {{ $row->id }}" @checked(in_array($row->id, old('remove_items', [])))></td>
<td>{{ $set?->item?->item_name ?? $a?->item?->item_name ?? '-' }}<br>{{ $set?->qr_code ?? $a?->qr_code ?? '-' }}</td>
@foreach(['gross_weight','other_weight','net_weight','purity','waste_percent','total_fine_weight','metal_rate','metal_amount','labour_rate','labour_amount','other_amount'] as $field)
<td>{{ number_format((float)($a?->$field ?? 0), in_array($field, ['metal_rate','metal_amount','labour_rate','labour_amount','other_amount']) ? 2 : 3) }}</td>
@endforeach
<td>Rs. {{ number_format((float)$row->return_amount, 2) }}</td>
</tr>
@endforeach
</tbody></table></div>
<p class="mt-3"><strong>Items remaining: <span id="remaining-count"></span> &nbsp; Updated total: Rs. <span id="remaining-total"></span></strong></p>
<div class="mb-3"><label for="removal_reason">Reason for removing items (required when removing)</label><textarea id="removal_reason" name="removal_reason" class="form-control" maxlength="500">{{ old('removal_reason') }}</textarea></div>
<button class="btn btn-primary" type="submit">Save Changes</button>
<a class="btn btn-danger" target="_blank" href="{{ route('company.returns.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string)$return->id)]) }}">View PDF</a>
</form></div></div></div>
<script>
(function () {
function recalc() {
let count = 0, amount = 0, removed = 0;
document.querySelectorAll('.return-row').forEach(function(row) {
const checked = row.querySelector('.remove-item').checked;
row.style.opacity = checked ? '0.45' : '1';
if (checked) removed++; else { count++; amount += Number(row.dataset.amount); }
});
document.getElementById('remaining-count').textContent = count;
document.getElementById('remaining-total').textContent = amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
document.getElementById('removal_reason').required = removed > 0;
}
document.querySelectorAll('.remove-item').forEach(function(input) { input.addEventListener('change', recalc); });
recalc();
})();
</script>
@endsection

