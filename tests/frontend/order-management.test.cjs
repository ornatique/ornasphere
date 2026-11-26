const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Minimal DOM adapter: exercises the actual shipped script without a browser dependency.
class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.dataset = {}; this.value = ''; this.hidden = false; this.style = {}; this.attributes = {}; }
    append(...nodes) { nodes.forEach(node => { node.parent = this; this.children.push(node); }); }
    replaceChildren(...nodes) { this.children = []; this.append(...nodes); }
    setAttribute(key, value) { this.attributes[key] = value; }
    removeAttribute(key) { delete this.attributes[key]; if (key === 'src') delete this.src; }
    showModal() { this.open = true; }
    close() { this.open = false; this.dispatch('close'); }
    addEventListener(key, callback) { (this.events[key] ??= []).push(callback); }
    dispatch(key, extra = {}) { for (const callback of this.events[key] || []) callback({target:this, preventDefault() {}, ...extra}); }
    focus() {}
    querySelectorAll(selector) { const nodes = this.children.flatMap(child => [child, ...child.querySelectorAll(selector)]); return nodes.filter(node => selector === 'button' ? node.tag === 'button' : selector === 'tr[data-item-search]' && node.tag === 'tr' && node.dataset.itemSearch !== undefined); }
    querySelector(selector) { return this.querySelectorAll(selector)[0]; }
}
const item = {item_set_id:1, item_name:'Mangalsutra', qr_code:'MS12', huid:'ABC123', gross_weight:50, net_weight:49, available_quantity:3};
function setup(rows = [], priceMode = null) {
    const elements = new Map();
    const applyPurity = new Element('button'); applyPurity.dataset.orderApply = 'purity';
    const document = {getElementById(id) { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); }, createElement:tag => new Element(tag), createTextNode:() => new Element('text'), addEventListener() {}, querySelectorAll:() => [applyPurity]};
    const requests = []; let responseItems = [item];
    const context = {document, URL, AbortController, setTimeout, clearTimeout, window:{location:{origin:'http://localhost'}, addEventListener() {}}, fetch:async url => { requests.push(url); return {ok:true, json:async () => responseItems}; }};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/order-management.js'), 'utf8'), context);
    context.window.OrderManagement.init({rows, priceMode, searchUrl:'/items/create'});
    return {el:id => document.getElementById(id), requests, applyPurity, respond:items => {responseItems = items;}};
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('selecting adds a row, clears and hides suggestions, and excludes it on subsequent search', async () => {
    const ui = setup();
    ui.el('item-filter').dispatch('change'); await flush();
    assert.equal(ui.el('item-results').hidden, false);
    ui.el('item-results').children[0].dispatch('click');
    assert.equal(ui.el('item-results').hidden, true);
    assert.equal(ui.el('item-scan').value, '');
    assert.equal(ui.el('selected-items').children.length, 1);
    assert.equal(ui.el('order-total-gross').textContent, '50.000');
    ui.el('item-filter').dispatch('change'); await flush();
    assert.deepEqual(ui.requests.at(-1).searchParams.getAll('exclude[]'), ['1']);
    // Even a response that still contains the selected item is filtered locally.
    assert.equal(ui.el('item-results').children.length, 0);
    assert.equal(ui.el('item-results').hidden, true);
});

test('removing a selected row makes it searchable again', async () => {
    const ui = setup([{...item, quantity:1}]);
    ui.el('selected-items').querySelector('button').dispatch('click');
    ui.el('item-filter').dispatch('change'); await flush();
    assert.deepEqual(ui.requests.at(-1).searchParams.getAll('exclude[]'), []);
    assert.equal(ui.el('item-results').children.length, 1);
});

test('saved edit rows are excluded and the lower search filters by item name or label', async () => {
    const ui = setup([{...item, quantity:2}]);
    ui.el('item-filter').dispatch('change'); await flush();
    assert.equal(ui.el('item-results').hidden, true);
    ui.el('selected-item-search').value = 'ring'; ui.el('selected-item-search').dispatch('input');
    assert.equal(ui.el('selected-items').children[0].hidden, true);
    ui.el('selected-item-search').value = 'ms12'; ui.el('selected-item-search').dispatch('input');
    assert.equal(ui.el('selected-items').children[0].hidden, false);
    assert.equal(ui.el('order-total-count').textContent, 2);
});

test('approval style inputs recalculate amounts and preserve values when another row is added', async () => {
    const ui = setup([{...item, quantity:2, purity:94, waste_percent:2, metal_rate:100, labour_rate:10, other_amount:25}]);
    assert.equal(ui.el('order-total-amount').textContent, '10830.00');
    const row = ui.el('selected-items').children[0];
    const rate = row.children[9].children[0];
    rate.value = '200'; rate.dispatch('input');
    assert.equal(ui.el('order-total-amount').textContent, '20630.00');
    const gross = row.children[2].children[0];
    gross.value = '60'; gross.dispatch('input');
    assert.equal(row.children[4].children[0].value, '59.000');
    assert.equal(row.children[7].children[0].value, '96.000');
    assert.equal(row.children[8].children[0].value, '56.640');
});

test('All copies first-row purity and recalculates the other rows', () => {
    const ui = setup([{...item, quantity:1, purity:94}, {...item, item_set_id:3, quantity:1, purity:80}]);
    ui.applyPurity.dispatch('click');
    assert.equal(ui.el('selected-items').children[1].children[5].children[0].value, '94.000');
    assert.equal(ui.el('selected-items').children[1].children[8].children[0].value, '46.060');
});

test('selected rows show a read-only wholesale price and image in the final column', () => {
    const ui = setup([{...item, quantity:1, wholesale_price:1234.50, image_url:'http://localhost/storage/ring.png'}]);
    const row = ui.el('selected-items').children[0];
    assert.equal(row.children.length,20);
    assert.equal(row.children[15].children[0].value,'1234.50');
    assert.equal(row.children[15].children[0].readOnly,true);
    assert.equal(row.children.at(-1).children[0].src,'http://localhost/storage/ring.png');
    row.children.at(-1).children[0].dispatch('click');
    assert.equal(ui.el('order-image-preview').open,true);
    assert.equal(ui.el('order-preview-image').src,'http://localhost/storage/ring.png');
    assert.equal(ui.el('order-preview-title').textContent,'Mangalsutra');
    ui.el('order-preview-close').dispatch('click');
    assert.equal(ui.el('order-image-preview').open,false);
    assert.equal(ui.el('order-preview-image').src,undefined);
    row.children.at(-1).children[0].dispatch('error');
    assert.equal(row.children.at(-1).textContent,'No image');
});

test('wholesale column appears only for priced labels and disappears when the last priced row is removed', () => {
    const absent = setup([{...item, quantity:1}]);
    assert.equal(absent.el('order-wholesale-heading').hidden,true);
    assert.equal(absent.el('selected-items').children[0].children.length,19);
    const zero = setup([{...item, quantity:1, wholesale_price:0}]);
    assert.equal(zero.el('order-wholesale-heading').hidden,true);
    const mixed = setup([{...item, quantity:1}, {...item, item_set_id:3, quantity:1, wholesale_price:100}]);
    assert.equal(mixed.el('order-wholesale-heading').hidden,false);
    assert.equal(mixed.el('selected-items').children[0].children[15].children[0].value,'—');
    mixed.el('selected-items').children[1].querySelector('button').dispatch('click');
    assert.equal(mixed.el('order-wholesale-heading').hidden,true);
    assert.equal(mixed.el('selected-items').children[0].children.length,19);
});

test('sale price stays 1200 while wholesale and the effective billing price stay 900', () => {
    const ui = setup([{...item, quantity:2, sale_price:1200, wholesale_price:900, billing_sale_price:900}]);
    const row = ui.el('selected-items').children[0];
    assert.equal(row.children[14].children[0].value,'1200.00');
    assert.equal(row.children[15].children[0].value,'900.00');
    assert.equal(ui.el('order-total-amount').textContent,'1800.00');
});

test('sale and wholesale radio buttons switch the total and displayed price column', () => {
    const ui = setup([{...item, quantity:1, sale_price:1200, wholesale_price:900, sale_unit_price:1200, wholesale_unit_price:900, billing_sale_price:1200}], 'sale');
    assert.equal(ui.el('order-total-amount').textContent,'1200.00');
    assert.equal(ui.el('order-wholesale-heading').hidden,true);
    ui.el('order-price-wholesale').value = 'wholesale'; ui.el('order-price-wholesale').checked = true;
    ui.el('order-price-wholesale').dispatch('change');
    assert.equal(ui.el('order-total-amount').textContent,'900.00');
    assert.equal(ui.el('order-sale-heading').hidden,true);
    assert.equal(ui.el('order-wholesale-heading').hidden,false);
    ui.el('order-price-sale').value = 'sale'; ui.el('order-price-sale').checked = true;
    ui.el('order-price-sale').dispatch('change');
    assert.equal(ui.el('order-total-amount').textContent,'1200.00');
    assert.equal(ui.el('order-sale-heading').hidden,false);
});

test('quantity can increase beyond available stock and updates totals', () => {
    const ui = setup([{...item, available_quantity:1, quantity:1, sale_price:900, billing_sale_price:900}]);
    const input = ui.el('selected-items').children[0].children[1].children[1];
    assert.equal(input.max,'1000000');
    input.value = '50'; input.dispatch('input');
    assert.equal(ui.el('order-total-count').textContent,50);
    assert.equal(ui.el('order-total-amount').textContent,'45000.00');
});

test('shared Google autocomplete targets order fields and fills city, address and pincode', () => {
    const inputs = Object.fromEntries(['customer-address','customer-city','customer-pincode'].map(id => [id, new Element('input')]));
    const instances = [];
    class Autocomplete {
        constructor(input, options) { this.input = input; this.options = options; this.listeners = {}; instances.push(this); }
        addListener(event, callback) { this.listeners[event] = callback; }
        getPlace() { return this.place; }
    }
    const google = {maps:{places:{Autocomplete}, Geocoder:class {geocode() {}}}};
    let script = fs.readFileSync(path.join(__dirname, '../../resources/views/company/customers/partials/address_autofill.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
    script = script.replace("@json($addressFieldId ?? 'address')", '"customer-address"').replace("@json($cityFieldId ?? 'city')", '"customer-city"').replace("@json($pincodeFieldId ?? 'pincode')", '"customer-pincode"');
    const context = {document:{getElementById:id => inputs[id] || null}, google, window:{google}};
    vm.runInNewContext(script, context);
    context.window.initCustomerAddressAutocomplete();
    assert.equal(instances.length, 2);
    assert.equal(instances[1].options.types[0], '(cities)');
    instances[1].place = {name:'Ahmedabad', address_components:[{types:['locality'],long_name:'Ahmedabad'}]};
    instances[1].listeners.place_changed();
    assert.equal(inputs['customer-city'].value, 'Ahmedabad');
    instances[0].place = {formatted_address:'Test Road, Surat', address_components:[{types:['locality'],long_name:'Surat'}, {types:['postal_code'],long_name:'395001'}]};
    instances[0].listeners.place_changed();
    assert.equal(inputs['customer-address'].value, 'Test Road, Surat');
    assert.equal(inputs['customer-city'].value, 'Surat');
    assert.equal(inputs['customer-pincode'].value, '395001');
});
