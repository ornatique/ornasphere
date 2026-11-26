(() => {
    const form = document.getElementById('order-list-filters');
    if (!form) return;
    const search = document.getElementById('order-list-search');
    const customer = document.getElementById('order-list-customer');
    const fromDate = document.getElementById('order-list-from');
    const toDate = document.getElementById('order-list-to');
    const results = document.getElementById('order-list-results');
    const status = document.getElementById('order-list-status');
    let timer, controller, sequence = 0;
    function cancel() { clearTimeout(timer); controller?.abort(); sequence++; }
    async function load(pageUrl) {
        cancel(); const current = sequence;
        if (fromDate.value && toDate.value && toDate.value < fromDate.value) {
            status.textContent = 'To Date must be on or after From Date.';
            results.setAttribute('aria-busy', 'false'); return;
        }
        const url = new URL(pageUrl || form.action, window.location.origin);
        if (!pageUrl) {
            url.searchParams.delete('page');
            if (search.value.trim()) url.searchParams.set('search', search.value.trim());
            if (customer.value) url.searchParams.set('customer_id', customer.value);
            if (fromDate.value) url.searchParams.set('from_date', fromDate.value);
            if (toDate.value) url.searchParams.set('to_date', toDate.value);
        }
        controller = new AbortController();
        status.textContent = 'Loading orders…'; results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal:controller.signal, headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Could not load orders. Please try again.');
            const data = await response.json();
            if (current !== sequence) return;
            if (typeof data.html !== 'string') throw new Error('Could not load orders. Refresh the page and try again.');
            results.innerHTML = data.html;
            window.history.replaceState(null, '', url.pathname + url.search);
            status.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && current === sequence) status.textContent = error.message;
        } finally { if (current === sequence) results.setAttribute('aria-busy', 'false'); }
    }
    search.addEventListener('input', () => { cancel(); timer = setTimeout(() => load(), 250); });
    customer.addEventListener('change', () => load());
    fromDate.addEventListener('change', () => load());
    toDate.addEventListener('change', () => load());
    form.addEventListener('submit', event => { event.preventDefault(); load(); });
    results.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || !link.closest('.pagination') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); load(link.href);
    });
})();
