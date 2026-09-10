@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card stock-gallery-page">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h4 class="card-title mb-0">Stock Gallery</h4>
            <div class="stock-gallery-count">
                Selected: <strong id="selectedCount">0</strong>
            </div>
        </div>

        <div class="card-body">
            <div class="gallery-filter-panel">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label>Item</label>
                        <select id="itemFilter" class="form-select stock-gallery-search-select">
                            <option value="">All Items</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Stock Status</label>
                        <select id="stockStatusFilter" class="form-select stock-gallery-search-select">
                            <option value="">All Stock</option>
                            <option value="in" selected>In Stock</option>
                            <option value="out">Out Stock</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Image Status</label>
                        <select id="imageStatusFilter" class="form-select stock-gallery-search-select">
                            <option value="">All Images</option>
                            <option value="with">With Image</option>
                            <option value="without">Without Image</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Search</label>
                        <input type="text" id="searchFilter" class="form-control" placeholder="Item, label, QR, HUID">
                    </div>

                    <div class="col-md-5 d-flex gap-2 flex-wrap">
                        <button type="button" id="applyFilter" class="btn btn-primary">Apply Filter</button>
                        <button type="button" id="resetFilter" class="btn btn-secondary">Reset</button>
                        <button type="button" id="selectVisible" class="btn btn-info">Select Visible</button>
                        <button type="button" id="clearSelected" class="btn btn-clear-gallery">Clear</button>
                    </div>
                </div>
            </div>

            <div class="stock-gallery-summary" id="gallerySummary">
                <div class="summary-box">
                    <span>Total Items</span>
                    <strong data-summary="total_items">0</strong>
                </div>
                <div class="summary-box">
                    <span>In Stock</span>
                    <strong data-summary="in_stock_items">0</strong>
                </div>
                <div class="summary-box">
                    <span>Out Stock</span>
                    <strong data-summary="out_stock_items">0</strong>
                </div>
                <div class="summary-box">
                    <span>With Image</span>
                    <strong data-summary="with_image_items">0</strong>
                </div>
                <div class="summary-box">
                    <span>Without Image</span>
                    <strong data-summary="without_image_items">0</strong>
                </div>
                <div class="summary-box">
                    <span>Gross Wt</span>
                    <strong data-summary="total_gross_weight">0.000</strong>
                </div>
                <div class="summary-box">
                    <span>Other Wt</span>
                    <strong data-summary="total_other_weight">0.000</strong>
                </div>
                <div class="summary-box">
                    <span>Net Wt</span>
                    <strong data-summary="total_net_weight">0.000</strong>
                </div>
            </div>

            <div class="gallery-actions">
                <button type="button" id="pdfSelected" class="btn btn-danger">PDF Selected</button>
                <button type="button" id="pdfFiltered" class="btn btn-danger">PDF Filtered</button>
                <button type="button" id="downloadSelected" class="btn btn-success">Download Selected Images</button>
                <button type="button" id="downloadFiltered" class="btn btn-success">Download Filtered Images</button>
                <button type="button" id="shareWhatsapp" class="btn btn-warning">WhatsApp Share</button>
            </div>

            <div id="galleryStatus" class="gallery-status">Loading stock gallery...</div>
            <div id="galleryGrid" class="stock-gallery-grid"></div>

            <div class="text-center mt-4">
                <button type="button" id="loadMore" class="btn btn-primary d-none">Load More</button>
            </div>
        </div>
    </div>
</div>

<style>
    .stock-gallery-page .card-header { border-bottom: 1px solid #3b3d5b; }
    .stock-gallery-count { color: #d5d7e4; font-size: 14px; }
    .gallery-filter-panel {
        border: 1px solid #3a3d5a;
        padding: 18px;
        margin-bottom: 18px;
        background: #262840;
    }
    .gallery-filter-panel label {
        color: #d5d7e4;
        font-size: 14px;
        margin-bottom: 6px;
    }
    .gallery-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        margin-bottom: 18px;
    }
    .stock-gallery-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .summary-box {
        border: 1px solid #3a3d5a;
        border-radius: 6px;
        background: #252740;
        padding: 12px 14px;
        min-height: 70px;
    }
    .summary-box span {
        display: block;
        color: #aeb3d0;
        font-size: 12px;
        margin-bottom: 6px;
    }
    .summary-box strong {
        color: #fff;
        font-size: 16px;
    }
    .btn-clear-gallery {
        background: #6c757d;
        border-color: #6c757d;
        color: #fff;
    }
    .btn-clear-gallery:hover,
    .btn-clear-gallery:focus {
        background: #5c636a;
        border-color: #565e64;
        color: #fff;
    }
    .gallery-status { color: #d5d7e4; margin-bottom: 12px; }
    .stock-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 16px;
    }
    .stock-gallery-card {
        border: 1px solid #3a3d5a;
        border-radius: 8px;
        background: #2c2f48;
        overflow: hidden;
        min-height: 430px;
        display: flex;
        flex-direction: column;
    }
    .gallery-image-wrap {
        width: 100%;
        height: 260px;
        flex: 0 0 260px;
        background: #202238;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid #3a3d5a;
        overflow: hidden;
    }
    .gallery-image-wrap img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .gallery-placeholder {
        color: #9da1bd;
        font-weight: 600;
        text-align: center;
        padding: 0 12px;
    }
    .gallery-placeholder span {
        display: block;
        color: #d5d7e4;
        font-size: 12px;
        font-weight: 500;
        margin-top: 4px;
    }
    .gallery-card-body {
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 9px;
        flex: 1;
    }
    .gallery-title {
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        line-height: 1.3;
        min-height: 40px;
    }
    .gallery-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        color: #d5d7e4;
        font-size: 12px;
    }
    .gallery-meta span {
        display: block;
        color: #9da1bd;
        margin-bottom: 2px;
    }
    .gallery-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: auto;
    }
    .stock-badge {
        border-radius: 4px;
        padding: 5px 8px;
        font-size: 12px;
        font-weight: 700;
    }
    .stock-badge.in { background: #06c76d; color: #06150f; }
    .stock-badge.out { background: #ff2f2f; color: #fff; }
    .gallery-check { width: 18px; height: 18px; }
    .gallery-check:disabled { opacity: .5; cursor: not-allowed; }
    @media (max-width: 575px) {
        .gallery-actions { justify-content: stretch; }
        .gallery-actions .btn,
        .gallery-filter-panel .btn { width: 100%; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json(route('company.stock-gallery.data', $company->slug));
    const pdfUrl = @json(route('company.stock-gallery.pdf', $company->slug));
    const downloadListUrl = @json(route('company.stock-gallery.download-list', $company->slug));
    const grid = document.getElementById('galleryGrid');
    const status = document.getElementById('galleryStatus');
    const loadMoreBtn = document.getElementById('loadMore');
    const selectedCount = document.getElementById('selectedCount');
    const selectedIds = new Set();
    const visibleItems = new Map();
    let page = 1;
    let lastPage = 1;
    let loading = false;

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.stock-gallery-search-select').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownParent: jQuery('.stock-gallery-page')
        });
    }

    function filters(extra = {}) {
        const params = new URLSearchParams({
            item_id: document.getElementById('itemFilter').value,
            stock_status: document.getElementById('stockStatusFilter').value,
            image_status: document.getElementById('imageStatusFilter').value,
            search: document.getElementById('searchFilter').value.trim(),
            page: page,
            per_page: 24,
            ...extra
        });

        Array.from(params.keys()).forEach(function (key) {
            if (params.get(key) === '') {
                params.delete(key);
            }
        });

        return params;
    }

    function updateSelectedCount() {
        selectedCount.textContent = selectedIds.size;
    }

    function updateSummary(summary = {}) {
        document.querySelectorAll('#gallerySummary [data-summary]').forEach(function (element) {
            const key = element.getAttribute('data-summary');
            element.textContent = summary[key] || (key.includes('weight') ? '0.000' : '0');
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function imagePlaceholderHtml() {
        return '<div class="gallery-placeholder">Image not uploaded<span>Upload image from Label Items</span></div>';
    }

    function cardHtml(item) {
        const checked = selectedIds.has(String(item.id)) ? 'checked' : '';
        const badgeClass = item.is_sold === 1 ? 'out' : 'in';
        const placeholder = imagePlaceholderHtml();
        const image = item.image_url
            ? `<img class="gallery-item-image" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.label_code)}">`
            : placeholder;
        const otherWeight = item.has_other_weight
            ? `<div><span>Other Wt</span>${escapeHtml(item.other_weight)}</div>`
            : '';
        const canSelect = item.has_image ? '' : 'disabled';
        const selectTitle = item.has_image ? '' : 'title="Image not uploaded"';

        return `
            <div class="stock-gallery-card" data-id="${item.id}">
                <div class="gallery-image-wrap">${image}</div>
                <div class="gallery-card-body">
                    <div class="gallery-title">${escapeHtml(item.item_name)}</div>
                    <div class="gallery-meta">
                        <div><span>Label / QR</span>${escapeHtml(item.label_code)}</div>
                        <div><span>HUID</span>${escapeHtml(item.huid)}</div>
                        <div><span>Gross Wt</span>${escapeHtml(item.gross_weight)}</div>
                        ${otherWeight}
                        <div><span>Net Wt</span>${escapeHtml(item.net_weight)}</div>
                    </div>
                    <div class="gallery-footer">
                        <span class="stock-badge ${badgeClass}">${escapeHtml(item.stock_status)}</span>
                        <label class="d-flex align-items-center gap-2 mb-0">
                            <input type="checkbox" class="gallery-check" value="${item.id}" ${checked} ${canSelect} ${selectTitle}>
                            Select
                        </label>
                    </div>
                </div>
            </div>
        `;
    }

    grid.addEventListener('error', function (event) {
        if (!event.target.classList.contains('gallery-item-image')) return;

        const imageWrap = event.target.closest('.gallery-image-wrap');
        if (imageWrap) {
            imageWrap.innerHTML = imagePlaceholderHtml();
        }
    }, true);

    async function loadGallery(reset = false) {
        if (loading) return;
        loading = true;

        if (reset) {
            page = 1;
            grid.innerHTML = '';
            visibleItems.clear();
        }

        status.textContent = 'Loading stock gallery...';
        loadMoreBtn.classList.add('d-none');

        try {
            const response = await fetch(dataUrl + '?' + filters().toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            lastPage = result.last_page || 1;
            (result.data || []).forEach(function (item) {
                visibleItems.set(String(item.id), item);
                grid.insertAdjacentHTML('beforeend', cardHtml(item));
            });

            status.textContent = result.total ? `${result.total} item(s) found` : 'No stock gallery items found';
            updateSummary(result.summary || {});
            loadMoreBtn.classList.toggle('d-none', page >= lastPage);
        } catch (error) {
            status.textContent = 'Unable to load stock gallery.';
            updateSummary();
        } finally {
            loading = false;
        }
    }

    function selectedQuery(scope) {
        const params = filters({ scope: scope });
        params.delete('page');
        params.delete('per_page');

        if (scope === 'selected') {
            params.set('selected_ids', Array.from(selectedIds).join(','));
        }

        return params;
    }

    async function downloadImages(scope) {
        if (scope === 'selected' && selectedIds.size === 0) {
            alert('Please select at least one item.');
            return;
        }

        const response = await fetch(downloadListUrl + '?' + selectedQuery(scope).toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (!result.count) {
            alert('Items are available in stock, but image is not uploaded for the selected items.');
            return;
        }

        result.items.forEach(function (item, index) {
            window.setTimeout(function () {
                const link = document.createElement('a');
                link.href = item.download_url;
                link.target = '_blank';
                link.download = '';
                document.body.appendChild(link);
                link.click();
                link.remove();
            }, index * 450);
        });
    }

    grid.addEventListener('change', function (event) {
        if (!event.target.classList.contains('gallery-check')) return;

        if (event.target.checked) {
            selectedIds.add(event.target.value);
        } else {
            selectedIds.delete(event.target.value);
        }
        updateSelectedCount();
    });

    document.getElementById('applyFilter').addEventListener('click', function () { loadGallery(true); });
    document.getElementById('resetFilter').addEventListener('click', function () {
        document.getElementById('itemFilter').value = '';
        document.getElementById('stockStatusFilter').value = 'in';
        document.getElementById('imageStatusFilter').value = '';
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#itemFilter').val('').trigger('change');
            jQuery('#stockStatusFilter').val('in').trigger('change');
            jQuery('#imageStatusFilter').val('').trigger('change');
        }
        document.getElementById('searchFilter').value = '';
        selectedIds.clear();
        updateSelectedCount();
        loadGallery(true);
    });
    document.getElementById('selectVisible').addEventListener('click', function () {
        visibleItems.forEach(function (item, id) {
            if (item.has_image) {
                selectedIds.add(id);
            }
        });
        document.querySelectorAll('.gallery-check:not(:disabled)').forEach(function (input) { input.checked = true; });
        updateSelectedCount();
    });
    document.getElementById('clearSelected').addEventListener('click', function () {
        document.getElementById('searchFilter').value = '';
        selectedIds.clear();
        document.querySelectorAll('.gallery-check').forEach(function (input) { input.checked = false; });
        updateSelectedCount();
        loadGallery(true);
    });
    document.getElementById('pdfSelected').addEventListener('click', function () {
        if (selectedIds.size === 0) {
            alert('Please select at least one item.');
            return;
        }
        window.open(pdfUrl + '?' + selectedQuery('selected').toString(), '_blank');
    });
    document.getElementById('pdfFiltered').addEventListener('click', function () {
        window.open(pdfUrl + '?' + selectedQuery('filtered').toString(), '_blank');
    });
    document.getElementById('downloadSelected').addEventListener('click', function () { downloadImages('selected'); });
    document.getElementById('downloadFiltered').addEventListener('click', function () { downloadImages('filtered'); });
    document.getElementById('shareWhatsapp').addEventListener('click', function () {
        const selected = Array.from(selectedIds).map(function (id) {
            return visibleItems.get(id);
        }).filter(Boolean);
        const labels = selected.length
            ? selected.map(function (item) { return `${item.label_code} - ${item.item_name}`; }).join('\n')
            : 'Stock gallery filtered list';
        const text = `Stock Gallery\n${labels}\n\nOpen ERP and export/download the gallery from this page.`;
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
    });
    loadMoreBtn.addEventListener('click', function () {
        if (page < lastPage) {
            page += 1;
            loadGallery(false);
        }
    });
    document.getElementById('searchFilter').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadGallery(true);
        }
    });

    loadGallery(true);
});
</script>
@endsection
