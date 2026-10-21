@extends('company_layout.admin')
@section('content')
<style>
.gst-page { color:#eef1fb; }
.gst-page .gst-shell { border:1px solid #45465e; border-radius:16px; background:#262638; }
.gst-page .gst-header { display:block; padding:28px; background:#2d2d44; border-radius:16px 16px 0 0; }
.gst-page h1 { font-size:30px; font-weight:700; color:#fff; margin:0 0 10px; }
.gst-page .gst-eyebrow { color:#aebfff; font-size:11px; letter-spacing:1.5px; font-weight:700; margin-bottom:10px; }
.gst-page .gst-subtitle { color:#c2c8dd; line-height:1.7; margin:0; }
.gst-page .card-body { padding:28px; }
.gst-page #gst-search-form { padding:22px; background:#2d2e45; border:1px solid #484b65; border-radius:12px; }
.gst-page label { font-size:14px; font-weight:600; margin-bottom:10px; }
.gst-page .gst-search-controls { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
.gst-page #gst-search-input { flex:1 1 260px; max-width:480px; height:48px; background:#232438; border:1px solid #636b8e; border-radius:8px; color:#fff; font-size:16px; letter-spacing:1px; text-transform:uppercase; }
.gst-page #gst-search-input:focus { border-color:#a1b4ff; box-shadow:0 0 0 3px #829aff30; }
.gst-page .btn { border-radius:8px; min-height:46px; padding:12px 20px; font-weight:600; color:#fff; }
.gst-page .btn-primary { background:#4869da; border-color:#4869da; }
.gst-page .btn-secondary { background:transparent; border:1px solid #68708c; color:#dbe3ff; }
.gst-page .btn-success { background:#16815f; border-color:#35b58f; }
.gst-page .btn:focus-visible { outline:3px solid #b5c5ff; outline-offset:3px; }
.gst-page .gst-hint { color:#bec5da; font-size:13px; margin:12px 0 0; line-height:1.6; }
.gst-page #gst-message:empty { display:none; }
.gst-page #gst-message { padding:12px 16px; border-radius:8px; background:#30374f; color:#dae5ff; line-height:1.6; }
.gst-page #gst-message[data-state="error"] { background:#492b38; color:#ffcfdb; border:1px solid #945067; }
.gst-page #gst-message[data-state="success"] { background:#233e38; color:#bcebdc; }
.gst-page .gst-result-heading { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin:24px 0 20px; }
.gst-page #gst-business-name { color:#fff; font-size:24px; font-weight:700; margin:0; overflow-wrap:anywhere; }
.gst-page .gst-group { border:1px solid #484b65; border-radius:12px; margin-bottom:18px; padding:20px; }
.gst-page .gst-group h3 { color:#b8c9ff; font-size:16px; font-weight:600; margin:0 0 18px; }
.gst-page .gst-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px 28px; }
.gst-page .gst-field-label { color:#b7bfd6; font-size:12px; margin-bottom:7px; }
.gst-page .gst-field-value { color:#f4f5fb; font-size:14px; line-height:1.65; overflow-wrap:anywhere; font-weight:500; }
.gst-page .gst-field-wide { grid-column:1/-1; }
.gst-page .gst-missing { color:#a0a8bf; font-weight:400; }
.gst-page .gst-status { display:inline-block; padding:3px 12px; border-radius:20px; background:#3b3c51; }
.gst-page .gst-status-active { background:#204b40; color:#a9f0d6; }
.gst-page [hidden] { display:none !important; }
@media(max-width:767px) { .gst-page .gst-header,.gst-page .card-body { padding:20px 16px; } .gst-page #gst-search-form { padding:16px; } .gst-page .gst-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .gst-page .gst-search-controls .btn { flex:1; } }
@media(max-width:480px) { .gst-page .gst-grid { grid-template-columns:1fr; } .gst-page .gst-result-heading .btn { width:100%; } }
</style>
<div class="content-wrapper gst-page"><div class="card gst-shell"><div class="card-header gst-header">
<div class="gst-eyebrow">CUSTOMER ONBOARDING</div>
<h1>GST Search</h1>
<p class="gst-subtitle">Find a business by GSTIN and use its details to create a customer.</p>
</div><div class="card-body">
@if(!config('services.gstin.key'))
<div class="alert alert-warning" role="status">GST search is awaiting API setup. Your administrator must configure GSTIN_API_KEY on this server and refresh the Laravel configuration cache. You can enter customer details manually meanwhile.</div>
@endif
<form id="gst-search-form" class="mb-4">
    @csrf
    <label for="gst-search-input">GST number</label>
    <div class="gst-search-controls">
        <input id="gst-search-input" name="gstin" class="form-control" maxlength="15" required placeholder="Enter 15-character GSTIN" autocomplete="off" aria-describedby="gst-search-hint">
        <button class="btn btn-primary" id="gst-search-button" @disabled(!config('services.gstin.key'))>Search GSTIN</button>
        <a class="btn btn-secondary" href="{{ route('company.customers.create', $company->slug) }}">Enter customer manually</a>
    </div>
    <p class="gst-hint" id="gst-search-hint">1. Search GSTIN &nbsp; → &nbsp; 2. Review business details &nbsp; → &nbsp; 3. Add customer</p>
</form>
<div id="gst-message" role="status" aria-live="polite" class="mb-3"></div>
<div id="gst-result" hidden>
    <div class="gst-result-heading"><div><div class="gst-eyebrow">BUSINESS DETAILS</div><h2 id="gst-business-name"></h2></div><a id="gst-add-customer" class="btn btn-success">Add Customer →</a></div>
    <div id="gst-fields"></div>
    <p class="gst-hint">Add Customer opens an editable form with Customer selected. Review the details and fill any missing fields before saving.</p>
</div>
</div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('gst-search-form');
    const input = document.getElementById('gst-search-input');
    const result = document.getElementById('gst-result');
    const message = document.getElementById('gst-message');
    const button = document.getElementById('gst-search-button');
    let revision = 0;
    input.addEventListener('input', () => { revision++; result.hidden = true; message.textContent = ''; });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (@json(!config('services.gstin.key'))) return;
        const current = ++revision;
        input.value = input.value.trim().toUpperCase();
        result.hidden = true;
        message.textContent = 'Looking up registered business details…';
        message.dataset.state = 'loading';
        button.textContent = 'Searching…';
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        try {
            const response = await fetch(@json(route('company.gst-search.create', $company->slug)), {
                method: 'POST', body: new FormData(form), headers: {Accept: 'application/json'}, credentials: 'same-origin'
            });
            const payload = await response.json();
            if (current !== revision) return;
            if (!response.ok) throw new Error(payload.errors?.gstin?.[0] || payload.message || 'GST lookup failed. Please try again.');
            const fields = document.getElementById('gst-fields');
            document.getElementById('gst-business-name').textContent = payload.data.name;
            fields.replaceChildren();
            const labels = {name:'Business name', legal_name:'Legal name', gst_no:'GSTIN', pan_no:'PAN', status:'Registration status', address:'Address', city:'City', state:'State', pincode:'Pincode', entity_type:'Entity type', nature_of_business:'Nature of business', department:'Department', registration_type:'Registration type', registration_date:'Registration date'};
            Object.entries({'Business identity':['name','legal_name','gst_no','pan_no','status','entity_type'], 'Registered address':['address','city','state','pincode'], 'Registration details':['registration_type','registration_date','department','nature_of_business']}).forEach(([heading, keys]) => {
                const section = document.createElement('section'); section.className = 'gst-group';
                const headingNode = document.createElement('h3'); headingNode.textContent = heading;
                const grid = document.createElement('div'); grid.className = 'gst-grid';
                keys.forEach(key => {
                    const field = document.createElement('div'); if (['address','nature_of_business'].includes(key)) field.className = 'gst-field-wide';
                    const label = document.createElement('div'); label.className = 'gst-field-label'; label.textContent = labels[key];
                    const value = document.createElement('div'); value.className = 'gst-field-value';
                    let text = payload.data[key];
                    if (key === 'registration_date' && /^\d{4}-\d{2}-\d{2}$/.test(text || '')) text = text.split('-').reverse().join('-');
                    value.textContent = text || 'Not provided';
                    if (!text) value.classList.add('gst-missing');
                    if (key === 'status') { value.classList.add('gst-status'); if ((text || '').toLowerCase() === 'active') value.classList.add('gst-status-active'); }
                    field.append(label,value); grid.append(field);
                });
                section.append(headingNode,grid); fields.append(section);
            });
            const add = document.getElementById('gst-add-customer');
            add.href = payload.create_url; add.hidden = payload.existing;
            message.textContent = payload.existing ? 'This GSTIN already exists in your company’s person list. Open the existing record instead of adding a duplicate.' : 'Business details found. Review them before continuing.';
            result.hidden = false;
            message.dataset.state = 'success';
        } catch (error) {
            if (current === revision) message.dataset.state = 'error';
            if (current === revision) message.textContent = error instanceof SyntaxError ? 'The server could not complete the search. Refresh and try again.' : error.message;
        } finally { button.disabled = false; button.textContent = 'Search GSTIN'; form.setAttribute('aria-busy', 'false'); }
    });
});
</script>
@endpush
