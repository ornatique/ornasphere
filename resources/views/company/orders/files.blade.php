<ul class="mb-3">@forelse($fileList as $file)<li>{{ ucfirst($file->kind) }}: <a href="{{ route('company.orders.files.show', [$company->slug, $file->id]) }}">{{ $file->name }}</a></li>@empty<li>No attachments yet.</li>@endforelse</ul>
@if($canUpload ?? true)
@php($fileModule = in_array('reference', $kinds) ? 'orders' : (in_array('inspection', $kinds) ? 'rpt' : 'cad'))
<form method="POST" enctype="multipart/form-data" action="{{ route('company.'.$fileModule.'.files.update', [$company->slug, $order->id]) }}" class="mb-3">@csrf
<input type="hidden" name="line_id" value="{{ $lineId ?? '' }}"><input type="hidden" name="cad_id" value="{{ $cadId ?? '' }}"><input type="hidden" name="rpt_id" value="{{ $rptId ?? '' }}">
@if(count($kinds) === 1)<input type="hidden" name="kind" value="{{ $kinds[0] }}">
@else<label>What are you uploading?</label><select name="kind" class="form-control mb-2">@foreach($kinds as $kind)<option value="{{ $kind }}">{{ $kind === 'source' ? 'Editable CAD source' : ucfirst($kind).' image' }}</option>@endforeach</select>@endif
<label class="d-block">Choose {{ count($kinds) === 1 ? $kinds[0] : 'design' }} file</label>
<input type="file" name="file" required class="form-control mb-2" @if($kinds === ['print']) accept=".stl,.obj,.3mf" @endif>
<small class="d-block mb-2">{{ $kinds === ['print'] ? 'STL, OBJ or 3MF, up to 50 MB. A JPG/PNG photo cannot be used as a print file.' : 'Photos: JPG, PNG, WebP up to 12 MB. CAD source: 3DM, STL, OBJ, STEP, IGES or ZIP up to 50 MB.' }}</small><button class="btn btn-secondary">{{ $kinds === ['print'] ? 'Upload Print File' : 'Upload Attachment' }}</button>
</form>
@endif
