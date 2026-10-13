@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card"><div class="card-header d-flex justify-content-between"><h4>{{ $qr->exists ? 'Edit QR' : 'Add QR' }}</h4><a class="btn btn-secondary" href="{{ route('company.qr-making.index',$company->slug) }}">Back</a></div><div class="card-body">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form id="qr-form" enctype="multipart/form-data" method="POST" action="{{ $qr->exists ? route('company.qr-making.update',[$company->slug,$qr->id]) : route('company.qr-making.store',$company->slug) }}">
@csrf @if($qr->exists) @method('PUT') <input type="hidden" name="id" value="{{ $qr->id }}"> @endif
<div class="row"><div class="col-md-8">
<label>Title *</label><input name="title" class="form-control mb-3" required maxlength="255" value="{{ old('title',$qr->title) }}">
<label>QR Size *</label><select name="size" class="form-select mb-3">@foreach([50=>'Mini',100=>'Extra Small',150=>'Very Small',250=>'Small',350=>'Medium',500=>'Large'] as $size=>$label)<option value="{{ $size }}" @selected(old('size',$qr->size)==$size)>{{ $label }} ({{ $size }}px)</option>@endforeach</select>
<small>Very small codes can be difficult to scan, especially with a logo. Test at the final printed size.</small><br>
<label>QR Type *</label><select id="qr-type" name="type" class="form-select mb-3"><option value="web" @selected(old('type',$qr->type)==='web')>Web</option><option value="app" @selected(old('type',$qr->type)==='app')>App</option></select>
<div id="qr-web"><label>Web Link *</label><input type="url" name="web_url" class="form-control mb-3" value="{{ old('web_url',$qr->web_url) }}" placeholder="https://example.com"></div>
<div id="qr-app"><label>Play Store Link *</label><input type="url" name="android_url" class="form-control mb-3" value="{{ old('android_url',$qr->android_url) }}" placeholder="https://play.google.com/..."><label>App Store Link *</label><input type="url" name="ios_url" class="form-control mb-3" value="{{ old('ios_url',$qr->ios_url) }}" placeholder="https://apps.apple.com/..."></div>
<label>Logo Option *</label><select id="qr-logo-option" name="with_logo" class="form-select mb-3"><option value="0" @selected(old('with_logo',$qr->logo_path ? '1':'0')==='0')>Without Logo</option><option value="1" @selected(old('with_logo',$qr->logo_path ? '1':'0')==='1')>With Logo</option></select>
<div id="qr-logo"><label>Center Logo</label><input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>JPG, PNG or WebP, up to 2 MB. {{ $qr->logo_path ? 'Leave empty to keep the saved logo.' : '' }}</small></div>
</div><div class="col-md-4"><h5>Preview</h5><img id="qr-preview" alt="QR preview" style="max-width:100%;display:none;background:white"><p id="qr-preview-status" role="status">Click Preview QR to check the design. Save before scanning a new QR.</p></div></div>
<div class="mt-4 d-flex gap-2"><button type="button" id="qr-preview-button" class="btn btn-info">Preview QR</button><button class="btn btn-primary">Save QR</button></div>
</form></div></div></div>
@endsection
@push('scripts')
<script>
(function(){
const form=document.getElementById('qr-form');
function sync(){const app=document.getElementById('qr-type').value==='app'; ['web','app'].forEach(type=>{const box=document.getElementById('qr-'+type); const on=(type==='app')===app;box.hidden=!on;box.querySelectorAll('input').forEach(input=>{input.disabled=!on; input.required=on;});});const logo=document.getElementById('qr-logo-option').value==='1';document.getElementById('qr-logo').hidden=!logo;form.elements.logo.disabled=!logo;}
document.getElementById('qr-type').addEventListener('change',sync);document.getElementById('qr-logo-option').addEventListener('change',sync);sync();
document.getElementById('qr-preview-button').addEventListener('click',async function(){if(!form.reportValidity())return;this.disabled=true;const status=document.getElementById('qr-preview-status');status.textContent='Generating preview…';try{const data=new FormData(form);data.delete('_method');const response=await fetch(@json(route($qr->exists ? 'company.qr-making.preview.edit' : 'company.qr-making.preview.create',$company->slug)),{method:'POST',body:data,headers:{'Accept':'application/json'}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Preview failed');const img=document.getElementById('qr-preview');img.src=result.image;img.style.display='block';status.textContent='Preview ready. Save to apply these settings.';}catch(e){status.textContent=e.message;}finally{this.disabled=false;}});
})();
</script>
@endpush
