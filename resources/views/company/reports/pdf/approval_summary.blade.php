<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin:24px; } body { font-family:DejaVu Sans,sans-serif;font-size:9px;color:#222; }
h1 { font-size:19px; } h2 { font-size:14px;margin-top:22px; }
table { width:100%;border-collapse:collapse;table-layout:fixed; } th,td { border:1px solid #bbb;padding:6px;word-wrap:break-word; } th { background:#edf0f5; } thead { display:table-header-group; } tr { page-break-inside:avoid; } .num { text-align:right; }
</style></head><body>
<h1>Approval Summary — {{ \App\Services\ReportBranches::heading($company) }}</h1>
<p>From: {{ $filters['from_date'] ?? 'All dates' }} | To: {{ $filters['to_date'] ?? 'All dates' }} | Voucher: {{ $filters['voucher'] ?? 'All' }} | Total vouchers: {{ $totals['vouchers'] }}</p>
<p>Current item status for matching approval issue dates, including later returns and sales. Weights are in grams.</p>
@php($status = 'issued')
<h2>Issued on Approval</h2>
@php($keys = ['qty','gross','other','net','fine','metal_rate','labour_rate','metal_amount','labour_amount','total_amount'])
<table><thead><tr><th>Voucher</th><th>Date</th><th>Customer</th><th>Branch</th><th>Qty</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Fine Wt</th>@if($status === 'issued')<th>Other Amount</th><th>Metal Rate</th><th>Labour Rate</th><th>Metal Amount</th><th>Labour Amount</th><th>Total Amount</th>@endif</tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ $row->approval_no }}</td><td>{{ \Carbon\Carbon::parse($row->approval_date)->format('d-m-Y') }}</td><td>{{ $row->customer_name }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id) }}</td>
@foreach($keys as $key)<td class="num">{{ number_format($row->{$status.'_'.$key}, $key === 'qty' ? 0 : (str_ends_with($key, '_rate') || str_ends_with($key, '_amount') ? 2 : 3)) }}</td>@if($status === 'issued' && $key === 'fine')<td class="num">{{ number_format($row->issued_other_amount, 2) }}</td>@endif @endforeach</tr>
@empty<tr><td colspan="15">No matching approvals.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="4">Total</th>@foreach($keys as $key)<th class="num">{{ number_format($totals[$status.'_'.$key], $key === 'qty' ? 0 : (str_ends_with($key, '_rate') || str_ends_with($key, '_amount') ? 2 : 3)) }}</th>@if($status === 'issued' && $key === 'fine')<th class="num">{{ number_format($totals['issued_other_amount'], 2) }}</th>@endif @endforeach</tr></tfoot></table>
</body></html>
