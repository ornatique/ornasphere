(() => {
    'use strict';
    const message = 'Read-only while company data is archived. Restore or cancel the archive to make changes.';
    const allowedAccountPath = path => /\/profile\/archive(?:\/|$)|\/logout\/?$|\/2fa\//i.test(path);
    const mutationPath = path => /\/(?:create|edit|delete|destroy|finalize|generate|reserve|upload|process|toggle-status|reset-2fa|print-direct|print-labels)(?:\/|$)/i.test(path);
    const pathOf = value => { try { return new URL(value, location.href).pathname; } catch { return ''; } };
    const allowedForm = form => {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        return allowedAccountPath(pathOf(form.action)) || (method === 'get' && !mutationPath(pathOf(form.action)));
    };
    const lock = element => {
        if (element.closest('form') && allowedAccountPath(pathOf(element.closest('form').action))) return;
        element.dataset.companyArchiveLocked = '1';
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('title', message);
        if ('disabled' in element) element.disabled = true;
        if (element.tagName === 'A') { element.classList.add('disabled'); element.setAttribute('tabindex', '-1'); }
    };
    const apply = root => {
        if (!(root instanceof Element) && root !== document) return;
        const nodes = selector => [...(root instanceof Element && root.matches(selector) ? [root] : []), ...root.querySelectorAll(selector)];
        nodes('form').forEach(form => { if (!allowedForm(form)) form.querySelectorAll('input, select, textarea, button').forEach(lock); });
        nodes('[contenteditable="true"]').forEach(cell => { cell.contentEditable = 'false'; cell.title = message; });
        nodes('a, button, input[type="submit"]').forEach(element => {
            if (element.closest('form') && allowedAccountPath(pathOf(element.closest('form').action))) return;
            const path = pathOf(element.getAttribute('href') || element.dataset.url || element.getAttribute('formaction') || '');
            if (allowedAccountPath(path)) return;
            const identity = `${element.id} ${element.className}`;
            const text = (element.textContent || element.value || '').trim().replace(/^\+\s*/, '');
            const namedMutation = /(?:^|[\s_-])(?:delete|destroy|edit|create|save|upload|finalize|reserve|add)(?:[a-z0-9]*)?(?:$|[\s_-])/i.test(identity);
            const labelledMutation = /^(?:add|create|save|update|edit|delete|remove|upload|finalize|approve|reject|activate|deactivate|receive|issue)(?:\s|$)/i.test(text);
            if (mutationPath(path) || namedMutation || labelledMutation) lock(element);
        });
    };
    document.addEventListener('submit', event => {
        if (!allowedForm(event.target)) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    document.addEventListener('click', event => {
        if (event.target.closest('[data-company-archive-locked="1"]')) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    apply(document);
    new MutationObserver(changes => changes.forEach(change => change.addedNodes.forEach(apply)))
        .observe(document.body, { childList: true, subtree: true });
})();
