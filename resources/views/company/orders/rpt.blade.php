@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">@include('company.orders.nav')<div class="card"><div class="card-body">
<h3>{{ $batch->number }} · {{ $batch->status === 'ready' ? 'Quality check complete' : ucwords(str_replace('_',' ', $batch->status)) }}</h3>
<p><a href="{{ route('company.orders.show', [$company->slug, $order->id]) }}">{{ $order->number }}</a> · {{ $order->customer->name }} · {{ $batch->design->line->item_name }} · <a href="{{ route('company.cad.show', [$company->slug, $batch->cad_design_id]) }}">{{ $batch->design->number }}</a></p>
<p>Operator: {{ $batch->operator?->name }} · Printer: {{ $batch->printer }} · Material: {{ $batch->material }} · Planned: {{ $batch->planned_pieces }} pcs</p>
<p>Pattern weight is resin/wax weight and does not add metal stock. Accepted patterns are available for a future casting handover; no casting stock is posted here.</p>
@include('company.orders.files', ['fileList' => $files, 'kinds' => ['inspection'], 'rptId' => $batch->id, 'canUpload' => $order->status === 'confirmed' && $batch->status !== 'ready'])
@if($order->status === 'confirmed' && $batch->status !== 'ready')
<form method="POST" action="{{ route('company.rpt.update', [$company->slug, $batch->id]) }}">@csrf @method('PUT')<div class="row">
@foreach(['printed_pieces'=>'Printed pieces','accepted_pieces'=>'Accepted pieces','rejected_pieces'=>'Rejected pieces','pattern_weight'=>'Actual pattern weight (g)'] as $field => $label)
<div class="col-md-3 form-group"><label>{{ $label }} *</label><input name="{{ $field }}" type="number" min="0" step="{{ $field === 'pattern_weight' ? '0.001' : '1' }}" value="{{ old($field, $batch->$field) }}" class="form-control" required></div>
@endforeach
@foreach(['started_on'=>'Start date','completed_on'=>'Completion date'] as $field => $label)<div class="col-md-6 form-group"><label>{{ $label }}</label><input type="date" name="{{ $field }}" class="form-control" value="{{ old($field, $batch->$field) }}"></div>@endforeach
</div><label>Rejection reason</label><textarea name="rejection_reason" class="form-control mb-2" maxlength="5000">{{ old('rejection_reason', $batch->rejection_reason) }}</textarea>
<label>Inspection notes</label><textarea name="inspection_notes" class="form-control mb-2" maxlength="5000">{{ old('inspection_notes', $batch->inspection_notes) }}</textarea>
<label>Next status</label><select name="status" class="form-control mb-2">@foreach(match($batch->status) { 'planned'=>['printing'], 'printing'=>['printing','printed'], 'printed'=>['quality_check'], default=>['quality_check','ready'] } as $status)<option value="{{ $status }}">{{ $status === 'ready' ? 'Complete quality check / Release accepted patterns' : ucwords(str_replace('_',' ', $status)) }}</option>@endforeach</select><button class="btn btn-primary">Save RPT Status</button></form>
@else
<p>Printed {{ $batch->printed_pieces }} · Accepted {{ $batch->accepted_pieces }} · Rejected {{ $batch->rejected_pieces }} · Pattern weight {{ $batch->pattern_weight }} g</p><p>{{ $batch->started_on }} — {{ $batch->completed_on }}<br>{{ $batch->rejection_reason }}<br>{{ $batch->inspection_notes }}</p>
@endif
</div></div></div>
@endsection
