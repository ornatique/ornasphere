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

        setImagePreview(URL.createObjectURL(file));
    });

    $('#uploadImageBtn').on('click', function() {
        resetImageMessages();

        const fileInput = $('#item_image_file')[0];
        if (!fileInput.files || !fileInput.files[0]) {
            $('#image_upload_error').removeClass('d-none').text('Please choose an image to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('_token', "{{ csrf_token() }}");
        formData.append('image', fileInput.files[0]);

        $('#uploadImageBtn').prop('disabled', true).text('Uploading...');

        $.ajax({
            url: $('#image_upload_url').val(),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                $('#item_image_file').val('');
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

