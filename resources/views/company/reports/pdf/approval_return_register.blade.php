<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin: 22px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
h2, h3 { text-align: center; margin: 0 0 7px; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
th, td { border: 1px solid #aaa; padding: 5px 3px; overflow-wrap: break-word; word-wrap: break-word; }
th { background: #eee; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.number { text-align: right; } .note { color: #555; margin-top: 8px; }
</style></head><body>
<h2>{{ $company->name }}</h2><h3>Approval &amp; Return Register</h3>
<div>From: {{ $filters['from_date'] ?? 'All dates' }} | To: {{ $filters['to_date'] ?? 'All dates' }} | Type: {{ $filters['entry_type'] ?? 'All types' }}</div>
@if(!empty($filters['voucher']))<div>Voucher filter: {{ $filters['voucher'] }}</div>@endif
@if(!empty($filters['search']))<div>Search: {{ $filters['search'] }}</div>@endif
<div class="note">Approvals use issue dates; returns use return dates; sales use sale dates. Weights are in grams. Quantity counts item rows. All matching entries are included.</div>
<table><thead><tr><th>Totals</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@foreach($totals as $type => $values)<tr><th>{{ $type }}</th>@foreach($metrics as $key => $label)<td class="number">{{ number_format($values[$key], $key === 'qty' ? 0 : ($key === 'amount' ? 2 : 3)) }}</td>@endforeach</tr>@endforeach
</tbody></table>
<table><thead><tr><th>Date</th><th>Type</th><th>Voucher</th><th>Approval Voucher</th><th style="width:14%">Customer</th>@foreach($metrics as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ \Carbon\Carbon::parse($row->entry_date)->format('d-m-Y') }}</td><td>{{ $row->entry_type }}</td><td>{{ $row->voucher_no }}</td><td>{{ $row->approval_no }}</td><td>{{ $row->customer_name ?? '-' }}</td>@foreach($metrics as $key => $label)<td class="number">{{ number_format($row->$key, $key === 'qty' ? 0 : ($key === 'amount' ? 2 : 3)) }}</td>@endforeach</tr>
@empty<tr><td colspan="11">No matching entries.</td></tr>@endforelse
</tbody></table>
</body></html>
