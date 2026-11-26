<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Order {{ $order->number }}</title>
<style>
@page{margin:30px 30px 42px}body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#253247;line-height:1.45}
table{width:100%;border-collapse:collapse}h1{font-size:23px;margin:0;color:#172b4d}h2{font-size:12px;margin:19px 0 7px;color:#172b4d}
.heading{border-bottom:3px solid #314f78;margin-bottom:16px}.heading td{padding:0 0 13px;vertical-align:middle}
.caption{font-size:9px;color:#68778d;letter-spacing:1px}.order-number{font-size:17px;font-weight:bold;margin:3px 0}
.right{text-align:right}.meta{margin-bottom:16px;background:#f2f5f9;border:1px solid #e0e6ee}.meta td{padding:11px 14px;vertical-align:top}.meta-label{font-size:9px;color:#68778d}.customer-name{font-size:14px;font-weight:bold;margin-top:3px}
.items{table-layout:fixed}.items th{background:#263e60;color:#fff;font-size:8px;font-weight:normal;padding:8px 4px;text-align:right;border:1px solid #263e60}
.items td{border:1px solid #dce3ec;padding:9px 4px;vertical-align:top;font-size:9px;word-wrap:break-word;text-align:right}
.items tr:nth-child(even) td{background:#f7f9fc}.items thead{display:table-header-group}.items tr{page-break-inside:avoid}
.items .image{width:7%;text-align:center}.item-image{max-width:55px;max-height:55px}
.items .left{text-align:left}.items .sr{width:2%;text-align:center}.items .label{width:13%}.items .qty{width:3%}.item-name{font-size:8px;margin-top:3px}.muted{color:#68778d;font-size:8px}
.items .grand-total td{background:#eaf0f8;font-weight:bold;border-top:2px solid #314f78;padding-top:10px;padding-bottom:10px}
.notes{margin-top:16px;border-left:3px solid #8a9cb6;padding:6px 10px;white-space:pre-wrap;word-wrap:break-word;page-break-inside:avoid}
.footer{position:fixed;bottom:-24px;left:0;right:0;border-top:1px solid #dce3ec;padding-top:5px;color:#7c8798;font-size:8px}
</style></head><body>
<div class="footer">{{ $company->name }} &nbsp; / &nbsp; Order {{ $order->number }}</div>
<table class="heading"><tr><td><h1>{{ $company->name }}</h1><div class="caption">ORDER MANAGEMENT</div></td>
<td class="right"><div class="caption">ORDER SUMMARY</div><div class="order-number">{{ $order->number }}</div><div>Date: {{ $order->order_date->format('d-m-Y') }}</div><div>Price: {{ ($order->price_mode ?? (\App\Services\WholesalePrice::enabled($company) ? 'wholesale' : 'sale')) === 'wholesale' ? 'Wholesale Price' : 'Sale Price' }}</div></td></tr></table>
<table class="meta"><tr><td style="width:65%"><div class="meta-label">CUSTOMER</div><div class="customer-name">{{ $order->customer?->name ?? 'Customer unavailable' }}</div></td>
<td><div class="meta-label">MOBILE NUMBER</div><div class="customer-name">{{ $order->customer?->mobile_no ?: '—' }}</div></td></tr></table>
<h2>Item details</h2>
@php($showWholesale = ($order->price_mode ?? (\App\Services\WholesalePrice::enabled($company) ? 'wholesale' : 'sale')) === 'wholesale' && $order->items->contains(fn ($line) => (float) $line->display_wholesale_price > 0))
<table class="items"><thead><tr><th class="sr">#</th><th class="label left">Label / Item</th><th class="image">Image</th><th class="qty">Qty</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Metal Amt</th><th>Labour Amt</th><th>Other Amt</th>@if($showWholesale)<th>Wholesale Price</th>@else<th>Sale Price</th>@endif<th>Total Amt</th></tr></thead><tbody>
@forelse($order->items as $line)
<tr><td class="sr">{{ $loop->iteration }}</td><td class="left"><strong>{{ $line->qr_code ?: $line->huid ?: '—' }}</strong><div class="item-name">{{ $line->item_name }}</div>
@if($line->huid && $line->huid !== $line->qr_code)<div class="muted">HUID: {{ $line->huid }}</div>@endif
@if($line->remarks)<div class="muted">{{ $line->remarks }}</div>@endif</td>
<td class="image">@if($line->pdf_image)<img class="item-image" src="{{ $line->pdf_image }}" alt="Item image">@else<span class="muted">No image</span>@endif</td><td>{{ $line->quantity }}</td>
@foreach(['gross_weight','other_weight','net_weight'] as $field)
<td>{{ number_format((float) ($line->$field ?? ($field === 'other_weight' ? max(0, $line->gross_weight - $line->net_weight) : 0)), 3) }}</td>
@endforeach
@foreach(['metal_amount','labour_amount','other_amount'] as $field)<td>{{ number_format((float) $line->$field, 2) }}</td>@endforeach
@if($showWholesale)<td>{{ (float) $line->display_wholesale_price > 0 ? number_format((float) $line->display_wholesale_price, 2) : '—' }}</td>@else<td>{{ number_format((float) ($line->display_sale_price ?? $line->sale_price), 2) }}</td>@endif<td>{{ number_format((float) $line->total_amount, 2) }}</td>
</tr>@empty<tr><td colspan="12" class="left">No order items.</td></tr>@endforelse
<tr class="grand-total">
<td colspan="3" class="left">Total</td>
<td>{{ $order->items->sum('quantity') }}</td>
<td>{{ number_format($order->items->sum(fn ($line) => $line->gross_weight * $line->quantity), 3) }}</td>
<td>{{ number_format($order->items->sum(fn ($line) => ($line->other_weight ?? max(0, $line->gross_weight - $line->net_weight)) * $line->quantity), 3) }}</td>
<td>{{ number_format($order->items->sum(fn ($line) => $line->net_weight * $line->quantity), 3) }}</td>
@foreach(['metal_amount','labour_amount','other_amount'] as $field)
<td>{{ number_format($order->items->sum(fn ($line) => (float) $line->$field * $line->quantity), 2) }}</td>
@endforeach
<td>{{ number_format($order->items->sum(fn ($line) => $showWholesale ? max(0, (float) $line->display_wholesale_price) : (float) ($line->display_sale_price ?? $line->sale_price)), 2) }}</td><td>{{ number_format($order->items->sum('total_amount'), 2) }}</td>
</tr></tbody></table>
@if($order->remarks)<div class="notes"><strong>Remarks</strong><br>{{ $order->remarks }}</div>@endif
</body></html>
