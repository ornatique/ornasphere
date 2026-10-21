@extends('company_layout.admin')
@section('content')
<div class="content-wrapper bullion-page"><div class="card bullion-shell"><div class="card-header"><span class="bullion-eyebrow">BULLION BALANCES & TRANSACTIONS</span><h1>Bullion Management</h1><p class="bullion-intro">Manage bullion cash and fine metal balances and conversions in one place.</p></div><div class="card-body">
<p class="bullion-context">Gold and silver balances are measured in fine grams. These account entries do not receive physical stock.</p>

<style>
.bullion-page { --bullion-border:#45465f; --bullion-muted:#bfc3d7; color:#f3f4fb; }
.bullion-page .bullion-shell { border:1px solid var(--bullion-border); border-radius:16px; overflow:visible; background:#262638; }
.bullion-page .bullion-shell > .card-header { border-radius:15px 15px 0 0; }
.bullion-page .bullion-shell > .card-header { display:flex; flex-direction:column; align-items:flex-start; justify-content:flex-start; gap:0; padding:28px 30px; background:#2c2c43; border-bottom:1px solid var(--bullion-border); }
.bullion-page .bullion-eyebrow { display:block; color:#aebdff; font-size:11px; font-weight:700; letter-spacing:1.8px; margin-bottom:10px; }
.bullion-page h1 { font-size:32px; line-height:1.2; font-weight:700; color:#fff; margin:0 0 10px; }
.bullion-page .bullion-intro,.bullion-page .bullion-context { color:var(--bullion-muted); font-size:14px; line-height:1.65; margin:0; }
.bullion-page .bullion-context { margin-bottom:22px; }
.bullion-page .bullion-shell > .card-body { padding:28px 30px; }
.bullion-page label { display:block; font-size:13px; font-weight:600; color:#e2e4ef; margin-bottom:8px; line-height:1.5; }
.bullion-page .form-control { height:48px; border:1px solid var(--bullion-border); border-radius:8px; background:#303049; color:#fff; padding:12px 14px; font-size:14px; color-scheme:dark; }
.bullion-page .form-control:focus { border-color:#91a7ff; box-shadow:0 0 0 3px #91a7ff22; }
.bullion-page .bullion-balances { row-gap:16px; }
.bullion-page .bullion-balance { height:100%; padding:22px 24px; border:1px solid var(--bullion-border); border-radius:12px; background:#2c2d43; border-top:3px solid #8ea5ff; }
.bullion-page .bullion-balance-gold { border-top-color:#dfbc72; }
.bullion-page .bullion-balance-silver { border-top-color:#b7c9db; }
.bullion-page .bullion-balance span { font-size:13px; color:#c6cbdf; font-weight:600; }
.bullion-page .bullion-balance h3 { margin:12px 0 0; font-size:30px; font-weight:700; color:#fff; font-variant-numeric:tabular-nums; }
.bullion-page #bullion-form { border:1px solid var(--bullion-border); border-radius:12px; padding:24px; background:#29293e; }
.bullion-page h2 { font-size:22px; font-weight:700; color:#fff; line-height:1.35; margin:0 0 16px; }
.bullion-page .btn { border-radius:8px; font-size:14px; font-weight:600; padding:12px 18px; line-height:1.4; }
.bullion-page .btn-info { color:#e4eaff; background:#353f66; border:1px solid #657bb7; }
.bullion-page .btn-info:hover,.bullion-page [data-bullion-convert][aria-pressed="true"] { background:#4764c5; border-color:#91a7ff; color:#fff; }
.bullion-page .btn-success { background:#24a97b; border-color:#24a97b; color:#fff; }
.bullion-page #bullion-transaction-help { color:var(--bullion-muted); font-size:13px; line-height:1.6; min-height:20px; }
.bullion-page #bullion-rate-notice { background:#27394b; border:1px solid #466783; border-radius:10px; color:#d6e8f8; padding:18px; font-size:13px; line-height:1.65; }
.bullion-page #bullion-live-status { margin-bottom:14px; font-weight:600; }
.bullion-page .bullion-quote-controls { display:flex; align-items:end; flex-wrap:wrap; gap:12px; margin-bottom:12px; }
.bullion-page .bullion-quote-controls label { margin:0; flex:1 1 180px; }
.bullion-page .bullion-quote-controls .form-control { margin-top:6px; }
.bullion-page #bullion-preview { background:#242c40; border:1px solid #53638c; border-left:3px solid #91a7ff; border-radius:8px; color:#e5eaff; padding:18px; font-size:14px; line-height:1.8; }
.bullion-page .bullion-confirm { display:flex; align-items:flex-start; gap:10px; margin:20px 0; }
.bullion-page .bullion-confirm input { width:18px; height:18px; margin-top:1px; flex-shrink:0; accent-color:#8099ff; }
.bullion-page .bullion-actions { display:flex; flex-wrap:wrap; gap:12px; padding-top:18px; border-top:1px solid var(--bullion-border); }
.bullion-page .bullion-ledger { margin-top:32px; }
.bullion-page .bullion-ledger-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin:32px 0 18px; }
.bullion-page .bullion-ledger-heading h2 { margin:0; }
.bullion-page .bullion-ledger-panel { border:1px solid var(--bullion-border); border-radius:12px; padding:18px; min-width:0; }
.bullion-page .bullion-table-toolbar,.bullion-page .bullion-table-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; padding:0 0 18px; }
.bullion-page .bullion-table-footer { padding:18px 0 0; }
.bullion-page .dataTables_wrapper label { display:flex; align-items:center; gap:10px; margin:0; white-space:nowrap; }
.bullion-page .dataTables_wrapper .dataTables_filter input { width:220px; max-width:100%; margin:0; height:40px; }
.bullion-page .dataTables_wrapper .dataTables_length select { width:76px; height:40px; padding:6px 10px; margin:0; background:#303049; color:#fff; border:1px solid var(--bullion-border); border-radius:8px; }
.bullion-page .dataTables_wrapper .dataTables_info { padding:0; color:var(--bullion-muted); }
.bullion-page .dataTables_wrapper .pagination { margin:0; gap:4px; }
.bullion-page .dataTables_wrapper .page-link { background:#303049; color:#dce3ff; border:1px solid #505471; border-radius:6px; padding:9px 13px; }
.bullion-page .dataTables_wrapper .page-item.active .page-link { background:#4764c5; border-color:#91a7ff; color:#fff; }
.bullion-page .dataTables_wrapper .page-item.disabled .page-link { background:#29293e; color:#9196ad; }
.bullion-page .dataTables_scrollBody { border-bottom:1px solid var(--bullion-border); }
.bullion-page .table-responsive { border:1px solid var(--bullion-border); border-radius:10px; }
.bullion-page .table { margin:0; color:#e0e3f0; }
.bullion-page .table th { background:#33334b; color:#fff; font-size:12px; padding:16px; }
.bullion-page .table td { padding:16px; border-color:#414159; font-size:13px; font-variant-numeric:tabular-nums; }
.bullion-page .table tbody tr:nth-child(even) { background:#2c2c42; }
.bullion-page .bullion-empty { padding:28px !important; text-align:center; color:var(--bullion-muted); }
@media(max-width:767px) { .bullion-page .bullion-shell > .card-header,.bullion-page .bullion-shell > .card-body { padding:20px 16px; } .bullion-page h1 { font-size:27px; } .bullion-page #bullion-form { padding:18px 14px; } .bullion-page .bullion-actions .btn { width:100%; } }
#bullion-customer-filter .select2-container { width:100% !important; }
#bullion-customer-filter .select2-selection--single { display:flex !important; align-items:center !important; height:46px !important; padding:0 !important; margin:0 !important; background:#2e2d4d; border:1px solid #555976; border-radius:5px; }
#bullion-customer-filter .select2-container .select2-selection--single { position:relative !important; box-sizing:border-box !important; }
#bullion-customer-filter .select2-container .select2-selection--single .select2-selection__rendered { position:absolute !important; top:50% !important; left:0 !important; right:0 !important; bottom:auto !important; transform:translateY(-50%) !important; display:block !important; height:20px !important; min-height:0 !important; width:auto !important; color:#fff !important; line-height:20px !important; padding:0 36px 0 14px !important; margin:0 !important; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#bullion-customer-filter .select2-selection__arrow { height:46px !important; top:0 !important; }
#bullion-customer-filter .select2-dropdown { background:#2e2d4d !important; color:#fff !important; border-color:#555976; box-shadow:0 8px 20px #0004; }
#bullion-customer-filter .select2-search--dropdown { padding:8px; }
#bullion-customer-filter .select2-search__field { background:#25263c !important; color:#fff !important; caret-color:#fff; border:1px solid #646b91 !important; border-radius:4px; padding:8px !important; height:38px; font-size:14px; }
#bullion-customer-filter .select2-search__field::placeholder { color:#c2c8dc; opacity:1; }
#bullion-customer-filter .select2-search__field:focus { outline:2px solid #719bff; outline-offset:1px; }
#bullion-customer-filter .select2-results__options { max-height:240px !important; }
#bullion-customer-filter .select2-results__option { padding:10px 12px; color:#fff !important; background:#2e2d4d; font-size:14px; line-height:22px; }
#bullion-customer-filter .select2-results__option[aria-selected="true"] { background:#424969; color:#fff; }
#bullion-customer-filter .select2-results__option--highlighted { background:#306fdd !important; color:#fff !important; }
</style>
<form method="GET" id="bullion-customer-filter"><label for="bullion-customer">Bullion</label><select id="bullion-customer" class="form-control" name="customer_id" onchange="this.form.submit()"><option value="">Select bullion</option>
@foreach($customers as $customer)
<option value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string)$customer->id) }}" @selected($selected?->id === $customer->id)>{{ $customer->name }}</option>
@endforeach
</select></form>
@if($selected)
<div class="row my-4 bullion-balances">
@foreach(['cash'=>'Cash (INR)','gold'=>'Gold fine (g)','silver'=>'Silver fine (g)'] as $key=>$label)
<div class="col-md-4"><div class="bullion-balance bullion-balance-{{ $key }}"><span>{{ $label }}</span><h3>{{ number_format($balance[$key], $key === 'cash' ? 2 : 3) }}</h3></div></div>
@endforeach
</div>
<form method="POST" id="bullion-form" action="{{ route('company.bullion.store',$company->slug) }}">@csrf
<input type="hidden" name="customer_id" value="{{ $selected->id }}">
<div class="mb-3"><h2>New bullion entry</h2><div class="mt-2 d-flex flex-wrap" style="gap:10px">
<button type="button" class="btn btn-info" data-bullion-convert="convert_to_metal">Convert Rupees to Metal</button>
<button type="button" class="btn btn-info" data-bullion-convert="convert_to_rupees">Convert Metal to Rupees</button>
</div><p class="mt-2 mb-0" id="bullion-transaction-help" aria-live="polite"></p></div>
<div class="row">
<div class="col-md-4 mb-3"><label>Date</label><input class="form-control" name="entry_date" type="date" value="{{ old('entry_date',now()->toDateString()) }}" required></div>
<div class="col-md-4 mb-3"><label>Transaction</label><select class="form-control" name="entry_type">
@foreach(['receive_amount'=>'Receive cash deposit','receive_metal'=>'Receive fine metal','return_amount'=>'Return cash','return_metal'=>'Return fine metal (no cash conversion)','convert_to_metal'=>'Convert Rupees to Metal','convert_to_rupees'=>'Convert Metal to Rupees'] as $value=>$label)
<option value="{{ $value }}" @selected(old('entry_type') === $value)>{{ $label }}</option>
@endforeach
</select></div>
<div class="col-md-4 mb-3"><label>Metal</label><select class="form-control" name="metal_type"><option value="gold">Gold</option><option value="silver" @selected(old('metal_type') === 'silver')>Silver</option></select></div>
@foreach(['amount'=>'Cash amount (INR)','net_weight'=>'Net metal weight (g)','purity'=>'Purity (%)','rate'=>'Conversion rate (INR / pure gram)'] as $key=>$label)
<div class="col-md-3 mb-3"><label>{{ $label }}</label><input class="form-control" type="number" step="0.001" min="0" name="{{ $key }}" value="{{ old($key,$key === 'purity' ? 100 : '') }}" @if($key === 'purity') max="100" @endif></div>
@endforeach
<div class="col-md-4 mb-3"><label>Payment mode</label><select class="form-control" name="payment_mode"><option value="cash">Cash</option><option value="bank">Bank</option><option value="upi">UPI</option></select></div>
<div class="col-md-3 mb-3" id="bullion-converted-amount-field" hidden><label for="bullion-converted-amount">Amount (INR)</label><input id="bullion-converted-amount" class="form-control" type="text" value="0.00" readonly aria-live="polite" style="font-weight:700"><small>Calculated from fine weight × rate.</small></div>
<div class="col-md-8 mb-3"><label>Reference / remarks</label><input class="form-control" name="remarks" maxlength="120" value="{{ old('remarks') }}"></div>
</div>
<div class="alert alert-info" id="bullion-rate-notice" hidden>
<div id="bullion-live-status" aria-live="polite">Load the current market quote to calculate a rate.</div>
<div class="bullion-quote-controls">
<label>Quote weight unit <select class="form-control" id="bullion-quote-unit"><option value="10">10 grams</option><option value="1000">1 kilogram</option><option value="1">1 gram</option></select></label>
<label>Quote purity (%) <input class="form-control" id="bullion-quote-purity" type="number" min="0.001" max="100" step="0.001" value="99.9"></label>
<button type="button" class="btn btn-sm btn-info" id="bullion-load-rate">Use latest quote</button>
</div>
<div>Confirm the quoted unit and purity before saving. Rate = quote ÷ weight ÷ purity fraction. You may enter an agreed rate manually.</div>
</div>
<div id="bullion-preview" class="card card-body mb-3" aria-live="polite"></div>
<label class="bullion-confirm"><input type="checkbox" name="rate_confirmed" value="1" required> <span id="bullion-confirm-label">I have checked the transaction details.</span></label>
<div class="bullion-actions"><button class="btn btn-success">Save Bullion Entry</button></div>
</form>
<div class="bullion-ledger-heading"><h2>Bullion ledger</h2><a class="btn btn-info" href="{{ route('company.bullion.pdf', ['slug'=>$company->slug, 'customer_id'=>\Illuminate\Support\Facades\Crypt::encryptString((string)$selected->id)]) }}">Download PDF (Cr / Dr)</a></div><div class="bullion-ledger-panel"><table id="bullion-ledger-table" class="table table-bordered" style="width:100%"><thead><tr><th>Date</th><th>Transaction</th><th>Cash in</th><th>Cash out</th><th>Metal</th><th>Fine in</th><th>Fine out</th><th>Rate (INR / pure g)</th><th>Cash balance</th><th>Gold balance</th><th>Silver balance</th><th>Remarks</th></tr></thead><tbody>
@foreach($entries->reverse() as $entry)
<tr><td data-order="{{ $entry->entry_date->format('Ymd') }}{{ str_pad((string)$entry->id, 12, '0', STR_PAD_LEFT) }}"><span class="text-nowrap">{{ $entry->entry_date->format('d-m-Y') }}</span><br><small class="text-nowrap" title="Saved time (IST)">{{ $entry->created_at?->timezone('Asia/Kolkata')->format('h:i a') ?? 'Time unavailable' }}</small></td><td>{{ ucwords(str_replace('_',' ',$entry->entry_type)) }}</td><td>{{ number_format($entry->cash_in,2) }}</td><td>{{ number_format($entry->cash_out,2) }}</td><td>{{ $entry->metal_type }}</td><td>{{ number_format($entry->metal_in,3) }}</td><td>{{ number_format($entry->metal_out,3) }}</td><td>{{ number_format($entry->rate,2) }}</td><td>{{ number_format($entry->running['cash'],2) }}</td><td>{{ number_format($entry->running['gold'],3) }}</td><td>{{ number_format($entry->running['silver'],3) }}</td><td>{{ $entry->remarks }}</td></tr>
@endforeach
</tbody></table></div>
@endif
</div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
if (window.jQuery && jQuery.fn.DataTable) {
 jQuery('#bullion-ledger-table').DataTable({
   order: [[0, 'desc']],
   pageLength: 10,
   lengthMenu: [10, 25, 50, 100],
   scrollX: true,
   dom: '<"bullion-table-toolbar"lf>rt<"bullion-table-footer"ip>',
   columnDefs: [{targets: 0, type: 'string'}, {targets: [2, 3, 5, 6, 7, 8, 9, 10], className: 'text-right', type: 'num-fmt'}],
   language: {search: 'Search ledger:', emptyTable: 'No transactions yet. Save a bullion entry to start this customer’s ledger.'}
 });
}
if (window.jQuery && jQuery.fn.select2) {
 jQuery('#bullion-customer').select2({width:'100%',minimumResultsForSearch:0,dropdownParent:jQuery('#bullion-customer-filter')});
 jQuery('#bullion-customer').on('select2:open', function () {
   const search=document.querySelector('#bullion-customer-filter .select2-search__field');
   if(search){search.placeholder='Search bullion name…';search.setAttribute('aria-label','Search bullion name');search.focus();}
 });
}
const form=document.getElementById('bullion-form'); if(!form) return;
const before=@json($balance);
let quote=null, rateRequest=0;
const quoteUnit=document.getElementById('bullion-quote-unit'), quotePurity=document.getElementById('bullion-quote-purity');
function applyQuote(){
 if(!quote) return;
 const unit=Number(quoteUnit.value), purity=Number(quotePurity.value);
 if(unit<=0||purity<=0||purity>100) return;
 form.rate.value=(quote.rate/unit/(purity/100)).toFixed(2);
 form.rate_confirmed.checked=false;preview();
}
async function loadQuote(){
 const requestId=++rateRequest, metal=form.metal_type.value;
 const status=document.getElementById('bullion-live-status');
 quote=null;status.textContent='Loading current market quote…';
 try {
  const response=await fetch(@json(route('company.dashboard.metal-rates',$company->slug)),{headers:{Accept:'application/json'}});
  if(!response.ok) throw new Error();
  const payload=await response.json();
  if(requestId!==rateRequest) return;
  const symbol=metal==='gold'?'GLD 999 IMP AMD T+1':'SLV 999 (1 KG BAR) T+1';
  const row=(payload.rates||[]).find(r=>r.symbol===symbol&&r.available&&Number(r.rate)>0);
  if(!payload.enabled||!row) throw new Error();
  quote={rate:Number(row.rate)};
  status.textContent=symbol+' | Quote: INR '+quote.rate+' | Retrieved: '+payload.updated_at;
  applyQuote();
 } catch(error){if(requestId===rateRequest){form.rate.value='';form.rate_confirmed.checked=false;status.textContent='Live quote unavailable. Retry or enter an agreed rate manually.';preview();}}
}
document.getElementById('bullion-load-rate').addEventListener('click',loadQuote);
quoteUnit.addEventListener('change',applyQuote);quotePurity.addEventListener('input',applyQuote);
function preview(){
 const type=form.entry_type.value, metal=form.metal_type.value;
 form.querySelectorAll('[data-bullion-convert]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.bullionConvert===type)));
 const cashOnly=['receive_amount','return_amount'].includes(type);
 const conversion=type.startsWith('convert_');
 document.getElementById('bullion-transaction-help').textContent=type==='convert_to_metal'?'Select gold or silver and enter rupees plus the rate per pure gram. Rupees ÷ rate = fine grams credited.':type==='convert_to_rupees'?'Select gold or silver and enter weight, purity and rate per pure gram. Fine grams × rate = rupees credited.':type==='return_metal'?'Physical metal return: reduces fine balance only. Use Convert Metal to Rupees to credit cash instead.':'';
 const weightEntry=['receive_metal','return_metal','convert_to_rupees'].includes(type);
 const fields={metal_type:!cashOnly,amount:cashOnly||type==='convert_to_metal',net_weight:weightEntry,purity:weightEntry,rate:conversion||type==='receive_metal',payment_mode:cashOnly};
 Object.entries(fields).forEach(([name,visible])=>{
   const input=form.elements.namedItem(name);
   input.closest('.mb-3').hidden=!visible;
   input.disabled=!visible;
   input.required=visible;
 });
 document.getElementById('bullion-rate-notice').hidden=!(conversion||type==='receive_metal');
 form.rate.previousElementSibling.textContent=(metal==='gold'?'Gold':'Silver')+(type==='receive_metal'?' purchase rate':' conversion rate')+' (INR / pure gram)';
 document.getElementById('bullion-confirm-label').textContent=conversion?'I have checked the transaction and conversion rate.':cashOnly?'I have checked the cash amount and payment mode.':'I have checked the metal weight and purity.';
 if(type==='receive_metal') document.getElementById('bullion-confirm-label').textContent='I have checked the metal weight, purity and purchase rate.';
 const amount=Number(form.amount.value)||0, rate=Number(form.rate.value)||0;
 const fine=Math.round((Number(form.net_weight.value)||0)*(Number(form.purity.value)||0)/100*1000)/1000;
 document.getElementById('bullion-converted-amount-field').hidden=type!=='convert_to_rupees';
 document.getElementById('bullion-converted-amount').value=(Math.round(fine*rate*100)/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
 const after={...before};
 if(type==='receive_amount') after.cash+=amount;
 if(type==='return_amount') after.cash-=amount;
 if(type==='receive_metal') after[metal]+=fine;
 if(type==='return_metal') after[metal]-=fine;
 if(type==='convert_to_metal'&&rate>0){after.cash-=amount;after[metal]+=Math.round(amount/rate*1000)/1000;}
 if(type==='convert_to_rupees'&&rate>0){after[metal]-=fine;after.cash+=Math.round(fine*rate*100)/100;}
 document.getElementById('bullion-preview').textContent=cashOnly?'Cash balance: '+Number(before.cash).toFixed(2)+' → '+after.cash.toFixed(2)+' INR':(weightEntry?'Fine weight: '+fine.toFixed(3)+' g | ':'')+'After saving — Cash: '+after.cash.toFixed(2)+' INR; Gold: '+after.gold.toFixed(3)+' g; Silver: '+after.silver.toFixed(3)+' g';
 if(type==='receive_metal') document.getElementById('bullion-preview').textContent+=' | Metal value: '+(fine*rate).toFixed(2)+' INR (reference only; no cash movement)';
 if(conversion&&rate>0) document.getElementById('bullion-preview').textContent+=' | Conversion: '+(type==='convert_to_metal'?amount.toFixed(2)+' INR → '+(Math.round(amount/rate*1000)/1000).toFixed(3)+' g':fine.toFixed(3)+' g → '+(Math.round(fine*rate*100)/100).toFixed(2)+' INR');
}
form.addEventListener('input',preview);form.addEventListener('change',preview);
function resetRate(){++rateRequest;quote=null;form.rate.value='';form.rate_confirmed.checked=false;quoteUnit.value=form.metal_type.value==='gold'?'10':'1000';preview();if(form.entry_type.value==='receive_metal'||form.entry_type.value.startsWith('convert_'))loadQuote();}
form.entry_type.addEventListener('change',resetRate);
form.metal_type.addEventListener('change',resetRate);preview();
form.querySelectorAll('[data-bullion-convert]').forEach(button=>button.addEventListener('click',()=>{
 form.entry_type.value=button.dataset.bullionConvert;
 form.entry_type.dispatchEvent(new Event('change',{bubbles:true}));
 form.elements.namedItem(button.dataset.bullionConvert==='convert_to_metal'?'amount':'net_weight').focus();
}));
quoteUnit.value=form.metal_type.value==='gold'?'10':'1000';
if(!form.rate.value&&(form.entry_type.value==='receive_metal'||form.entry_type.value.startsWith('convert_')))loadQuote();
});
</script>
@endpush
