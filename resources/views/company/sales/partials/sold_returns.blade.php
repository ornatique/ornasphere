<section id="sold-return-panel" style="display:none" class="mb-4" role="tabpanel" aria-labelledby="return-items-tab">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h5 class="mb-0">Return sold items</h5>
    </div>
    <p>Select items to receive back into stock. Values come from the original bills. Cash refunds are separate.</p>
    <p class="text-muted">If a bill used metal balance, its remaining cash metal charge is allocated proportionally by item metal amount.</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('company.sales.advance.return.store',$company->slug) }}" id="sold-return-form">
        @csrf
        <input type="hidden" name="customer_id" id="sold-return-customer">
        <div class="row mb-3">
            <div class="col-md-3"><label>Return date</label><input type="date" name="return_date" class="form-control" required value="{{ old('return_date', date('Y-m-d')) }}"></div>
            <div class="col-md-6"><label>Return reason / remarks</label><input name="reason" class="form-control" required maxlength="2000" value="{{ old('reason') }}"></div>
            <div class="col-md-3"><label>Search barcode / label / item / bill</label><input id="sold-return-search" class="form-control" type="search" placeholder=""></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-5"><label for="sold-return-item-search">Item Name</label><input id="sold-return-item-search" class="form-control" type="search" placeholder="Example: MANGALSUTRA"></div>
            <div class="col-md-5"><label for="sold-return-voucher-search">Voucher Number</label><input id="sold-return-voucher-search" class="form-control" type="search" placeholder="Example: SL1790254194"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-secondary" id="sold-return-clear-search">Clear Filters</button></div>
        </div>
        <div id="sold-return-filter-status" role="status"></div>
        <div id="sold-return-status" role="status"></div>
        <div style="max-height:420px;overflow:auto" tabindex="0" aria-label="Sold items">
        <table class="table table-bordered"><thead style="position:sticky;top:0;background:#2b2f4a;z-index:1"><tr>
        <th><input type="checkbox" id="sold-return-all" aria-label="Select visible items"></th><th>Bill / Date</th><th>Label / Item</th><th>Gross Wt</th><th>Other Wt</th><th>Net Wt</th><th>Purity</th><th>Waste %</th><th>Fine Wt</th><th>Original Amount</th>
        </tr></thead><tbody id="sold-return-rows"></tbody></table></div>
        <p class="mt-2"><strong id="sold-return-count">0 items selected</strong></p>
        <button class="btn btn-success" id="sold-return-save" disabled>Save Sales Return</button>
    </form>
    <h5 class="mt-4">Sales return history</h5><div id="sold-return-history"></div>
</section>
@push('scripts')
<script>
$(function () {
    let request = null, sequence = 0, previousEntryType = 'receive_amount', loadedCustomer = null;
    const esc = value => $('<div>').text(value == null ? '' : value).html();
    const number = (value, digits) => Number(value || 0).toFixed(digits);
    function count() {
        const n = $('#sold-return-rows input:checked').length;
        $('#sold-return-count').text(n + ' items selected');
        $('#sold-return-save').prop('disabled', n === 0);
    }
    function search() {
        const q = $('#sold-return-search').val().trim().toLowerCase();
        const item = $('#sold-return-item-search').val().trim().toLowerCase();
        const voucher = $('#sold-return-voucher-search').val().trim().toLowerCase();
        let matches = 0;
        $('#sold-return-rows tr').each(function () {
            const row = $(this);
            const match = row.text().toLowerCase().includes(q)
                && String(row.data('item') || '').toLowerCase().includes(item)
                && String(row.data('voucher') || '').toLowerCase().includes(voucher);
            row.toggle(match);
            if (match) matches++;
        });
        $('#sold-return-filter-status').text(matches + ' matching items');
        $('#sold-return-all').prop('checked', false);
    }
    function load() {
        if ($('#entry_type').val() !== 'sales_return') return;
        const customer = $('#entry_customer_id').val() || $('#customer_id').val();
        loadedCustomer = null;
        $('#sold-return-customer').val(customer);
        $('#sold-return-rows, #sold-return-history').empty();
        $('#sold-return-all').prop('checked',false);
        count();
        const current = ++sequence;
        if (request) request.abort();
        if (!customer) { $('#sold-return-status').text('Select a customer first.'); return; }
        $('#sold-return-status').text('Loading sold items…');
        request = $.get(@json(route('company.sales.advance.soldItems',$company->slug)), {customer_id:customer})
        .done(function(data) {
            if (current !== sequence) return;
            loadedCustomer = String(customer);
            $('#sold-return-status').text(data.items.length ? 'Select one or more items below.' : 'No eligible sold items found.');
            data.items.forEach(function(i) {
                const codes = [...new Set([i.label,i.barcode,i.huid].filter(Boolean))].map(esc).join(' / ');
                $('#sold-return-rows').append('<tr><td><input type="checkbox" name="sale_item_ids[]" value="'+ Number(i.id) +'"></td><td>'+esc(i.bill)+'<br>'+esc(String(i.date || '').slice(0,10))+'</td><td>'+codes+'<br>'+esc(i.name)+'</td>'+['gross','other','net','purity','waste','fine'].map(k=>'<td>'+number(i[k],3)+'</td>').join('')+'<td>Rs. '+number(i.amount,2)+'</td></tr>');
                $('#sold-return-rows tr:last').data('item', i.name || '').data('voucher', i.bill || '');
            });
            search();
            data.history.forEach(function(r) {
                const p = $('<p>').text(r.number+' · '+String(r.date).slice(0,10)+' · Rs. '+number(r.amount,2)+' ');
                p.append($('<a>').attr({href:r.url,target:'_blank',rel:'noopener'}).text('Download PDF'));
                $('#sold-return-history').append(p);
            });
            if (!data.history.length) $('#sold-return-history').text('No sales returns yet.');
        }).fail(function(xhr,status) { if (status !== 'abort') $('#sold-return-status').text('Could not load sold items. Select the customer again to retry.'); });
    }
    function mode() {
        const active = $('#entry_type').val() === 'sales_return';
        if (!active) previousEntryType = $('#entry_type').val() || 'receive_amount';
        $('#sold-return-panel').toggle(active);
        $('#receive-purchase-panel').toggle(!active);
        $('#return-items-tab').toggleClass('btn-primary',active).toggleClass('btn-outline-primary',!active).attr('aria-selected',String(active)).attr('tabindex',active ? 0 : -1);
        $('#receive-purchase-tab').toggleClass('btn-primary',!active).toggleClass('btn-outline-primary',active).attr('aria-selected',String(!active)).attr('tabindex',active ? -1 : 0);
        $('#advanceSaleTable').closest('.advance-sale-grid-wrap').toggle(!active);
        $('#advance_item_name_search').closest('.d-flex').toggleClass('d-none',active);
        $('#btnSaveAdvanceItems').closest('.d-flex').toggleClass('d-none',active);
        $('#advanceForm button, #advanceForm #entry_payment_mode, #advanceForm #amount').prop('disabled',active);
        if (!active && previousEntryType === 'receive_metal') $('#entry_payment_mode').prop('disabled',true);
        if (active && loadedCustomer !== String($('#entry_customer_id').val() || $('#customer_id').val())) load();
        if (!active && $.fn.dataTable) $.fn.dataTable.tables({visible:true,api:true}).columns.adjust();
    }
    $('#entry_type').on('change',mode);
    $('#customer_id, #entry_customer_id').on('change',function () {
        $('#customer_id, #entry_customer_id').val($(this).val()).trigger('change.select2');
        load();
    });
    $('#btnLoadCustomer').on('click',function () {
        $('#entry_customer_id').val($('#customer_id').val()).trigger('change.select2');
        load();
    });
    $('#return-items-tab').on('click',function () {
        $('#entry_type').val('sales_return').trigger('change');
    });
    $('#receive-purchase-tab').on('click',function () {
        $('#entry_type').val(previousEntryType).trigger('change');
    });
    $('#return-items-tab, #receive-purchase-tab').on('keydown',function(e) {
        if (!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) return;
        e.preventDefault();
        const target = e.key === 'Home' ? '#return-items-tab' : e.key === 'End' ? '#receive-purchase-tab' : this.id === 'return-items-tab' ? '#receive-purchase-tab' : '#return-items-tab';
        $(target).trigger('click').trigger('focus');
    });
    $('#sold-return-rows').on('change','input',count);
    $('#sold-return-all').on('change',function(){ $('#sold-return-rows tr:visible input').prop('checked',this.checked); count(); });
    $('#sold-return-search, #sold-return-item-search, #sold-return-voucher-search').on('input',search);
    $('#sold-return-clear-search').on('click',function () {
        $('#sold-return-search, #sold-return-item-search, #sold-return-voucher-search').val('');
        search();
    });
    $('#sold-return-form').on('submit',function(e){ if (!$('#sold-return-rows input:checked').length) {e.preventDefault(); return;} $('#sold-return-save').prop('disabled',true).text('Saving…'); });
    setTimeout(function () {
    @if(session('sales_return_saved') || old('sale_item_ids'))
    @if(old('customer_id'))
    $('#customer_id, #entry_customer_id').val(@json((string)old('customer_id'))).trigger('change.select2');
    @endif
    $('#entry_type').val('sales_return').trigger('change');
    @endif
    mode();
    }, 0);
});
</script>
@endpush
