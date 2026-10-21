<!doctype html><html><head><meta charset="utf-8"><style>
@page { margin:24px; } body { font-family:DejaVu Sans,sans-serif;font-size:9px;color:#222; }
h1 { font-size:19px; } h2 { font-size:14px;margin-top:22px; }
table { width:100%;border-collapse:collapse;table-layout:fixed; } th,td { border:1px solid #bbb;padding:6px;word-wrap:break-word; } th { background:#edf0f5; } thead { display:table-header-group; } tr { page-break-inside:avoid; } .num { text-align:right; }
</style></head><body>
<h1>Approval Summary — {{ \App\Services\ReportBranches::heading($company) }}</h1>
<p>From: {{ $filters['from_date'] ?? 'All dates' }} | To: {{ $filters['to_date'] ?? 'All dates' }} | Voucher: {{ $filters['voucher'] ?? 'All' }} | Total vouchers: {{ $totals['vouchers'] }}</p>
<p>Current item status for matching approval issue dates, including later returns and sales. Weights are in grams.</p>
@foreach(['issued'=>'Issued on Approval','returned'=>'Returned','sold'=>'Sold','pending'=>'Pending with Customer'] as $status=>$label)
<h2>{{ $label }}</h2>
<table><thead><tr><th>Voucher</th><th>Date</th><th>Customer</th><th>Branch</th><th>Qty</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Fine Wt</th></tr></thead><tbody>
@forelse($rows as $row)
<tr><td>{{ $row->approval_no }}</td><td>{{ \Carbon\Carbon::parse($row->approval_date)->format('d-m-Y') }}</td><td>{{ $row->customer_name }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id) }}</td>
@foreach(['qty','gross','other','net','fine'] as $key)<td class="num">{{ number_format($row->{$status.'_'.$key},$key === 'qty' ? 0 : 3) }}</td>@endforeach</tr>
@empty<tr><td colspan="9">No matching approvals.</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="4">Total</th>@foreach(['qty','gross','other','net','fine'] as $key)<th class="num">{{ number_format($totals[$status.'_'.$key],$key === 'qty' ? 0 : 3) }}</th>@endforeach</tr></tfoot></table>
@endforeach
</body></html>
