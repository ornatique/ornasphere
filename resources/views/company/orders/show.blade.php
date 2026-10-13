@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">@include('company.orders.nav')
<div class="card mb-3"><div class="card-body">
<h3>{{ $order->number }} · {{ $order->customer->name }}</h3>
@unless(in_array($order->status, ['on_hold','cancelled']))
@include('company.orders.steps', ['stepLabel' => 'Order progress', 'steps' => ['Review order', 'CAD design & approval', 'RPT production', 'Ready for casting'], 'currentStep' => match($order->progress) { 'draft' => 0, 'in_rpt' => 2, 'ready_for_casting' => 3, default => 1 }])
@endunless
@if($order->status === 'draft')<a class="btn btn-secondary mb-2" href="{{ route('company.orders.edit', [$company->slug, $order->id]) }}">Edit Draft</a>@endif
<p>Progress: <strong>{{ ucwords(str_replace('_', ' ', $order->progress)) }}</strong> · Ordered {{ $order->order_date }} · Delivery {{ $order->delivery_date ?: '—' }}</p>
<p>Reference: {{ $order->customer_reference ?: '—' }}<br>{{ $order->remarks }}</p>
@if($order->status !== 'cancelled')
<form method="POST" action="{{ route('company.orders.update', [$company->slug, $order->id]) }}" class="mb-3">@csrf @method('PUT')
@if($order->status === 'draft')
<div class="production-next"><h4>Step 1 · Review your order</h4><p>Check the customer, items, pieces and expected weight below. Confirm when these details are correct to start CAD design.</p><button name="status" value="confirmed" class="btn btn-primary">Confirm Order &amp; Start CAD</button></div>
@elseif($order->status === 'on_hold')
<div class="production-next"><p>This order is on hold. Resume it to continue production.</p><button name="status" value="confirmed" class="btn btn-primary">Resume Order</button></div>
@endif
<details class="mt-3"><summary>Other order actions</summary><div class="production-actions">
@if($order->status === 'confirmed')<button name="status" value="on_hold" class="btn btn-outline-secondary">Put Order on Hold</button>@endif
@if($order->lines->flatMap->designs->flatMap->batches->isEmpty())<button name="status" value="cancelled" class="btn btn-outline-danger" onclick="return confirm('Cancel this order? Existing vouchers will be retained.')">Cancel Order</button>@endif
</div></details></form>
@endif
</div></div>
@foreach($order->lines as $line)
<div class="card mb-3 order-item-detail"><div class="card-body">
<div class="order-item-heading"><h4>{{ $loop->iteration }}. {{ $line->item_name }}</h4><span class="order-item-status">{{ ucwords(str_replace('_', ' ', in_array($order->status, ['draft','on_hold','cancelled']) ? $order->status : $line->progress)) }}</span></div>
<h5>Item specifications</h5>
<dl class="order-item-fields">
<div><dt>Metal</dt><dd>{{ ucfirst($line->metal) }}</dd></div>
<div><dt>Required purity</dt><dd>{{ number_format($line->purity, 3) }}%</dd></div>
<div><dt>Ordered pieces</dt><dd>{{ number_format($line->pieces) }} pcs</dd></div>
<div><dt>Expected total net weight</dt><dd>{{ number_format($line->expected_weight, 3) }} g</dd></div>
<div><dt>Size / length</dt><dd>{{ $line->size ?: '—' }}</dd></div>
</dl>
<div class="order-item-instructions"><h5>Design instructions</h5><p>{{ $line->instructions ?: 'No instructions added.' }}</p></div>
<h5>Pattern production</h5>
<dl class="order-item-fields order-item-counts">
<div><dt>Accepted patterns</dt><dd>{{ number_format($line->accepted) }} <small>/ {{ number_format($line->pieces) }} required</small></dd></div>
<div><dt>Patterns still required</dt><dd>{{ number_format(max(0, $line->pieces - $line->accepted)) }}</dd></div>
<div><dt>Rejected patterns</dt><dd>{{ number_format($line->designs->flatMap->batches->sum('rejected_pieces')) }}</dd></div>
</dl>
<details class="mb-3"><summary>Reference images</summary>@include('company.orders.files', ['fileList' => $files->where('customer_order_line_id', $line->id), 'kinds' => ['reference'], 'lineId' => $line->id, 'canUpload' => !in_array($order->status, ['cancelled','on_hold'])])</details>
@if($line->designs->isNotEmpty())
<details class="mb-3"><summary>CAD revisions &amp; RPT history</summary>
<div class="table-responsive"><table class="table"><thead><tr><th>CAD revision</th><th>Designer</th><th>Status</th><th>RPT batches</th></tr></thead><tbody>@foreach($line->designs as $design)<tr><td><a href="{{ route('company.cad.show', [$company->slug, $design->id]) }}">{{ $design->number }}</a></td><td>{{ $design->designer?->name }}</td><td>{{ ucwords(str_replace('_',' ', $design->status)) }}</td><td>@foreach($design->batches as $batch)<a href="{{ route('company.rpt.show', [$company->slug, $batch->id]) }}">{{ $batch->number }}</a> ({{ $batch->accepted_pieces }} accepted)<br>@endforeach</td></tr>@endforeach</tbody></table></div>
</details>@endif
@php($latestDesign = $line->designs->sortByDesc('revision')->first())
@if($order->status === 'confirmed' && $latestDesign && $latestDesign->status !== 'revision_requested')
<div class="production-next"><h4>Your next step</h4>
<p>{{ match($latestDesign->status) { 'assigned' => 'Your designer is assigned. Open the CAD record to start designing.', 'designing' => 'Continue the current design: upload the print file, enter the weight and send it for approval.', 'submitted' => 'The design is waiting for review. Open it to record approval or request changes.', default => 'The design is approved. Open it to plan RPT production or view its batches.' } }}</p>
<a class="btn btn-primary" href="{{ route('company.cad.show', [$company->slug, $latestDesign->id]) }}">{{ match($latestDesign->status) { 'assigned' => 'Start CAD Design', 'designing' => 'Continue CAD Design', 'submitted' => 'Review CAD Design', default => 'Continue to RPT' } }}</a></div>
@endif
@if($order->status === 'confirmed' && (!$latestDesign || in_array($latestDesign->status, ['approved','revision_requested'])))
@if($latestDesign && $latestDesign->status === 'approved')<details class="mt-3"><summary>Need to change the approved design?</summary><p>Create another revision only when the design must change. Existing RPT batches keep their original revision.</p>@endif
<h4>{{ $latestDesign ? 'Assign the next CAD revision' : 'Step 2 · Assign a CAD designer' }}</h4>
<form method="POST" action="{{ route('company.cad.store', [$company->slug, $line->id]) }}">@csrf
<div class="row"><div class="col-md-6"><label>Assign designer</label><select name="designer_id" class="form-control" required><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div class="col-md-6"><label>Design due date</label><input type="date" name="due_date" class="form-control"></div></div>
<button class="btn btn-primary mt-2">{{ $latestDesign ? 'Create New Revision' : 'Assign Designer & Create CAD' }}</button></form>
@if($latestDesign && $latestDesign->status === 'approved')</details>@endif
@endif
</div></div>
@endforeach
<style>
.order-item-detail .order-item-heading{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:24px}.order-item-detail .order-item-heading h4{margin:0;overflow-wrap:anywhere}.order-item-detail .order-item-status{border:1px solid #80808080;border-radius:20px;padding:6px 12px;font-weight:600}.order-item-detail .order-item-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:0 0 24px}.order-item-detail .order-item-fields>div{padding:14px 16px;border:1px solid #80808040;border-radius:8px;background:#8080800a;min-width:0}.order-item-detail dt{font-size:13px;font-weight:400;margin-bottom:8px}.order-item-detail dd{font-size:17px;font-weight:600;margin:0;overflow-wrap:anywhere}.order-item-detail dd small{font-size:12px;font-weight:400}.order-item-detail .order-item-instructions{border-left:3px solid #80808080;padding:4px 16px;margin-bottom:24px}.order-item-detail .order-item-instructions p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere}.order-item-detail h5{margin-bottom:12px}.order-item-detail .order-item-counts{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}@media(max-width:480px){.order-item-detail .order-item-fields{grid-template-columns:1fr}}
</style>
<div class="card mb-3"><div class="card-body"><h4>Linked Receive / Return / Purchase vouchers</h4>
@if($order->status !== 'cancelled' && $unlinkedVouchers->isNotEmpty())
<form method="POST" action="{{ route('company.orders.vouchers.update', [$company->slug, $order->id]) }}" class="mb-3">
@csrf
<label>Link an existing voucher for {{ $order->customer->name }}</label>
<select name="voucher_id" class="form-control mb-2" required>
<option value="">Select an existing voucher</option>
@foreach($unlinkedVouchers as $existingVoucher)
<option value="{{ $existingVoucher->id }}">{{ $existingVoucher->voucher_no }} · {{ $existingVoucher->voucher_date?->format('d-m-Y') }} · {{ str_replace('_', ' ', $existingVoucher->entry_type) }} · Cash in/out {{ number_format($existingVoucher->cash_in, 2) }}/{{ number_format($existingVoucher->cash_out, 2) }} · {{ $existingVoucher->metal_type }} fine in/out {{ number_format($existingVoucher->metal_in, 3) }}/{{ number_format($existingVoucher->metal_out, 3) }} g</option>
@endforeach
</select>
<p>This links a payment already recorded for this customer. It does not receive the payment again.</p>
<button class="btn btn-secondary">Link Existing Voucher</button>
</form>
@endif
<p>Entries use the existing customer ledger. Amounts below are voucher movements, not the remaining advance balance. Billing continues to use the existing customer advance adjustment.</p>
@if($order->status !== 'cancelled')<a class="btn btn-primary mb-3" href="{{ route('company.sales.advance.create', ['slug' => $company->slug, 'order_id' => $order->id]) }}">Receive Advance / Add Voucher</a>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>Voucher</th><th>Type</th><th>Cash in / out</th><th>Metal</th><th>Fine in / out (g)</th></tr></thead><tbody>@forelse($order->vouchers as $voucher)<tr><td><a href="{{ route('company.sales.advance.voucher.pdf', [$company->slug, Illuminate\Support\Facades\Crypt::encryptString((string)$voucher->id)]) }}">{{ $voucher->voucher_no }}</a></td><td>{{ str_replace('_',' ', $voucher->entry_type) }}</td><td>{{ $voucher->cash_in }} / {{ $voucher->cash_out }}</td><td>{{ $voucher->metal_type }}</td><td>{{ $voucher->metal_in }} / {{ $voucher->metal_out }}</td></tr>@empty<tr><td colspan="5">No linked vouchers.</td></tr>@endforelse</tbody></table></div>
</div></div>
<div class="card"><div class="card-body">
<h4 id="order-activity-title">Activity history</h4>
<div role="region" aria-labelledby="order-activity-title" tabindex="0" style="max-height:320px;overflow-y:auto;overscroll-behavior-y:contain;scrollbar-gutter:stable;overflow-wrap:anywhere;">
<ul class="mb-0">@forelse($events as $event)<li>{{ $event->created_at }} · {{ $event->description }} · User #{{ $event->user_id }}</li>@empty<li>No activity yet.</li>@endforelse</ul>
</div>
</div></div>
</div>
@endsection
