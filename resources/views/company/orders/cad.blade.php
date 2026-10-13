@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">@include('company.orders.nav')<div class="card"><div class="card-body">
<h3>{{ $design->number }} · {{ ucwords(str_replace('_',' ', $design->status)) }}</h3>
@include('company.orders.steps', ['stepLabel' => 'CAD progress', 'steps' => ['Start design', 'Prepare files & weight', 'Review & approve', 'Plan RPT'], 'currentStep' => match($design->status) { 'assigned' => 0, 'designing' => 1, 'approved' => 3, default => 2 }])
<p><a href="{{ route('company.orders.show', [$company->slug, $order->id]) }}">{{ $order->number }}</a> · {{ $order->customer->name }} · {{ $design->line->item_name }}</p>
<p>{{ ucfirst($design->line->metal) }} · {{ $design->line->purity }}% purity · Size {{ $design->line->size ?: '—' }} · {{ $design->line->pieces }} pcs · Due {{ $design->due_date ?: '—' }}</p>
<p>{{ $design->line->instructions }}</p><p>Designer: {{ $design->designer?->name }} · Estimated finished wt: {{ $design->weight_per_piece ?: '—' }} g per piece</p>
<p>{{ $design->notes }}</p>
@php($hasPrint = $files->where('kind', 'print')->isNotEmpty())
@if($order->status !== 'confirmed')
<div class="production-next">This order is {{ str_replace('_', ' ', $order->status) }}. <a href="{{ route('company.orders.show', [$company->slug, $order->id]) }}">Return to the order</a> to check its status before continuing.</div>
@elseif($design->status === 'assigned')
<div class="production-next"><h4>Step 1 · Start your design</h4><p>Review the item specifications above. Create the jewellery design in your CAD software, then upload its files here.</p>
<form method="POST" action="{{ route('company.cad.update', [$company->slug, $design->id]) }}">@csrf @method('PUT')<button name="status" value="designing" class="btn btn-primary">Start Designing</button></form></div>
@elseif($design->status === 'designing')
<div class="production-next"><h4>Step 2 · Prepare the design for review</h4><p>Upload a print file and enter the finished weight per piece. A preview photo helps the reviewer but does not replace the print file.</p></div>
<h4>2a. Print file <small>— required</small></h4>
<p>{{ $hasPrint ? 'Print file uploaded. You can now complete the weight and submit below.' : 'Upload STL, OBJ or 3MF before sending this design for approval.' }}</p>
@include('company.orders.files', ['fileList' => $files->where('kind', 'print'), 'kinds' => ['print'], 'cadId' => $design->id, 'canUpload' => true])
<details class="mb-4"><summary>Preview photos &amp; editable source files (optional)</summary><p>Preview: a photo for review. Source: the editable file from your CAD software.</p>
@include('company.orders.files', ['fileList' => $files->whereIn('kind', ['source','preview']), 'kinds' => ['preview','source'], 'cadId' => $design->id, 'canUpload' => true])</details>
<h4>2b. Finished weight &amp; notes</h4>
<form method="POST" action="{{ route('company.cad.update', [$company->slug, $design->id]) }}">@csrf @method('PUT')
<label>Estimated finished weight per piece (g), based on {{ $design->line->metal }} / {{ $design->line->purity }}%</label><input type="number" step="0.001" min="0.001" name="weight_per_piece" class="form-control mb-2" value="{{ old('weight_per_piece', $design->weight_per_piece) }}">
<small class="d-block mb-3">Order estimate: {{ number_format($design->line->expected_weight, 3) }} g total for {{ $design->line->pieces }} pieces ({{ number_format($design->line->expected_weight / max(1, $design->line->pieces), 3) }} g per piece). Enter the designer's actual estimate here.</small>
<label>Design notes</label><textarea name="notes" class="form-control mb-2" maxlength="5000">{{ old('notes', $design->notes) }}</textarea>
<div class="production-actions"><button name="status" value="designing" class="btn btn-secondary">Save Work</button><button name="status" value="submitted" class="btn btn-primary" @disabled(!$hasPrint)>Send for Approval</button></div>
@unless($hasPrint)<p class="mt-2">Send for Approval becomes available after you upload a print file.</p>@endunless
</form>
@elseif($design->status === 'submitted')
<div class="production-next"><h4>Step 3 · Review the submitted design</h4><p>Review the files and weight below. Record who approved the design, or request changes. Submitted files are locked.</p></div>
@include('company.orders.files', ['fileList' => $files, 'kinds' => ['print'], 'canUpload' => false])
<form method="POST" action="{{ route('company.cad.update', [$company->slug, $design->id]) }}">@csrf @method('PUT')
<label>Approved by (customer / authorised person)</label><input name="approved_by" class="form-control mb-2" maxlength="255" value="{{ old('approved_by') }}">
<label>Approval / revision remarks *</label><textarea name="approval_remarks" class="form-control mb-2" required maxlength="5000">{{ old('approval_remarks') }}</textarea>
<div class="production-actions"><button name="status" value="approved" class="btn btn-primary">Approve Design &amp; Continue to RPT</button><button name="status" value="revision_requested" class="btn btn-outline-secondary">Request Changes</button></div></form>
@elseif($design->status === 'revision_requested')
<div class="production-next"><h4>Changes requested</h4><p>This revision is preserved. Return to the order item and assign a new revision for the changes.</p><a class="btn btn-primary" href="{{ route('company.orders.show', [$company->slug, $order->id]) }}">Return to Order · Create New Revision</a></div>
@endif
@if(!in_array($design->status, ['designing','submitted']) || $order->status !== 'confirmed')
<details class="mb-3"><summary>View saved design files</summary>@include('company.orders.files', ['fileList' => $files, 'kinds' => ['print'], 'canUpload' => false])</details>
@endif
@if($design->approval_remarks)<p class="mt-3">Review: {{ $design->approval_remarks }}<br>Approved by {{ $design->approved_by ?: '—' }} · {{ $design->approved_at }} · Recorded by user #{{ $design->approval_recorded_by ?: '—' }}</p>@endif
@if($design->status === 'approved' && $order->status === 'confirmed' && (int)$design->revision === (int)$design->line->designs()->max('revision'))
<hr><h4>Step 4 · Plan RPT Batch</h4><p>Your design is approved. Enter the printing details below to continue. This batch will use {{ $design->number }}.</p>
<form method="POST" action="{{ route('company.rpt.store', [$company->slug, $design->id]) }}">@csrf
<label>Operator *</label><select name="operator_id" class="form-control mb-2" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
<label>Printer / machine *</label><input name="printer" class="form-control mb-2" maxlength="255" required value="{{ old('printer') }}">
<label>Pattern material (resin / wax) *</label><input name="material" class="form-control mb-2" maxlength="255" required value="{{ old('material') }}">
<label>Planned pieces *</label><input name="planned_pieces" type="number" min="1" max="{{ $design->line->pieces }}" class="form-control mb-2" required value="{{ old('planned_pieces') }}"><button class="btn btn-primary">Create RPT Batch</button></form>
@endif
<ul class="mt-3">@foreach($design->batches as $batch)<li><a href="{{ route('company.rpt.show', [$company->slug, $batch->id]) }}">{{ $batch->number }}</a> · {{ $batch->status }}</li>@endforeach</ul>
</div></div></div>
@endsection
