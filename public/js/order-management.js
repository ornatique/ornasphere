/* Stock-item orders: DOM text is always rendered as text, never as user HTML. */
window.OrderManagement = {
    calculate(item, priceMode = null) {
        const num = value => Number(value || 0);
        const round = (value, digits) => Number(num(value).toFixed(digits));
        item.gross_weight = round(item.gross_weight, 3);
        item.other_weight = round(item.other_weight ?? Math.max(0, item.gross_weight - num(item.net_weight)), 3);
        item.net_weight = round(Math.max(0, item.gross_weight - item.other_weight), 3);
        item.net_purity = round(round(item.purity, 3) + round(item.waste_percent, 3), 3);
        item.total_fine_weight = round(item.net_weight * item.net_purity / 100, 3);
        const metal = item.net_weight * round(item.metal_rate, 2);
        const labour = item.net_weight * round(item.labour_rate, 2);
        item.metal_amount = round(metal, 2); item.labour_amount = round(labour, 2);
        item.total_amount = round((metal + labour + round(item.other_amount, 2)) * num(item.quantity), 2);
        if (priceMode) {
            const sale = num(item.sale_unit_price ?? item.sale_price);
            const wholesale = num(item.wholesale_unit_price ?? item.wholesale_price);
            const chosen = priceMode === 'wholesale' ? wholesale : sale;
            item.billing_sale_price = round(chosen > 0 ? chosen : Math.max(0, priceMode === 'wholesale' ? sale : wholesale), 2);
        }
        const billingPrice = num(item.billing_sale_price ?? item.sale_price);
        if (billingPrice > 0) {
            item.metal_amount = item.labour_amount = item.total_fine_weight = item.other_amount = 0;
            item.total_amount = round(billingPrice * num(item.quantity), 2);
        }
        return item;
    },
    init({rows, searchUrl, priceMode = null}) {
        const byId = id => document.getElementById(id);
        const form = byId('managed-order-form');
        if (!form) return;
        const selected = new Map(rows.map(row => [String(row.item_set_id), row]));
        let activePriceMode = priceMode;
        const status = byId('search-status');
        const results = byId('item-results');
        const preview = byId('order-image-preview');
        const previewImage = byId('order-preview-image');
        function openImage(item) {
            byId('order-preview-title').textContent = item.item_name || 'Item image';
            byId('order-preview-message').textContent = '';
            previewImage.hidden = false; previewImage.alt = item.item_name || 'Item image'; previewImage.src = item.image_url;
            if (!preview.open) preview.showModal();
        }
        byId('order-preview-close').addEventListener('click', () => preview.close());
        preview.addEventListener('click', event => { if (event.target === preview) preview.close(); });
        preview.addEventListener('close', () => { previewImage.removeAttribute('src'); });
        previewImage.addEventListener('error', () => { previewImage.hidden = true; byId('order-preview-message').textContent = 'Image unavailable.'; });
        let requestNumber = 0, timer, controller, busy = false;
        const number = value => Number(value || 0);
        const cell = (row, text) => { const td = document.createElement('td'); td.textContent = text; row.append(td); return td; };
        const otherWeight = row => Math.max(0, number(row.gross_weight) - number(row.net_weight));
        function closeSearch() {
            clearTimeout(timer); controller?.abort(); requestNumber++;
            results.replaceChildren(); results.hidden = true; byId('item-scan').setAttribute('aria-expanded', 'false');
        }
        function totals() {
            let qty = 0, gross = 0, net = 0, other = 0, amount = 0;
            selected.forEach(row => { window.OrderManagement.calculate(row); qty += number(row.quantity); gross += number(row.gross_weight) * number(row.quantity); net += number(row.net_weight) * number(row.quantity); other += otherWeight(row) * number(row.quantity); amount += number(row.total_amount); });
            byId('order-total-count').textContent = qty;
            byId('order-total-gross').textContent = gross.toFixed(3);
            byId('order-total-other').textContent = other.toFixed(3);
            byId('order-total-net').textContent = net.toFixed(3);
            byId('order-total-amount').textContent = amount.toFixed(2);
        }
        function filterRows() {
            const query = byId('selected-item-search').value.trim().toLowerCase();
            byId('selected-items').querySelectorAll('tr[data-item-search]').forEach(row => { row.hidden = !row.dataset.itemSearch.includes(query); });
        }
        function render() {
            const body = byId('selected-items'); body.replaceChildren();
            const showWholesale = activePriceMode !== 'sale' && [...selected.values()].some(item => number(item.wholesale_price) > 0);
            const showSale = activePriceMode === null || !showWholesale;
            byId('order-wholesale-heading').hidden = !showWholesale;
            byId('order-sale-heading').hidden = !showSale;
            let index = 0;
            selected.forEach((item, key) => {
                window.OrderManagement.calculate(item);
                const row = document.createElement('tr');
                row.dataset.itemSearch = [item.item_name, item.huid, item.qr_code, item.barcode].filter(Boolean).join(' ').toLowerCase();
                const label = cell(row, item.qr_code || item.huid || item.barcode || '—');
                const name = document.createElement('span'); name.className = 'order-label-name'; name.textContent = item.item_name; label.append(name);
                if (item.huid && item.qr_code && item.huid !== item.qr_code) { const huid = document.createElement('small'); huid.textContent = `HUID: ${item.huid}`; huid.className = 'd-block'; label.append(huid); }
                const qtyCell = cell(row, '');
                const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = `items[${index}][item_set_id]`; hidden.value = key;
                const input = document.createElement('input'); input.type = 'number'; input.min = '1'; input.max = '1000000'; input.step = '1'; input.required = true; input.value = item.quantity || 1; input.className = 'form-control'; input.style.minWidth = '90px'; input.name = `items[${index}][quantity]`;
                const derived = new Map();
                function refreshRow() {
                    window.OrderManagement.calculate(item);
                    derived.forEach((control, field) => { control.value = number(item[field]).toFixed(['net_weight', 'net_purity', 'total_fine_weight'].includes(field) ? 3 : 2); });
                    totals();
                }
                input.addEventListener('input', () => { item.quantity = input.value; refreshRow(); }); qtyCell.append(hidden, input);
                const fields = ['gross_weight','other_weight','net_weight','purity','waste_percent','net_purity','total_fine_weight','metal_rate','metal_amount','labour_rate','labour_amount','other_amount','sale_price','wholesale_price','total_amount','remarks'];
                const readOnly = ['net_weight','net_purity','total_fine_weight','metal_amount','labour_amount','sale_price','wholesale_price','total_amount'];
                fields.forEach(field => {
                    if (field === 'wholesale_price' && !showWholesale) return;
                    if (field === 'sale_price' && !showSale) return;
                    const control = document.createElement('input'); const decimals = ['gross_weight','other_weight','net_weight','purity','waste_percent','net_purity','total_fine_weight'].includes(field) ? 3 : 2;
                    control.type = field === 'remarks' ? 'text' : 'number'; control.className = 'form-control order-weight';
                    control.value = field === 'remarks' ? (item[field] || '') : number(item[field]).toFixed(decimals);
                    if (field === 'wholesale_price' && number(item[field]) <= 0) { control.type = 'text'; control.value = '—'; }
                    control.readOnly = readOnly.includes(field);
                    if (control.readOnly) { control.tabIndex = -1; if (field !== 'wholesale_price') derived.set(field, control); }
                    else {
                        control.name = `items[${index}][${field}]`;
                        if (field === 'remarks') control.maxLength = 1000;
                        else { control.min = '0'; control.step = decimals === 3 ? '0.001' : '0.01'; control.required = true; control.max = ['purity','waste_percent'].includes(field) ? '100' : ['gross_weight','other_weight'].includes(field) ? '999999' : '999999999'; }
                        control.addEventListener('input', () => { item[field] = control.value; refreshRow(); });
                    }
                    cell(row, '').append(control);
                });
                const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-danger'; remove.textContent = 'Remove'; remove.addEventListener('click', () => { selected.delete(key); render(); }); cell(row, '').append(remove);
                const imageCell = cell(row, item.image_url ? '' : 'No image'); imageCell.style.minWidth = '90px';
                if (item.image_url) {
                    const image = document.createElement('img'); image.src = item.image_url; image.alt = item.item_name || 'Item image'; image.loading = 'lazy';
                    image.style.width = '64px'; image.style.height = '64px'; image.style.objectFit = 'contain';
                    image.style.cursor = 'zoom-in'; image.tabIndex = 0; image.setAttribute('role', 'button'); image.setAttribute('aria-label', `View image: ${item.item_name || 'Item'}`);
                    image.addEventListener('click', () => openImage(item));
                    image.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openImage(item); } });
                    image.addEventListener('error', () => { imageCell.replaceChildren(); imageCell.textContent = 'No image'; }); imageCell.append(image);
                }
                body.append(row); index++;
            });
            if (!selected.size) { const row = document.createElement('tr'); const td = cell(row, 'Search and select items to add them to this order.'); td.colSpan = showWholesale ? 20 : 19; body.append(row); }
            totals(); filterRows();
        }
        function add(item) {
            const key = String(item.item_set_id);
            if (selected.has(key)) { status.textContent = 'This item is already added.'; return; }
            if (selected.size >= 200) { status.textContent = 'An order can contain up to 200 items.'; return; }
            selected.set(key, window.OrderManagement.calculate({...item, quantity: 1}, activePriceMode));
            closeSearch(); byId('item-scan').value = ''; render(); status.textContent = 'Item added.'; byId('item-scan').focus();
        }
        async function search(scan = false) {
            clearTimeout(timer);
            const keyword = byId('item-scan').value.trim();
            const id = ++requestNumber;
            controller?.abort(); controller = new AbortController();
            status.textContent = 'Searching…'; results.replaceChildren(); results.hidden = true; byId('item-scan').setAttribute('aria-expanded', 'false');
            const url = new URL(searchUrl, window.location.origin); url.searchParams.set('keyword', keyword);
            if (byId('item-filter').value) url.searchParams.set('item_id', byId('item-filter').value);
            selected.forEach((item, key) => url.searchParams.append('exclude[]', key));
            try {
                const response = await fetch(url, {signal: controller.signal, headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error('Search failed. Refresh the page or try again.');
                const responseItems = await response.json(); if (id !== requestNumber) return;
                // Filter again at response time in case an item was selected while a search was running.
                const items = responseItems.filter(item => !selected.has(String(item.item_set_id)));
                if (scan) {
                    const exact = items.filter(item => [item.huid, item.qr_code, item.barcode].some(code => code && String(code).toLowerCase() === keyword.toLowerCase()));
                    if (exact.length === 1) { add(exact[0]); byId('item-scan').value = ''; byId('item-scan').focus(); return; }
                }
                status.textContent = items.length ? `${items.length} result(s). Select an item to add it.${items.length === 50 ? ' Refine your search for more results.' : ''}` : 'No available items found.';
                items.forEach(item => {
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'list-group-item list-group-item-action';
                    const code = document.createElement('span'); code.className = 'd-block'; code.textContent = item.qr_code || item.huid || item.barcode || 'Unlabelled';
                    const name = document.createElement('small'); name.className = 'd-block'; name.textContent = item.item_name;
                    button.append(code, name); button.addEventListener('click', () => add(item)); results.append(button);
                });
                results.hidden = !items.length; byId('item-scan').setAttribute('aria-expanded', String(items.length > 0));
            } catch (error) { if (error.name !== 'AbortError' && id === requestNumber) status.textContent = error.message; }
        }
        byId('item-scan').addEventListener('input', () => { closeSearch(); if (!byId('item-scan').value.trim()) { status.textContent = ''; return; } timer = setTimeout(() => search(), 300); });
        byId('item-scan').addEventListener('focus', () => { if (byId('item-scan').value.trim()) search(); });
        byId('item-scan').addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); search(true); }
            if (event.key === 'Escape') closeSearch();
            if (event.key === 'ArrowDown' && !results.hidden) { event.preventDefault(); results.querySelector('button')?.focus(); }
        });
        results.addEventListener('keydown', event => {
            if (event.key === 'Escape') { byId('item-scan').focus(); closeSearch(); }
            if (event.key === 'ArrowDown') { event.preventDefault(); document.activeElement.nextElementSibling?.focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); document.activeElement.previousElementSibling?.focus(); }
        });
        document.addEventListener('click', event => { if (!event.target.closest('.order-search-wrap') && !event.target.closest('#order-item-picker')) closeSearch(); });
        byId('selected-item-search').addEventListener('input', filterRows);
        (document.querySelectorAll?.('[data-order-apply]') || []).forEach(button => button.addEventListener('click', () => {
            const first = selected.values().next().value;
            if (!first) return;
            const field = button.dataset.orderApply;
            selected.forEach(item => { item[field] = first[field] ?? 0; }); render();
        }));
        ['customer-city', 'customer-address'].forEach(id => byId(id).addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); }));
        function customerFields() {
            const other = byId('order-customer').value === 'other';
            byId('new-customer').hidden = !other; byId('new-customer').disabled = !other; byId('customer-name').required = other;
        }
        ['order-price-sale', 'order-price-wholesale'].forEach(id => {
            byId(id)?.addEventListener('change', event => {
                if (!event.target.checked) return;
                activePriceMode = event.target.value;
                selected.forEach(item => window.OrderManagement.calculate(item, activePriceMode));
                render();
            });
        });
        if (window.jQuery && window.jQuery.fn.select2) {
            const $customer = window.jQuery(byId('order-customer'));
            $customer.select2({
                theme: 'bootstrap4', width: '100%', minimumResultsForSearch: 0,
                placeholder: 'Select customer', dropdownParent: window.jQuery(byId('order-customer-picker'))
            });
            $customer.on('change.orderManagement', customerFields);
            const $items = window.jQuery(byId('item-filter'));
            $items.select2({
                theme: 'bootstrap4', width: '100%', minimumResultsForSearch: 0,
                dropdownParent: window.jQuery(byId('order-item-picker'))
            });
            $items.on('change.orderManagement', () => search());
        } else {
            byId('order-customer').addEventListener('change', customerFields);
            byId('item-filter').addEventListener('change', () => search());
        }
        customerFields(); render();
        form.addEventListener('submit', event => {
            if (busy) { event.preventDefault(); return; }
            if (!selected.size) { event.preventDefault(); status.textContent = 'Add at least one item before saving.'; byId('item-scan').focus(); return; }
            busy = true; const button = byId('save-order'); button.disabled = true; button.setAttribute('aria-busy', 'true');
            const spinner = document.createElement('span'); spinner.className = 'spinner-border spinner-border-sm mr-2'; spinner.setAttribute('aria-hidden', 'true'); button.replaceChildren(spinner, document.createTextNode('Saving…'));
        });
        window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    }
};
