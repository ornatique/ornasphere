<div class="voucher-heading"><strong>{{ $number }}</strong><span>Date: {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</span><span>{{ $lines->count() }} items</span></div>
<div class="row mb-3">
    <div class="col-6 col-lg-3 mb-2"><div class="voucher-stat"><div>Quantity (items)</div><strong class="h4">{{ number_format($lines->count()) }}</strong></div></div>
    @foreach(['gross_weight'=>'Gross Wt', 'other_weight'=>'Other Wt', 'net_weight'=>'Net Wt'] as $field=>$label)
    <div class="col-6 col-lg-3 mb-2"><div class="voucher-stat"><div>{{ $label }}</div><strong class="h4">{{ number_format($lines->sum(fn($line)=>(float)$line['source']->$field),3) }} <small>g</small></strong></div></div>
    @endforeach
</div>
<div class="mb-3">
    <label for="voucher-barcode-search">Search barcode / label</label>
    <input id="voucher-barcode-search" type="search" class="form-control" placeholder="Type or scan barcode / label" autocomplete="off">
    <small id="voucher-search-count" aria-live="polite">Showing {{ $lines->count() }} items. Totals above are for all voucher items.</small>
</div>
<p>Items related to the selected approval voucher are shown below.</p>
@if($voucher->remarks)<p><strong>Remarks:</strong> {{ $voucher->remarks }}</p>@endif
<div class="table-responsive"><table class="table table-bordered text-nowrap">
<thead><tr><th>#</th><th>Label</th><th>Item</th><th>Approval</th><th>Customer</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Purity</th><th>Fine Wt</th><th>Amount</th><th>Remarks</th></tr></thead>
<tbody>
@foreach($lines as $line)
@php
    $a = $line['approval']; $s = $line['source'];
    $set = $a->itemSet ?? $a->legacyItemSet;
    $item = $set?->item ?? $a->item;
@endphp
<tr class="voucher-detail-row" data-barcode="{{ implode(' ', array_filter([$a->qr_code, $set?->qr_code, $set?->barcode, $a->huid, $set?->HUID])) }}"><td>{{ $loop->iteration }}</td><td>{{ $a->qr_code ?? $set?->qr_code ?? '-' }}</td><td>{{ $item?->item_name ?? '-' }}</td><td>{{ $a->approval?->approval_no }}</td><td>{{ $a->approval?->customer?->name ?? '-' }}</td>
@foreach(['gross_weight','other_weight','net_weight','purity'] as $field)<td>{{ number_format((float)$s->$field,3) }}</td>@endforeach
<td>{{ number_format((float)($type === 'sale' ? $s->fine_weight : $s->total_fine_weight),3) }}</td><td>{{ number_format((float)$line['amount'],2) }}</td><td>{{ $s->remarks ?? '-' }}</td></tr>
@endforeach
<tr id="voucher-search-empty" hidden><td colspan="12" class="text-center">No matching barcode / label.</td></tr>
</tbody>
<tfoot><tr><th colspan="5">Total</th>@foreach(['gross_weight','other_weight','net_weight'] as $field)<th>{{ number_format($lines->sum(fn($line)=>(float)$line['source']->$field),3) }}</th>@endforeach<th>-</th><th>{{ number_format($lines->sum(fn($line)=>(float)($type === 'sale' ? $line['source']->fine_weight : $line['source']->total_fine_weight)),3) }}</th><th>{{ number_format($lines->sum('amount'),2) }}</th><th></th></tr></tfoot>
</table></div>
