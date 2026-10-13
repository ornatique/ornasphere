@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card"><div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2"><h4 class="card-title mb-0">QR Making</h4><a class="btn btn-primary" href="{{ route('company.qr-making.create',$company->slug) }}">Add QR</a></div><div class="card-body">
<div class="table-responsive"><table id="qrTable" class="table table-bordered"><thead><tr><th>QR</th><th>Title</th><th>Type</th><th>Size</th><th>Logo</th><th>Created By</th><th>Action</th></tr></thead><tbody>
@foreach($rows as $qr)
<tr><td><img width="100" height="100" alt="QR preview" src="{{ route('company.qr-making.image',[$company->slug,$qr->id]) }}"></td><td>{{ $qr->title }}</td><td>{{ ucfirst($qr->type) }}</td><td>{{ $qr->size }}px</td><td>{{ $qr->logo_path ? 'With logo' : 'Without logo' }}</td><td>{{ $qr->creator?->name ?? '-' }}</td><td>
<a class="btn btn-sm btn-success" href="{{ route('company.qr-making.svg',[$company->slug,$qr->id]) }}">SVG</a>
<a class="btn btn-sm btn-danger" href="{{ route('company.qr-making.pdf',[$company->slug,$qr->id]) }}">PDF</a>
<a class="btn btn-sm btn-primary" href="{{ route('company.qr-making.edit',[$company->slug,$qr->id]) }}">Edit</a>
<form class="d-inline" method="POST" action="{{ route('company.qr-making.destroy',[$company->slug,$qr->id]) }}" onsubmit="return confirm('Delete this saved QR record?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form></td></tr>
@endforeach
</tbody></table></div></div></div></div>
@endsection
@push('scripts')
<script>
$(function () {
    $('#qrTable').DataTable({
        pageLength: 10,
        order: [],
        columnDefs: [{ targets: [0, 6], orderable: false, searchable: false }],
        language: { emptyTable: 'No QR codes yet.' }
    });
});
</script>
@endpush
