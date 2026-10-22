<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:22px}body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#222}h1{font-size:16px}table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border:1px solid #bbb;padding:5px;word-wrap:break-word}th{background:#e9edf3;text-align:left}thead{display:table-header-group}tr{page-break-inside:avoid}p{word-wrap:break-word}
</style></head><body><h1>{{ $title }}</h1><p><strong>Company / Branch:</strong> {{ $branches }}</p><p>{{ $filters }}</p>
<table><thead><tr>@foreach($headers as $header)<th @if($header === 'Details') style="width:55%" @endif>{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headers) }}">No records found.</td></tr>@endforelse
@if(!empty($totals))<tr style="font-weight:bold;background:#e9edf3">@foreach($totals as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endif
</tbody></table></body></html>