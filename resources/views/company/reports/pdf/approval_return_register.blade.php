
<!doctype html>

<!-- approval-register-pdf: multiline-directives-v2 -->
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 22px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
h2, h3 { text-align: center; margin: 0 0 7px; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
th, td { border: 1px solid #aaa; padding: 5px 3px; overflow-wrap: break-word; word-wrap: break-word; }
th { background: #eee; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.number { text-align: right; } .note { color: #555; margin-top: 8px; }

</style>
</head>
<body>
@include('company.reports.partials.pdf_company_scope')

<h2>{{ \App\Services\ReportBranches::heading($company) }}
</h2>
<h3>Approval &amp; Return Register
</h3>

<div>From: {{ $filters['from_date'] ?? 'All dates' }} | To: {{ $filters['to_date'] ?? 'All dates' }} | Type: {{ $filters['entry_type'] ?? 'All types' }}
</div>

@if(!empty($filters['voucher']))
<div>Voucher filter: {{ $filters['voucher'] }}
</div>
@endif


@if(!empty($filters['search']))
<div>Search: {{ $filters['search'] }}
</div>
@endif


<div class="note">Approvals use issue dates; returns use return dates; sales use sale dates. Weights are in grams. Quantity counts item rows. All matching entries are included.
</div>

<table>
<thead>
<tr>
<th>Totals
</th>
@foreach($metrics as $label)
<th>{{ $label }}
</th>
@endforeach

</tr>
</thead>
<tbody>

@foreach($totals as $type => $values)
<tr>
<th>{{ $type }}
</th>
@foreach($metrics as $key => $label)
<td class="number">{{ number_format($values[$key], $key === 'qty' ? 0 : ($key === 'amount' ? 2 : 3)) }}
</td>
@endforeach

</tr>
@endforeach


</tbody>
</table>
@php
    $showCompany = count(\App\Services\ReportBranches::ids($company)) > 1;
    $showSourceApproval = ($filters['entry_type'] ?? null) !== 'Approval';
@endphp

@if($showSourceApproval)
<div class="note">Transaction Voucher identifies this issue, return or sale. Original Approval links returns and sales to their approval.
</div>
@endif


<table>
<thead>
<tr>
<th>Date
</th>
<th>Type
</th>
<th>Transaction Voucher
</th>
@if($showSourceApproval)
<th>Original Approval
</th>
@endif

<th style="width:14%">Customer
</th>
@foreach($metrics as $label)
<th>{{ $label }}
</th>
@endforeach
 
@if($showCompany)
<th>Company / Branch
</th>
@endif
<th>Pending Qty</th>

</tr>
</thead>
<tbody>

@forelse($rows as $row)

<tr>
<td>{{ \Carbon\Carbon::parse($row->entry_date)->format('d-m-Y') }}
</td>
<td>{{ $row->entry_type }}
</td>
<td>{{ $row->voucher_no }}
</td>
@if($showSourceApproval)
<td>{{ $row->entry_type === 'Approval' ? '—' : $row->approval_no }}
</td>
@endif

<td>{{ $row->customer_name ?? '-' }}
</td>
@foreach($metrics as $key => $label)
<td class="number">{{ number_format($row->$key, $key === 'amount' ? 2 : ($key === 'qty' ? 0 : 3)) }}
</td>
@endforeach


@if($showCompany)
<td>{{ \App\Services\ReportBranches::name($row->company_id) }}
</td>
@endif
<td class="number">{{ $row->entry_type === 'Approval' ? $pendingItems->where('voucher_id', $row->voucher_id)->count() : '-' }}</td>

</tr>

@empty

<tr>
<td colspan="{{ 5 + count($metrics) + (int) $showSourceApproval + (int) $showCompany }}">No matching entries.
</td>
</tr>
@endforelse


</tbody>
</table>

@include('company.reports.partials.approval_register_pending')
</body>
</html>
