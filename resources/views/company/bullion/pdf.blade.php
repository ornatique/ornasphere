<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Bullion ledger</title>
<style>
@page { margin:26px; }
body { font-family:DejaVu Sans,sans-serif; font-size:9px; color:#222b3d; }
h1 { font-size:22px; margin:0 0 6px; } h2 { font-size:14px; margin:0 0 12px; }
.meta { margin-bottom:14px; line-height:1.8; } .summary { background:#edf1f8; padding:12px; margin-bottom:14px; }
table { width:100%; border-collapse:collapse; table-layout:fixed; }
thead { display:table-header-group; } tr { page-break-inside:avoid; }
th { background:#28354f; color:white; font-size:8px; text-align:left; }
th,td { padding:7px 4px; border:1px solid #ccd3df; overflow-wrap:break-word; }
td { font-size:8px; } .num { text-align:right; } .note { color:#546078; line-height:1.6; margin:10px 0; }
</style></head><body>
<h1>{{ $company->name }}</h1><h2>Bullion ledger</h2>
<div class="meta"><strong>Bullion:</strong> {{ $selected->name }}<br>Generated: {{ now()->format('d-m-Y') }} &nbsp; | &nbsp; Latest transactions first</div>
<div class="summary"><strong>Closing balances:</strong> Cash INR {{ number_format($balance['cash'],2) }} &nbsp; | &nbsp; Gold {{ number_format($balance['gold'],3) }} g &nbsp; | &nbsp; Silver {{ number_format($balance['silver'],3) }} g</div>
<p class="note">Bullion balance perspective: Cr adds to the bullion's balance; Dr deducts from it. Metal quantities are fine grams. Running balances show the balance immediately after each transaction.</p>
<table><thead><tr><th style="width:10%">Date / Time (IST)</th><th style="width:10%">Transaction</th><th>Cash Cr<br>INR</th><th>Cash Dr<br>INR</th><th style="width:5%">Metal</th><th>Fine Cr<br>g</th><th>Fine Dr<br>g</th><th>Rate<br>INR / pure g</th><th>Cash balance<br>INR</th><th>Gold balance<br>g</th><th>Silver balance<br>g</th><th style="width:13%">Remarks</th></tr></thead><tbody>
@forelse($entries->reverse() as $entry)
<tr><td><span style="white-space:nowrap">{{ $entry->entry_date->format('d-m-Y') }}</span><br>{{ $entry->created_at?->timezone('Asia/Kolkata')->format('h:i a') ?? 'Time unavailable' }}</td><td>{{ ucwords(str_replace('_',' ',$entry->entry_type)) }}</td>
@foreach(['cash_in','cash_out'] as $field)<td class="num">{{ number_format($entry->$field,2) }}</td>@endforeach
<td>{{ ucfirst($entry->metal_type ?? '-') }}</td>
@foreach(['metal_in','metal_out'] as $field)<td class="num">{{ number_format($entry->$field,3) }}</td>@endforeach
<td class="num">{{ number_format($entry->rate,2) }}</td>
@foreach(['cash','gold','silver'] as $field)<td class="num">{{ number_format($entry->running[$field],$field === 'cash' ? 2 : 3) }}</td>@endforeach
<td>{{ $entry->remarks }}</td></tr>
@empty<tr><td colspan="12">No transactions recorded.</td></tr>@endforelse
</tbody></table></body></html>
