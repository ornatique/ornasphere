@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">
        {{-- HEADER --}}
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Item Sets</h4>

            <div>
                <a href="{{ route('company.item_sets.index', $company->slug) }}" class="btn btn-primary">
                    Create Label Item
                </a>
            </div>
        </div>

        {{-- FILTER --}}
        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control" value="{{ now()->toDateString() }}">
                </div>

                <div class="col-md-3">
                    <label>Item</label>
                    <select id="item_id" class="form-select itemset-search-select">
                        <option value="">All Items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-50" id="filterBtn" type="button">
                        Apply Filter
                    </button>
                    <button class="btn btn-secondary w-50" id="resetBtn" type="button">
                        Reset
                    </button>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="card-body">

            <div class="itemset-view-actions d-flex justify-content-end gap-2 mb-3">
                <button type="button" class="btn btn-primary" id="defaultViewBtn">Default List</button>
                <button type="button" class="btn btn-secondary" id="bulkViewBtn">Bulk View</button>
            </div>

            <div id="defaultListWrap">
                <table class="table table-bordered w-100" id="itemset-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Label Code</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Other Charges</th>
                            <th>Net Wt</th>
                            <th>Qty Pcs</th>
                            <th>Print Date Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div id="bulkListWrap" style="display:none;">
                <table class="table table-bordered w-100" id="bulk-itemset-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Total Pcs</th>
                            <th>Total Gross Wt</th>
                            <th>Total Net Wt</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>

        </div>
    </div>

</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-0">

            <div class="modal-header border-bottom">
                <h5 class="modal-title">Edit Item Set</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="edit_id">
                <input type="hidden" id="edit_encrypted_id">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Gross Weight</label>
                        <input type="text" id="gross_weight" class="form-control  text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Net Weight</label>
                        <input type="text" id="net_weight" class="form-control text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Other Weight</label>
                        <input type="text" id="other" class="form-control  text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Size</label>
                        <input type="text" id="size" class="form-control  text-white border-0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Other Charges</label>
                        <input type="text" id="other_charges" class="form-control  text-white border-0">
                    </div>

                </div>

            </div>

            <div class="modal-footer border-top">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="updateBtn">
                    Update
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="imageModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-0">

            <div class="modal-header border-bottom">
                <h5 class="modal-title">Item Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="image_show_url">
                <input type="hidden" id="image_upload_url">
                <input type="hidden" id="image_remove_url">

                <div class="row">
                    <div class="col-md-7 mb-3">
                        <div class="item-image-preview-wrap">
                            <img id="item_image_preview" class="item-image-preview" alt="Item image preview">
                            <div id="image_crop_box" class="image-crop-box">
                                <span class="image-crop-handle image-crop-handle-nw" data-handle="nw"></span>
                                <span class="image-crop-handle image-crop-handle-ne" data-handle="ne"></span>
                                <span class="image-crop-handle image-crop-handle-sw" data-handle="sw"></span>
                                <span class="image-crop-handle image-crop-handle-se" data-handle="se"></span>
                            </div>
                            <div id="item_image_empty" class="item-image-empty">No image uploaded</div>
                        </div>
                    </div>

                    <div class="col-md-5 mb-3">
                        <div class="mb-3">
                            <label>Item</label>
                            <input type="text" id="image_item_name" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Label Code</label>
                            <input type="text" id="image_label_code" class="form-control text-white border-0" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Upload Image</label>
                            <input type="file" id="item_image_file" class="form-control text-white border-0" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted d-block mt-1">JPG, PNG or WebP up to 20 MB. Stored as compressed WebP.</small>
                        </div>

                        <div class="image-edit-tools border rounded p-2 mb-3" id="image_edit_tools">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-warning" id="cropBtn" type="button"><i class="mdi mdi-crop"></i> Crop</button>
                                <button class="btn btn-sm btn-info" id="rotateLeftBtn" type="button"><i class="mdi mdi-rotate-left"></i> Rotate Left</button>
                                <button class="btn btn-sm btn-info" id="rotateRightBtn" type="button"><i class="mdi mdi-rotate-right"></i> Rotate Right</button>
                                <button class="btn btn-sm btn-secondary" id="resetCropBtn" type="button"><i class="mdi mdi-crop-free"></i> Reset Crop</button>
                            </div>
                            <small class="text-muted d-block mt-2">Click Crop, then drag or resize the box on the image. Upload saves the selected area.</small>
                        </div>

                        <div class="mb-3">
                            <label>Uploaded</label>
                            <input type="text" id="image_uploaded_at" class="form-control text-white border-0" readonly>
                        </div>
                    </div>
                </div>

                <div id="image_upload_error" class="alert alert-danger d-none mb-0"></div>
                <div id="image_upload_success" class="alert alert-success d-none mb-0"></div>
            </div>

            <div class="modal-footer border-top">
                <button class="btn btn-danger me-auto" id="removeImageBtn" type="button">Remove Image</button>
                <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="uploadImageBtn" type="button">Upload Image</button>
            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #defaultListWrap,
    #bulkListWrap,
    #itemset-table_wrapper,
    #bulk-itemset-table_wrapper {
        width: 100%;
    }

    #bulk-itemset-table {
        table-layout: fixed;
    }

    #bulk-itemset-table th,
    #bulk-itemset-table td {
        vertical-align: middle;
        white-space: normal;
    }

    #bulk-itemset-table th:nth-child(1),
    #bulk-itemset-table td:nth-child(1) {
        width: 56px;
        text-align: center;
    }

    #bulk-itemset-table th:nth-child(2),
    #bulk-itemset-table td:nth-child(2) {
        width: 120px;
    }

    #bulk-itemset-table th:nth-child(4),
    #bulk-itemset-table td:nth-child(4),
    #bulk-itemset-table th:nth-child(5),
    #bulk-itemset-table td:nth-child(5),
    #bulk-itemset-table th:nth-child(6),
    #bulk-itemset-table td:nth-child(6) {
        width: 150px;
    }

    #bulk-itemset-table th:nth-child(7),
    #bulk-itemset-table td:nth-child(7) {
        width: 110px;
        text-align: center;
    }

    .item-image-preview-wrap {
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

    .item-image-preview {
        display: none;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        object-position: center;
    }

    .item-image-empty {
        color: rgba(255, 255, 255, 0.62);
    }

    .image-crop-box {
        display: none;
        position: absolute;
        z-index: 4;
        border: 2px solid #2f80ff;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);
        cursor: move;
        touch-action: none;
    }

    .image-crop-box::before,
    .image-crop-box::after {
        content: '';
        position: absolute;
        inset: 33.333% 0 auto 0;
        border-top: 1px dashed rgba(255, 255, 255, 0.75);
    }

    .image-crop-box::after {
        inset: 66.666% 0 auto 0;
    }

    .image-crop-handle {
        position: absolute;
        width: 12px;
        height: 12px;
        background: #2f80ff;
        border: 2px solid #ffffff;
        border-radius: 50%;
        touch-action: none;
    }

    .image-crop-handle-nw {
        left: -7px;
        top: -7px;
        cursor: nwse-resize;
    }

    .image-crop-handle-ne {
        right: -7px;
        top: -7px;
        cursor: nesw-resize;
    }

    .image-crop-handle-sw {
        left: -7px;
        bottom: -7px;
        cursor: nesw-resize;
    }

    .image-crop-handle-se {
        right: -7px;
        bottom: -7px;
        cursor: nwse-resize;
    }
</style>
@endpush


@push('scripts')
<script>
    let currentView = 'default';

    if (window.jQuery && $.fn.select2) {
        $('#item_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            placeholder: 'All Items'
        });
    }

    let table = $('#itemset-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('company.list_itemset', $company->slug) }}",
            data: function(d) {
                d.item_id = $('#item_id').val();
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.view_mode = 'default';
            }
        },
                columns: [
            {
                data: 'DT_RowIndex',
                name: 'id',
                orderable: false,
                searchable: false
            },
            {
                data: 'date',
                name: 'date',
                searchable: false
            },
            {
                data: 'item_name',
                name: 'item_name',
                searchable: true
            },
            {
                data: 'qr_code',
                name: 'qr_code',
                searchable: true
            },
            {
                data: 'gross_weight',
                name: 'gross_weight',
                searchable: true
            },
            {
                data: 'other_weight',
                name: 'other_weight',
                searchable: true
            },
            {
                data: 'other_charges',
                name: 'other_charges',
                searchable: true
            },
            {
                data: 'net_weight',
                name: 'net_weight',
                searchable: true
            },
            {
                data: 'qty_pcs',
                name: 'qty_pcs',
                searchable: false
            },
            {
                data: 'printed_at',
                name: 'printed_at',
                searchable: false
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false
            }
        ]
    });


    // 🔍 FILTER
    let bulkTable = $('#bulk-itemset-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: "{{ route('company.list_itemset', $company->slug) }}",
            data: function(d) {
                d.item_id = $('#item_id').val();
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.view_mode = 'bulk';
            }
        },
        columns: [
            {
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                orderable: false,
                searchable: false
            },
            {
                data: 'date',
                name: 'batch_date',
                searchable: false
            },
            {
                data: 'item_name',
                name: 'item_name',
                searchable: true
            },
            {
                data: 'qty_pcs',
                name: 'total_pcs',
                searchable: false
            },
            {
                data: 'gross_weight',
                name: 'total_gross_weight',
                searchable: false
            },
            {
                data: 'net_weight',
                name: 'total_net_weight',
                searchable: false
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false
            }
        ]
    });

    function switchItemSetView(view) {
        currentView = view;
        const isBulk = view === 'bulk';

        $('#defaultListWrap').toggle(!isBulk);
        $('#bulkListWrap').toggle(isBulk);
        $('#defaultViewBtn').toggleClass('btn-primary', !isBulk).toggleClass('btn-secondary', isBulk);
        $('#bulkViewBtn').toggleClass('btn-primary', isBulk).toggleClass('btn-secondary', !isBulk);

        setTimeout(function() {
            if (isBulk) {
                bulkTable.columns.adjust().draw();
            } else {
                table.columns.adjust().draw();
            }
        }, 0);
    }

    $('#defaultViewBtn').on('click', function() {
        switchItemSetView('default');
    });

    $('#bulkViewBtn').on('click', function() {
        switchItemSetView('bulk');
    });

    $(document).on('click', '.viewBulkItems', function() {
        const date = $(this).data('date');
        const itemId = $(this).data('item-id');

        $('#from_date').val(date);
        $('#to_date').val(date);
        $('#item_id').val(itemId);
        switchItemSetView('default');
    });

    function redrawCurrentItemSetTable() {
        if (currentView === 'bulk') {
            bulkTable.draw();
            return;
        }

        table.draw();
    }

    $('#filterBtn').click(function() {
        redrawCurrentItemSetTable();
    });

    $('#resetBtn').click(function() {
        const today = "{{ now()->toDateString() }}";
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#item_id').val('').trigger('change.select2');
        redrawCurrentItemSetTable();
    });


    // 🗑 DELETE
    $(document).on('click', '.deleteBtn', function() {

        let url = $(this).data('url');

        if (confirm('Delete this item?')) {
            $.ajax({
                url: url,
                type: "DELETE",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function() {
                    table.draw();
                }
            });
        }
    });

    $(document).on('click', '.editBtn', function() {

        let url = $(this).data('url');

        $.get(url, function(data) {

            $('#edit_id').val(data.id);
            $('#edit_encrypted_id').val(data.encrypted_id);
            $('#gross_weight').val(data.gross_weight);
            $('#net_weight').val(data.net_weight);
            $('#other').val(data.other);
            $('#size').val(data.size);
            $('#other_charges').val(data.sale_other);

            $('#editModal').modal('show');
        });

    });

    function recalculateNetWeight() {
        const gross = parseFloat($('#gross_weight').val()) || 0;
        const other = parseFloat($('#other').val()) || 0;
        const net = Math.max(0, gross - other);
        $('#net_weight').val(net.toFixed(3));
    }

    $('#gross_weight, #other').on('input', recalculateNetWeight);

    $('#updateBtn').click(function() {

        let encryptedId = $('#edit_encrypted_id').val();

        $.post("{{ url('company/'.$company->slug.'/itemsets') }}/" + encryptedId + "/update", {
            _token: "{{ csrf_token() }}",
            gross_weight: $('#gross_weight').val(),
            other: $('#other').val(),
            size: $('#size').val(),
            other_charges: $('#other_charges').val(),
        }, function() {

            $('#editModal').modal('hide');
            table.draw();

        });

    });

    function resetImageMessages() {
        $('#image_upload_error').addClass('d-none').text('');
        $('#image_upload_success').addClass('d-none').text('');
    }

    function createImageEditor(options) {
        const state = {
            image: null,
            rotation: 0,
            editedFile: null,
            previewUrl: null,
            fileName: 'item-image.jpg',
            renderWidth: 0,
            renderHeight: 0,
            cropActive: false,
            drag: null
        };

        const $tools = $(options.tools);
        const $wrap = $(options.wrap);
        const $preview = $(options.preview);
        const $cropBox = $(options.cropBox);
        const $crop = $(options.crop);
        const $resetCrop = $(options.resetCrop);
        const $rotateLeft = $(options.rotateLeft);
        const $rotateRight = $(options.rotateRight);

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
            state.editedFile = null;
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
                    state.editedFile = file;
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
                $('#image_upload_error').removeClass('d-none').text('Unable to read selected image.');
            };
            img.src = imageUrl;
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
                handle: $(event.target).data('handle') || 'move',
                startX: point.clientX,
                startY: point.clientY,
                left: position.left,
                top: position.top,
                width: $cropBox.outerWidth(),
                height: $cropBox.outerHeight()
            };
            event.preventDefault();
        });

        $(document).on('mousemove.imageEditor touchmove.imageEditor', function(event) {
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

        $(document).on('mouseup.imageEditor touchend.imageEditor touchcancel.imageEditor', function() {
            state.drag = null;
        });

        reset();

        return {
            load,
            reset,
            getFile: renderToFile
        };
    }

    const imageEditor = createImageEditor({
        tools: '#image_edit_tools',
        wrap: '.item-image-preview-wrap',
        preview: '#item_image_preview',
        cropBox: '#image_crop_box',
        crop: '#cropBtn',
        resetCrop: '#resetCropBtn',
        rotateLeft: '#rotateLeftBtn',
        rotateRight: '#rotateRightBtn'
    });

    function setImagePreview(url) {
        if (url) {
            $('#item_image_preview').attr('src', url).show();
            $('#item_image_empty').hide();
            $('#removeImageBtn').prop('disabled', false).show();
            return;
        }

        $('#item_image_preview').removeAttr('src').hide();
        $('#item_image_empty').show();
        $('#removeImageBtn').prop('disabled', true).hide();
    }

    function loadItemImage() {
        resetImageMessages();
        $('#item_image_file').val('');
        imageEditor.reset();

        $.get($('#image_show_url').val(), function(data) {
            $('#image_item_name').val(data.item_name || '-');
            $('#image_label_code').val(data.label_code || '-');
            $('#image_uploaded_at').val(data.image_uploaded_at || '-');
            setImagePreview(data.image_url || '');
            $('#imageModal').modal('show');
        }).fail(function() {
            $('#image_upload_error').removeClass('d-none').text('Unable to load item image details.');
            $('#imageModal').modal('show');
        });
    }

    $(document).on('click', '.imageBtn', function() {
        $('#image_show_url').val($(this).data('show-url'));
        $('#image_upload_url').val($(this).data('upload-url'));
        $('#image_remove_url').val($(this).data('remove-url'));
        loadItemImage();
    });

    $('#item_image_file').on('change', function() {
        resetImageMessages();

        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            loadItemImage();
            return;
        }

        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            $(this).val('');
            $('#image_upload_error').removeClass('d-none').text('Please select a JPG, PNG or WebP image.');
            return;
        }

        imageEditor.load(file);
    });

    $('#uploadImageBtn').on('click', async function() {
        resetImageMessages();

        const fileInput = $('#item_image_file')[0];
        if (!fileInput.files || !fileInput.files[0]) {
            $('#image_upload_error').removeClass('d-none').text('Please choose an image to upload.');
            return;
        }

        const formData = new FormData();
        const uploadFile = await imageEditor.getFile();
        formData.append('_token', "{{ csrf_token() }}");
        formData.append('image', uploadFile || fileInput.files[0]);

        $('#uploadImageBtn').prop('disabled', true).text('Uploading...');

        $.ajax({
            url: $('#image_upload_url').val(),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                $('#item_image_file').val('');
                imageEditor.reset();
                $('#image_uploaded_at').val(data.image_uploaded_at || '-');
                setImagePreview(data.image_url || '');
                $('#image_upload_success').removeClass('d-none').text(data.message || 'Image uploaded successfully.');
                table.draw(false);
                $('#imageModal').modal('hide');
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || xhr.responseJSON?.errors?.image?.[0] || 'Image upload failed.';
                $('#image_upload_error').removeClass('d-none').text(message);
            },
            complete: function() {
                $('#uploadImageBtn').prop('disabled', false).text('Upload Image');
            }
        });
    });

    $('#removeImageBtn').on('click', function() {
        if (!confirm('Remove this item image?')) {
            return;
        }

        resetImageMessages();
        $('#removeImageBtn').prop('disabled', true).text('Removing...');

        $.ajax({
            url: $('#image_remove_url').val(),
            method: 'DELETE',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function() {
                $('#item_image_file').val('');
                imageEditor.reset();
                $('#image_uploaded_at').val('-');
                setImagePreview('');
                $('#image_upload_success').removeClass('d-none').text('Image removed successfully.');
                table.draw(false);
            },
            error: function(xhr) {
                $('#image_upload_error').removeClass('d-none').text(xhr.responseJSON?.message || 'Unable to remove image.');
            },
            complete: function() {
                $('#removeImageBtn').text('Remove Image');
            }
        });
    });
</script>
@endpush

