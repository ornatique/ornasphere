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
                        <select id="itemFilter" class="form-select stock-gallery-search-select" multiple>
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

    <button type="button" id="stockGalleryGoTop" class="stock-gallery-go-top" title="Go to top" aria-label="Go to top">
        <i class="mdi mdi-arrow-up"></i>
    </button>
</div>

<div class="modal fade" id="galleryImageModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">Item Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="gallery_image_item_id">
                <input type="hidden" id="gallery_image_encrypted_id">
                <input type="hidden" id="gallery_image_current_url">

                <div class="row">
                    <div class="col-md-7 mb-3">
                        <div class="gallery-image-preview-wrap">
                            <img id="gallery_item_image_preview" class="gallery-item-image-preview" alt="Item image preview">
                            <div id="gallery_image_crop_box" class="gallery-image-crop-box">
                                <span class="gallery-image-crop-handle gallery-image-crop-handle-nw" data-handle="nw"></span>
                                <span class="gallery-image-crop-handle gallery-image-crop-handle-ne" data-handle="ne"></span>
                                <span class="gallery-image-crop-handle gallery-image-crop-handle-sw" data-handle="sw"></span>
                                <span class="gallery-image-crop-handle gallery-image-crop-handle-se" data-handle="se"></span>
                            </div>
                            <div id="gallery_item_image_empty" class="gallery-item-image-empty">No image uploaded</div>
                        </div>
                    </div>

                    <div class="col-md-5 mb-3">
                        <div class="mb-3">
                            <label>Item</label>
                            <input type="text" id="gallery_image_item_name" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Label Code</label>
                            <input type="text" id="gallery_image_label_code" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Upload Image</label>
                            <input type="file" id="gallery_item_image_file" class="form-control text-white border-0" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted d-block mt-1">JPG, PNG or WebP up to 15 MB. Stored as compressed WebP.</small>
                        </div>

                        <div class="gallery-image-edit-tools border rounded p-2 mb-3" id="gallery_image_edit_tools">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-warning" id="galleryCropBtn" type="button"><i class="mdi mdi-crop"></i> Crop</button>
                                <button class="btn btn-sm btn-info" id="galleryRotateLeftBtn" type="button"><i class="mdi mdi-rotate-left"></i> Rotate Left</button>
                                <button class="btn btn-sm btn-info" id="galleryRotateRightBtn" type="button"><i class="mdi mdi-rotate-right"></i> Rotate Right</button>
                                <button class="btn btn-sm btn-secondary" id="galleryResetCropBtn" type="button"><i class="mdi mdi-crop-free"></i> Reset Crop</button>
                            </div>
                            <small class="text-muted d-block mt-2">Click Crop, then drag or resize the box on the image. Upload saves the selected area.</small>
                        </div>

                        <div class="mb-3">
                            <label>Uploaded</label>
                            <input type="text" id="gallery_image_uploaded_at" class="form-control text-white border-0" readonly>
                        </div>
                    </div>
                </div>

                <div id="gallery_image_upload_error" class="alert alert-danger d-none mb-0"></div>
                <div id="gallery_image_upload_success" class="alert alert-success d-none mb-0"></div>
            </div>

            <div class="modal-footer border-top">
                <button class="btn btn-danger me-auto" id="galleryRemoveImageBtn" type="button">Remove Image</button>
                <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="galleryUploadImageBtn" type="button">Upload Image</button>
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
    .stock-gallery-page .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 46px;
        height: 46px;
        background: #302c55;
        border-color: #4b4580;
        overflow: hidden;
    }
    .stock-gallery-page #itemFilter + .select2-container .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        align-items: center;
        height: 44px;
        padding: 6px 12px;
        overflow: hidden;
    }
    .stock-gallery-page #itemFilter + .select2-container .select2-selection--multiple .select2-selection__choice {
        display: none;
    }
    .stock-gallery-page #itemFilter + .select2-container .select2-search--inline {
        width: 100%;
    }
    .stock-gallery-page #itemFilter + .select2-container .select2-search--inline .select2-search__field {
        width: 100% !important;
        margin-top: 0;
        height: 30px;
        padding: 5px 8px;
        color: #fff;
    }
    .stock-gallery-page .select2-dropdown .select2-search--dropdown .select2-search__field,
    .select2-container--bootstrap4 .select2-dropdown .select2-search--dropdown .select2-search__field,
    .select2-container .select2-search--dropdown .select2-search__field,
    input.select2-search__field {
        color: #111 !important;
        -webkit-text-fill-color: #111 !important;
        caret-color: #111 !important;
        background: #fff !important;
        opacity: 1 !important;
        font-size: 14px !important;
        line-height: 20px !important;
        text-shadow: none !important;
        visibility: visible !important;
    }
    .stock-gallery-page .select2-results__option--selected,
    .stock-gallery-page .select2-results__option[aria-selected=true] {
        background: #1c75ff !important;
        color: #fff !important;
    }
    .stock-gallery-item-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
    }
    .stock-gallery-item-option span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .stock-gallery-item-option input {
        width: 16px;
        height: 16px;
        accent-color: #1c75ff;
        flex: 0 0 auto;
        pointer-events: none;
    }
    .stock-gallery-item-option.is-selected span {
        color: #fff;
        font-weight: 700;
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
        cursor: pointer;
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
    .gallery-card-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 4px;
    }
    .gallery-image-btn {
        padding: 5px 8px;
        font-size: 12px;
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
    .stock-gallery-go-top {
        position: fixed;
        right: 28px;
        bottom: 34px;
        z-index: 1050;
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 50%;
        background: #ff2b72;
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35);
        cursor: pointer;
    }
    .stock-gallery-go-top:hover,
    .stock-gallery-go-top:focus {
        background: #e91e63;
        color: #fff;
        outline: none;
    }
    .stock-gallery-go-top.is-visible {
        display: flex;
    }
    .stock-gallery-go-top i {
        font-size: 22px;
        line-height: 1;
    }
    .gallery-image-preview-wrap {
        position: relative;
        height: min(56vh, 460px);
        min-height: 320px;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.04);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .gallery-item-image-preview {
        display: none;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        object-position: center;
    }
    .gallery-item-image-empty {
        color: rgba(255, 255, 255, 0.62);
    }
    .gallery-image-crop-box {
        display: none;
        position: absolute;
        z-index: 4;
        border: 2px solid #2f80ff;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);
        cursor: move;
        touch-action: none;
    }
    .gallery-image-crop-box::before,
    .gallery-image-crop-box::after {
        content: '';
        position: absolute;
        inset: 33.333% 0 auto 0;
        border-top: 1px dashed rgba(255, 255, 255, 0.75);
    }
    .gallery-image-crop-box::after {
        inset: 66.666% 0 auto 0;
    }
    .gallery-image-crop-handle {
        position: absolute;
        width: 12px;
        height: 12px;
        background: #2f80ff;
        border: 2px solid #ffffff;
        border-radius: 50%;
        touch-action: none;
    }
    .gallery-image-crop-handle-nw {
        left: -7px;
        top: -7px;
        cursor: nwse-resize;
    }
    .gallery-image-crop-handle-ne {
        right: -7px;
        top: -7px;
        cursor: nesw-resize;
    }
    .gallery-image-crop-handle-sw {
        left: -7px;
        bottom: -7px;
        cursor: nesw-resize;
    }
    .gallery-image-crop-handle-se {
        right: -7px;
        bottom: -7px;
        cursor: nwse-resize;
    }
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
    const imageShowUrlTemplate = @json(route('company.stock-gallery.image.show', [$company->slug, '__ID__']));
    const imageUpdateUrlTemplate = @json(route('company.stock-gallery.image.update', [$company->slug, '__ID__']));
    const imageRemoveUrlTemplate = @json(route('company.stock-gallery.image.remove', [$company->slug, '__ID__']));
    const csrfToken = @json(csrf_token());
    const grid = document.getElementById('galleryGrid');
    const status = document.getElementById('galleryStatus');
    const loadMoreBtn = document.getElementById('loadMore');
    const goTopBtn = document.getElementById('stockGalleryGoTop');
    const selectedCount = document.getElementById('selectedCount');
    const selectedIds = new Set();
    const visibleItems = new Map();
    let page = 1;
    let lastPage = 1;
    let loading = false;

    if (window.jQuery && jQuery.fn.select2) {
        function itemOptionTemplate(option) {
            if (!option.id) {
                return option.text;
            }

            const selectedValues = jQuery('#itemFilter').val() || [];
            const checked = selectedValues.map(String).includes(String(option.id)) ? 'checked' : '';
            const selectedClass = checked ? ' is-selected' : '';

            return jQuery(`
                <div class="stock-gallery-item-option${selectedClass}">
                    <span></span>
                    <input type="checkbox" ${checked} tabindex="-1">
                </div>
            `).find('span').text(option.text).end();
        }

        jQuery('#itemFilter').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            placeholder: 'All Items',
            templateResult: itemOptionTemplate,
            templateSelection: function(option) {
                return option.text;
            },
            escapeMarkup: function(markup) {
                return markup;
            },
            dropdownParent: jQuery('.stock-gallery-page')
        });
        jQuery('#itemFilter').on('change', function () {
            const count = jQuery(this).val()?.length || 0;
            const placeholder = count > 0 ? `${count} item selected` : 'All Items';
            const selection = jQuery(this).next('.select2-container').find('.select2-search__field');
            selection.attr('placeholder', placeholder);
        });
        jQuery('#itemFilter').trigger('change');

        jQuery('#stockStatusFilter, #imageStatusFilter').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownParent: jQuery('.stock-gallery-page')
        });
    }

    function filters(extra = {}, targetPage = page) {
        const params = new URLSearchParams({
            stock_status: document.getElementById('stockStatusFilter').value,
            image_status: document.getElementById('imageStatusFilter').value,
            search: document.getElementById('searchFilter').value.trim(),
            page: targetPage,
            per_page: 24,
            ...extra
        });
        const itemIds = Array.from(document.getElementById('itemFilter').selectedOptions)
            .map(function (option) { return option.value; })
            .filter(Boolean);

        itemIds.forEach(function (itemId) {
            params.append('item_id[]', itemId);
        });

        Array.from(params.keys()).forEach(function (key) {
            if (params.get(key) === '') {
                params.delete(key);
            }
        });

        return params;
    }

    function currentLoadedPageCount() {
        return Math.max(1, page || 1);
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
        return '<div class="gallery-placeholder">Image not uploaded<span>Upload image here</span></div>';
    }

    function itemImageUrl(template, encryptedId) {
        return template.replace('__ID__', encodeURIComponent(encryptedId));
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
                    <div class="gallery-card-actions">
                        {{-- Media / Try-On buttons temporarily hidden; processing and saved settings are preserved. --}}
                        <button type="button" class="btn btn-sm btn-info gallery-image-btn"
                            data-id="${item.id}"
                            data-encrypted-id="${escapeHtml(item.encrypted_id)}">
                            ${item.has_image ? 'Update Image' : 'Upload Image'}
                        </button>
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

    function resetImageMessages() {
        jQuery('#gallery_image_upload_error').addClass('d-none').text('');
        jQuery('#gallery_image_upload_success').addClass('d-none').text('');
    }

    function setImagePreview(url) {
        if (url) {
            jQuery('#gallery_item_image_preview')
                .off('error.galleryPreview')
                .on('error.galleryPreview', function () {
                    setImagePreview('');
                })
                .attr('src', url)
                .show();
            jQuery('#gallery_item_image_empty').hide();
            jQuery('#galleryRemoveImageBtn').prop('disabled', false).show();
            return;
        }

        jQuery('#gallery_item_image_preview').off('error.galleryPreview').removeAttr('src').hide();
        jQuery('#gallery_item_image_empty').show();
        jQuery('#galleryRemoveImageBtn').prop('disabled', true).hide();
    }

    function createImageEditor(options) {
        const state = {
            image: null,
            rotation: 0,
            previewUrl: null,
            fileName: 'item-image.jpg',
            renderWidth: 0,
            renderHeight: 0,
            cropActive: false,
            drag: null
        };

        const $tools = jQuery(options.tools);
        const $wrap = jQuery(options.wrap);
        const $cropBox = jQuery(options.cropBox);
        const $crop = jQuery(options.crop);
        const $resetCrop = jQuery(options.resetCrop);
        const $rotateLeft = jQuery(options.rotateLeft);
        const $rotateRight = jQuery(options.rotateRight);

        function setEnabled(enabled) {
            $tools.find('button, input').prop('disabled', !enabled);
        }

        function revokePreviewUrl() {
            if (state.previewUrl) {
                URL.revokeObjectURL(state.previewUrl);
                state.previewUrl = null;
            }
        }

        function reset() {
            revokePreviewUrl();
            state.image = null;
            state.rotation = 0;
            state.renderWidth = 0;
            state.renderHeight = 0;
            state.cropActive = false;
            state.drag = null;
            $cropBox.hide().removeAttr('style');
            setEnabled(false);
        }

        function buildRotatedCanvas() {
            if (!state.image) {
                return null;
            }

            const img = state.image;
            const rotation = ((state.rotation % 360) + 360) % 360;
            const canvas = document.createElement('canvas');
            canvas.width = rotation === 90 || rotation === 270 ? img.naturalHeight : img.naturalWidth;
            canvas.height = rotation === 90 || rotation === 270 ? img.naturalWidth : img.naturalHeight;
            const ctx = canvas.getContext('2d');

            ctx.save();
            if (rotation === 90) {
                ctx.translate(canvas.width, 0);
                ctx.rotate(Math.PI / 2);
            } else if (rotation === 180) {
                ctx.translate(canvas.width, canvas.height);
                ctx.rotate(Math.PI);
            } else if (rotation === 270) {
                ctx.translate(0, canvas.height);
                ctx.rotate(3 * Math.PI / 2);
            }
            ctx.drawImage(img, 0, 0);
            ctx.restore();

            state.renderWidth = canvas.width;
            state.renderHeight = canvas.height;

            return canvas;
        }

        function getImageRect() {
            if (!state.renderWidth || !state.renderHeight) {
                return null;
            }

            const wrapWidth = $wrap.width();
            const wrapHeight = $wrap.height();
            const scale = Math.min(wrapWidth / state.renderWidth, wrapHeight / state.renderHeight);
            const width = state.renderWidth * scale;
            const height = state.renderHeight * scale;

            return {
                left: (wrapWidth - width) / 2,
                top: (wrapHeight - height) / 2,
                width,
                height
            };
        }

        function setCropBox(rect) {
            $cropBox.css({
                left: rect.left + 'px',
                top: rect.top + 'px',
                width: rect.width + 'px',
                height: rect.height + 'px',
                display: state.cropActive ? 'block' : 'none'
            });
        }

        function startCrop() {
            if (!state.image) {
                return;
            }

            const imageRect = getImageRect();
            if (!imageRect) {
                return;
            }

            state.cropActive = true;
            setCropBox({
                left: imageRect.left + imageRect.width * 0.1,
                top: imageRect.top + imageRect.height * 0.1,
                width: imageRect.width * 0.8,
                height: imageRect.height * 0.8
            });
        }

        function resetCrop() {
            state.cropActive = false;
            $cropBox.hide();
        }

        function renderPreview() {
            const canvas = buildRotatedCanvas();
            if (!canvas) {
                return Promise.resolve(null);
            }

            return new Promise(function(resolve) {
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        resolve(null);
                        return;
                    }

                    const file = new File([blob], state.fileName.replace(/\.[^.]+$/, '') + '-edited.jpg', { type: 'image/jpeg' });
                    revokePreviewUrl();
                    state.previewUrl = URL.createObjectURL(file);
                    setImagePreview(state.previewUrl);
                    resetCrop();
                    resolve(file);
                }, 'image/jpeg', 0.92);
            });
        }

        function renderToFile() {
            const rotatedCanvas = buildRotatedCanvas();
            if (!rotatedCanvas) {
                return Promise.resolve(null);
            }

            let outputCanvas = rotatedCanvas;
            if (state.cropActive && $cropBox.is(':visible')) {
                const imageRect = getImageRect();
                const box = {
                    left: parseFloat($cropBox.css('left')) || 0,
                    top: parseFloat($cropBox.css('top')) || 0,
                    width: $cropBox.outerWidth(),
                    height: $cropBox.outerHeight()
                };

                if (imageRect && box.width > 2 && box.height > 2) {
                    const scaleX = rotatedCanvas.width / imageRect.width;
                    const scaleY = rotatedCanvas.height / imageRect.height;
                    const sx = Math.max(0, Math.round((box.left - imageRect.left) * scaleX));
                    const sy = Math.max(0, Math.round((box.top - imageRect.top) * scaleY));
                    const sw = Math.min(rotatedCanvas.width - sx, Math.round(box.width * scaleX));
                    const sh = Math.min(rotatedCanvas.height - sy, Math.round(box.height * scaleY));

                    outputCanvas = document.createElement('canvas');
                    outputCanvas.width = Math.max(1, sw);
                    outputCanvas.height = Math.max(1, sh);
                    outputCanvas.getContext('2d').drawImage(rotatedCanvas, sx, sy, sw, sh, 0, 0, outputCanvas.width, outputCanvas.height);
                }
            }

            return new Promise(function(resolve) {
                outputCanvas.toBlob(function(blob) {
                    if (!blob) {
                        resolve(null);
                        return;
                    }

                    resolve(new File([blob], state.fileName.replace(/\.[^.]+$/, '') + '-edited.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.92);
            });
        }

        function load(file) {
            reset();
            state.fileName = file.name || 'item-image.jpg';
            const imageUrl = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function() {
                URL.revokeObjectURL(imageUrl);
                state.image = img;
                setEnabled(true);
                renderPreview();
            };
            img.onerror = function() {
                URL.revokeObjectURL(imageUrl);
                reset();
                jQuery('#gallery_image_upload_error').removeClass('d-none').text('Unable to read selected image.');
            };
            img.src = imageUrl;
        }

        async function loadUrl(url, fileName = 'item-image.jpg') {
            reset();

            if (!url) {
                setImagePreview('');
                return false;
            }

            try {
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error('Image request failed');
                }

                const blob = await response.blob();
                const type = blob.type && blob.type.match(/^image\/(jpeg|png|webp)$/)
                    ? blob.type
                    : 'image/jpeg';
                const file = new File([blob], fileName, { type });
                load(file);

                return true;
            } catch (error) {
                reset();
                setImagePreview('');
                return false;
            }
        }

        $rotateLeft.on('click', function() {
            state.rotation -= 90;
            renderPreview();
        });
        $rotateRight.on('click', function() {
            state.rotation += 90;
            renderPreview();
        });
        $crop.on('click', startCrop);
        $resetCrop.on('click', resetCrop);

        $cropBox.on('mousedown touchstart', function(event) {
            if (!state.cropActive) {
                return;
            }

            const point = event.type === 'touchstart' ? event.originalEvent.touches[0] : event;
            const position = $cropBox.position();
            state.drag = {
                handle: jQuery(event.target).data('handle') || 'move',
                startX: point.clientX,
                startY: point.clientY,
                left: position.left,
                top: position.top,
                width: $cropBox.outerWidth(),
                height: $cropBox.outerHeight()
            };
            event.preventDefault();
        });

        jQuery(document).on('mousemove.galleryImageEditor touchmove.galleryImageEditor', function(event) {
            if (!state.drag) {
                return;
            }

            const point = event.type === 'touchmove' ? event.originalEvent.touches[0] : event;
            const dx = point.clientX - state.drag.startX;
            const dy = point.clientY - state.drag.startY;
            const imageRect = getImageRect();
            const minSize = 40;
            let left = state.drag.left;
            let top = state.drag.top;
            let width = state.drag.width;
            let height = state.drag.height;

            if (!imageRect) {
                return;
            }

            if (state.drag.handle === 'move') {
                left += dx;
                top += dy;
            } else {
                if (state.drag.handle.includes('w')) {
                    left += dx;
                    width -= dx;
                }
                if (state.drag.handle.includes('e')) {
                    width += dx;
                }
                if (state.drag.handle.includes('n')) {
                    top += dy;
                    height -= dy;
                }
                if (state.drag.handle.includes('s')) {
                    height += dy;
                }
            }

            width = Math.max(minSize, width);
            height = Math.max(minSize, height);
            left = Math.max(imageRect.left, Math.min(left, imageRect.left + imageRect.width - width));
            top = Math.max(imageRect.top, Math.min(top, imageRect.top + imageRect.height - height));
            width = Math.min(width, imageRect.left + imageRect.width - left);
            height = Math.min(height, imageRect.top + imageRect.height - top);
            setCropBox({ left, top, width, height });
            event.preventDefault();
        });

        jQuery(document).on('mouseup.galleryImageEditor touchend.galleryImageEditor touchcancel.galleryImageEditor', function() {
            state.drag = null;
        });

        reset();

        return {
            load,
            loadUrl,
            reset,
            hasImage: function() {
                return Boolean(state.image);
            },
            getFile: renderToFile
        };
    }

    const galleryImageEditor = createImageEditor({
        tools: '#gallery_image_edit_tools',
        wrap: '.gallery-image-preview-wrap',
        cropBox: '#gallery_image_crop_box',
        crop: '#galleryCropBtn',
        resetCrop: '#galleryResetCropBtn',
        rotateLeft: '#galleryRotateLeftBtn',
        rotateRight: '#galleryRotateRightBtn'
    });

    function currentGalleryImageUrls() {
        const encryptedId = document.getElementById('gallery_image_encrypted_id').value;

        return {
            show: itemImageUrl(imageShowUrlTemplate, encryptedId),
            update: itemImageUrl(imageUpdateUrlTemplate, encryptedId),
            remove: itemImageUrl(imageRemoveUrlTemplate, encryptedId)
        };
    }

    function updateGalleryCardImage(itemId, imageUrl) {
        const id = String(itemId);
        const item = visibleItems.get(id);
        if (item) {
            item.image_url = imageUrl || null;
            item.has_image = Boolean(imageUrl);
        }

        const card = grid.querySelector(`.stock-gallery-card[data-id="${CSS.escape(id)}"]`);
        if (!card) {
            return;
        }

        const wrap = card.querySelector('.gallery-image-wrap');
        const button = card.querySelector('.gallery-image-btn');
        const checkbox = card.querySelector('.gallery-check');

        if (wrap) {
            wrap.innerHTML = imageUrl
                ? `<img class="gallery-item-image" src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item?.label_code || '')}">`
                : imagePlaceholderHtml();
        }
        if (button) {
            button.textContent = imageUrl ? 'Update Image' : 'Upload Image';
        }
        if (checkbox) {
            checkbox.disabled = !imageUrl;
            checkbox.title = imageUrl ? '' : 'Image not uploaded';
            if (!imageUrl) {
                checkbox.checked = false;
                selectedIds.delete(id);
                updateSelectedCount();
            }
        }
    }

    function setGalleryCardSelected(card, checked) {
        const checkbox = card ? card.querySelector('.gallery-check') : null;
        if (!checkbox || checkbox.disabled) {
            return;
        }

        checkbox.checked = checked;
        if (checked) {
            selectedIds.add(checkbox.value);
        } else {
            selectedIds.delete(checkbox.value);
        }
        updateSelectedCount();
    }

    function toggleGalleryCardSelected(card) {
        const checkbox = card ? card.querySelector('.gallery-check') : null;
        if (!checkbox || checkbox.disabled) {
            return;
        }

        setGalleryCardSelected(card, !checkbox.checked);
    }

    function loadGalleryItemImage(item) {
        if (!window.jQuery) {
            return;
        }

        resetImageMessages();
        jQuery('#gallery_item_image_file').val('');
        galleryImageEditor.reset();
        document.getElementById('gallery_image_item_id').value = item.id;
        document.getElementById('gallery_image_encrypted_id').value = item.encrypted_id;

        jQuery.get(itemImageUrl(imageShowUrlTemplate, item.encrypted_id), async function(data) {
            jQuery('#gallery_image_item_name').val(data.item_name || item.item_name || '-');
            jQuery('#gallery_image_label_code').val(data.label_code || item.label_code || '-');
            jQuery('#gallery_image_uploaded_at').val(data.image_uploaded_at || '-');
            jQuery('#gallery_image_current_url').val(data.image_url || '');
            await galleryImageEditor.loadUrl(item.download_url || data.image_url || '', `${data.label_code || item.label_code || 'item-image'}.jpg`);
            jQuery('#galleryImageModal').modal('show');
        }).fail(async function() {
            jQuery('#gallery_image_item_name').val(item.item_name || '-');
            jQuery('#gallery_image_label_code').val(item.label_code || '-');
            jQuery('#gallery_image_uploaded_at').val('-');
            jQuery('#gallery_image_current_url').val(item.image_url || '');
            await galleryImageEditor.loadUrl(item.download_url || item.image_url || '', `${item.label_code || 'item-image'}.jpg`);
            jQuery('#gallery_image_upload_error').removeClass('d-none').text('Unable to load item image details.');
            jQuery('#galleryImageModal').modal('show');
        });
    }

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

    async function refreshLoadedGallery() {
        if (loading) return;

        const loadedPages = currentLoadedPageCount();
        const scrollY = window.scrollY;
        loading = true;
        grid.innerHTML = '';
        visibleItems.clear();
        status.textContent = 'Refreshing stock gallery...';
        loadMoreBtn.classList.add('d-none');

        try {
            let total = 0;
            let summary = {};
            let refreshedLastPage = 1;

            for (let fetchPage = 1; fetchPage <= loadedPages; fetchPage += 1) {
                const response = await fetch(dataUrl + '?' + filters({}, fetchPage).toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();

                if (fetchPage === 1) {
                    total = result.total || 0;
                    summary = result.summary || {};
                    refreshedLastPage = result.last_page || 1;
                }

                (result.data || []).forEach(function (item) {
                    visibleItems.set(String(item.id), item);
                    grid.insertAdjacentHTML('beforeend', cardHtml(item));
                });

                if (fetchPage >= refreshedLastPage) {
                    break;
                }
            }

            page = Math.min(loadedPages, refreshedLastPage);
            lastPage = refreshedLastPage;
            status.textContent = total ? `${total} item(s) found` : 'No stock gallery items found';
            updateSummary(summary);
            loadMoreBtn.classList.toggle('d-none', page >= lastPage);
            window.scrollTo({ top: scrollY });
        } catch (error) {
            status.textContent = 'Unable to refresh stock gallery.';
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

    function shareTextForItems(items) {
        const labels = items.length
            ? items.map(function (item) { return `${item.label_code} - ${item.item_name || ''}`.trim(); }).join('\n')
            : 'Stock gallery filtered list';

        return `Stock Gallery\n${labels}\n\nOpen ERP and export/download the gallery from this page.`;
    }

    function extensionFromContentType(contentType) {
        if (contentType.includes('png')) return 'png';
        if (contentType.includes('webp')) return 'webp';
        if (contentType.includes('gif')) return 'gif';
        return 'jpg';
    }

    async function filesFromDownloadItems(items) {
        const limitedItems = items.slice(0, 20);
        const files = [];

        for (const item of limitedItems) {
            const response = await fetch(item.download_url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                continue;
            }

            const blob = await response.blob();
            const contentType = blob.type || response.headers.get('Content-Type') || 'image/jpeg';
            const extension = extensionFromContentType(contentType);
            const name = `${item.label_code || item.id || 'stock-gallery'}.${extension}`;

            files.push(new File([blob], name, { type: contentType }));
        }

        return files;
    }

    async function shareWhatsappWithImages() {
        if (selectedIds.size === 0) {
            alert('Please select at least one item with image.');
            return;
        }

        const response = await fetch(downloadListUrl + '?' + selectedQuery('selected').toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (!result.count) {
            alert('Items are available in stock, but image is not uploaded for the selected items.');
            return;
        }

        const selected = Array.from(selectedIds).map(function (id) {
            return visibleItems.get(id);
        }).filter(Boolean);
        const text = shareTextForItems(selected);

        try {
            const files = await filesFromDownloadItems(result.items || []);

            if (files.length && navigator.canShare && navigator.canShare({ files: files }) && navigator.share) {
                await navigator.share({
                    title: 'Stock Gallery',
                    text: text,
                    files: files
                });
                return;
            }
        } catch (error) {
            // Fall through to the WhatsApp Web text fallback below.
        }

        alert('Your browser cannot attach photos directly to WhatsApp. The selected images will download, then WhatsApp text will open.');
        (result.items || []).forEach(function (item, index) {
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
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
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

    let galleryImageClickTimer = null;

    grid.addEventListener('click', function (event) {
        const button = event.target.closest('.gallery-image-btn');
        if (button) {
            const item = visibleItems.get(String(button.dataset.id));
            if (!item) {
                alert('Unable to load this gallery item.');
                return;
            }

            loadGalleryItemImage(item);
            return;
        }

        const imageWrap = event.target.closest('.gallery-image-wrap');
        if (!imageWrap) return;

        const card = imageWrap.closest('.stock-gallery-card');
        if (!card || galleryImageClickTimer) return;

        galleryImageClickTimer = window.setTimeout(function () {
            galleryImageClickTimer = null;
            toggleGalleryCardSelected(card);
        }, 240);
    });

    grid.addEventListener('dblclick', function (event) {
        const imageWrap = event.target.closest('.gallery-image-wrap');
        if (!imageWrap) return;

        if (galleryImageClickTimer) {
            window.clearTimeout(galleryImageClickTimer);
            galleryImageClickTimer = null;
        }

        const card = imageWrap.closest('.stock-gallery-card');
        const item = card ? visibleItems.get(String(card.dataset.id)) : null;
        if (!item) {
            alert('Unable to load this gallery item.');
            return;
        }

        loadGalleryItemImage(item);
    });

    jQuery('#gallery_item_image_file').on('change', function() {
        resetImageMessages();

        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            const item = visibleItems.get(String(document.getElementById('gallery_image_item_id').value));
            if (item) {
                loadGalleryItemImage(item);
            }
            return;
        }

        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            jQuery(this).val('');
            jQuery('#gallery_image_upload_error').removeClass('d-none').text('Please select a JPG, PNG or WebP image.');
            return;
        }

        if (file.size > 15 * 1024 * 1024) {
            jQuery(this).val('');
            jQuery('#gallery_image_upload_error').removeClass('d-none').text('Image size must be 15 MB or less.');
            return;
        }

        galleryImageEditor.load(file);
    });

    function waitForProcessedGalleryImage(previousUrl, attempts = 60) {
        const deferred = jQuery.Deferred();
        const encryptedId = document.getElementById('gallery_image_encrypted_id').value;
        const showUrl = itemImageUrl(imageShowUrlTemplate, encryptedId);

        const poll = function(remaining) {
            jQuery.get(showUrl, function(data) {
                const nextUrl = data.image_url || '';
                if (nextUrl && nextUrl !== previousUrl) {
                    deferred.resolve(data);
                    return;
                }

                if (remaining <= 0) {
                    deferred.reject();
                    return;
                }

                setTimeout(function() {
                    poll(remaining - 1);
                }, 1000);
            }).fail(function() {
                if (remaining <= 0) {
                    deferred.reject();
                    return;
                }

                setTimeout(function() {
                    poll(remaining - 1);
                }, 1000);
            });
        };

        poll(attempts);
        return deferred.promise();
    }

    jQuery('#galleryUploadImageBtn').on('click', async function() {
        resetImageMessages();

        const fileInput = jQuery('#gallery_item_image_file')[0];
        if ((!fileInput.files || !fileInput.files[0]) && !galleryImageEditor.hasImage()) {
            jQuery('#gallery_image_upload_error').removeClass('d-none').text('Please choose an image or rotate the current image first.');
            return;
        }

        const formData = new FormData();
        const uploadFile = await galleryImageEditor.getFile();
        formData.append('_token', csrfToken);
        formData.append('image', uploadFile || fileInput.files[0]);

        const itemId = document.getElementById('gallery_image_item_id').value;
        const urls = currentGalleryImageUrls();
        const $button = jQuery(this);
        const previousUrl = jQuery('#gallery_image_current_url').val() || '';
        $button.prop('disabled', true).text('Uploading...');

        jQuery.ajax({
            url: urls.update,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                jQuery('#gallery_item_image_file').val('');
                galleryImageEditor.reset();
                jQuery('#gallery_image_upload_success').removeClass('d-none').text(data.message || 'Image uploaded successfully.');

                if (data.queued) {
                    $button.prop('disabled', true).text('Processing...');
                    waitForProcessedGalleryImage(previousUrl)
                        .done(function(fresh) {
                            const freshImageUrl = fresh.image_url
                                ? fresh.image_url + (fresh.image_url.includes('?') ? '&' : '?') + 'v=' + Date.now()
                                : '';
                            jQuery('#gallery_image_uploaded_at').val(fresh.image_uploaded_at || '-');
                            jQuery('#gallery_image_current_url').val(fresh.image_url || '');
                            setImagePreview(freshImageUrl);
                            updateGalleryCardImage(itemId, freshImageUrl);
                            refreshLoadedGallery();
                            jQuery('#galleryImageModal').modal('hide');
                        })
                        .fail(function() {
                            jQuery('#gallery_image_upload_error').removeClass('d-none').text('Image is still processing. Please reopen this image after a few moments.');
                        })
                        .always(function() {
                            $button.prop('disabled', false).text('Upload Image');
                        });
                    return;
                }

                const refreshedImageUrl = data.image_url
                    ? data.image_url + (data.image_url.includes('?') ? '&' : '?') + 'v=' + Date.now()
                    : '';
                jQuery('#gallery_image_uploaded_at').val(data.image_uploaded_at || '-');
                jQuery('#gallery_image_current_url').val(data.image_url || '');
                galleryImageEditor.loadUrl(refreshedImageUrl, 'item-image.jpg');
                updateGalleryCardImage(itemId, refreshedImageUrl);
                refreshLoadedGallery();
                jQuery('#galleryImageModal').modal('hide');
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || xhr.responseJSON?.errors?.image?.[0] || 'Image upload failed.';
                jQuery('#gallery_image_upload_error').removeClass('d-none').text(message);
            },
            complete: function() {
                if ($button.text() !== 'Processing...') {
                    $button.prop('disabled', false).text('Upload Image');
                }
            }
        });
    });

    jQuery('#galleryRemoveImageBtn').on('click', function() {
        if (!confirm('Remove this item image?')) {
            return;
        }

        resetImageMessages();

        const itemId = document.getElementById('gallery_image_item_id').value;
        const urls = currentGalleryImageUrls();
        const $button = jQuery(this);
        $button.prop('disabled', true).text('Removing...');

        jQuery.ajax({
            url: urls.remove,
            method: 'DELETE',
            data: {
                _token: csrfToken
            },
            success: function() {
                jQuery('#gallery_item_image_file').val('');
                galleryImageEditor.reset();
                jQuery('#gallery_image_uploaded_at').val('-');
                jQuery('#gallery_image_current_url').val('');
                setImagePreview('');
                updateGalleryCardImage(itemId, '');
                jQuery('#gallery_image_upload_success').removeClass('d-none').text('Image removed successfully.');
                refreshLoadedGallery();
            },
            error: function(xhr) {
                jQuery('#gallery_image_upload_error').removeClass('d-none').text(xhr.responseJSON?.message || 'Unable to remove image.');
            },
            complete: function() {
                $button.text('Remove Image');
            }
        });
    });

    document.getElementById('applyFilter').addEventListener('click', function () { loadGallery(true); });
    document.getElementById('resetFilter').addEventListener('click', function () {
        Array.from(document.getElementById('itemFilter').options).forEach(function (option) {
            option.selected = false;
        });
        document.getElementById('stockStatusFilter').value = 'in';
        document.getElementById('imageStatusFilter').value = '';
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#itemFilter').val(null).trigger('change');
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
        shareWhatsappWithImages();
    });
    function toggleGoTopButton() {
        goTopBtn.classList.toggle('is-visible', window.scrollY > 350);
    }
    window.addEventListener('scroll', toggleGoTopButton, { passive: true });
    goTopBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    toggleGoTopButton();
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
    window.addEventListener('stock-gallery-media-saved', () => loadGallery(true));
});
</script>
@if($mediaAvailable ?? false)
@include('company.stock_gallery.media')
@endif
@endsection
