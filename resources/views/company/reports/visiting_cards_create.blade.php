@extends('company_layout.admin')

@section('title', 'Bulk Visiting Card Upload')

@section('content')
<div class="content-wrapper">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Bulk Visiting Card Upload</h4>
            <a href="{{ route('company.reports.visiting-cards.index', $company->slug) }}" class="btn btn-secondary">Back to List</a>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Select Visiting Card Images</label>
                    <input type="file" class="form-control" id="bulk_card_images" multiple accept="image/*">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Language</label>
                    <input type="text" class="form-control" id="bulk_original_language" placeholder="gu / hi / en">
                </div>
                <div class="col-md-5 d-flex gap-2 justify-content-md-end">
                    <button class="btn btn-warning" id="bulkExtractBtn" type="button">Extract Bulk</button>
                    <button class="btn btn-success" id="bulkSaveBtn" type="button" disabled>Save All</button>
                </div>
            </div>
            <div class="mt-2 small text-muted" id="bulkStatus"></div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="bulkPreviewTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Pincode</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No extracted data yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    let bulkRecords = [];

    function renderBulkPreview() {
        const $tbody = $('#bulkPreviewTable tbody');
        $tbody.empty();
        if (!bulkRecords.length) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">No extracted data yet.</td></tr>');
            $('#bulkSaveBtn').prop('disabled', true);
            return;
        }

        bulkRecords.forEach((r, idx) => {
            const f = r.fields || {};
            $tbody.append(`
                <tr data-row="${idx}">
                    <td>${idx + 1}</td>
                    <td><input class="form-control form-control-sm bulk-name" value="${(f.name || '').replace(/"/g, '&quot;')}"></td>
                    <td><input class="form-control form-control-sm bulk-mobile" value="${(f.mobile_no || '').replace(/"/g, '&quot;')}"></td>
                    <td><input class="form-control form-control-sm bulk-email" value="${(f.email || '').replace(/"/g, '&quot;')}"></td>
                    <td><input class="form-control form-control-sm bulk-city" value="${(f.city || '').replace(/"/g, '&quot;')}"></td>
                    <td><input class="form-control form-control-sm bulk-pincode" value="${(f.pincode || '').replace(/"/g, '&quot;')}"></td>
                    <td><textarea class="form-control form-control-sm bulk-address" rows="2">${(f.address || '')}</textarea></td>
                </tr>
            `);
        });

        $('#bulkSaveBtn').prop('disabled', false);
    }

    $('#bulkExtractBtn').on('click', function () {
        const files = $('#bulk_card_images')[0].files;
        if (!files || !files.length) {
            $('#bulkStatus').text('Please select one or more images.');
            return;
        }

        const fd = new FormData();
        for (let i = 0; i < files.length; i++) {
            fd.append('card_images[]', files[i]);
        }
        const lang = $('#bulk_original_language').val().trim();
        if (lang) fd.append('original_language', lang);

        $('#bulkStatus').text('Extracting...');
        $('#bulkExtractBtn').prop('disabled', true);
        $.ajax({
            url: "{{ route('company.reports.visiting-cards.extract-bulk', $company->slug) }}",
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
            success: function (res) {
                bulkRecords = (res && res.data) ? res.data.filter(x => x.success) : [];
                renderBulkPreview();
                $('#bulkStatus').text(`Extracted ${bulkRecords.length} records.`);
            },
            error: function (xhr) {
                bulkRecords = [];
                renderBulkPreview();
                const msg = xhr?.responseJSON?.message || 'Bulk extract failed.';
                $('#bulkStatus').text(msg);
            },
            complete: function () {
                $('#bulkExtractBtn').prop('disabled', false);
            }
        });
    });

    $('#bulkSaveBtn').on('click', function () {
        if (!bulkRecords.length) return;

        const records = bulkRecords.map((r, idx) => {
            const $row = $(`#bulkPreviewTable tbody tr[data-row="${idx}"]`);
            const f = r.fields || {};
            const mobile = ($row.find('.bulk-mobile').val() || '').trim();
            return {
                image_path: r.image_path || null,
                name: ($row.find('.bulk-name').val() || '').trim() || null,
                mobile_no: mobile || null,
                mobile_numbers: mobile ? [mobile] : (f.mobile_numbers || null),
                email: ($row.find('.bulk-email').val() || '').trim() || null,
                address: ($row.find('.bulk-address').val() || '').trim() || null,
                city: ($row.find('.bulk-city').val() || '').trim() || null,
                pincode: ($row.find('.bulk-pincode').val() || '').trim() || null,
                original_language: r.original_language || null,
                original_text: r.original_text || null,
                english_text: r.english_text || null
            };
        });

        $('#bulkStatus').text('Saving...');
        $('#bulkSaveBtn').prop('disabled', true);
        $.ajax({
            url: "{{ route('company.reports.visiting-cards.bulk-save', $company->slug) }}",
            method: 'POST',
            data: JSON.stringify({ records }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}" },
            success: function (res) {
                const saved = res.saved_count || 0;
                const failed = res.failed_count || 0;
                if (saved > 0 && failed === 0) {
                    window.location.href = "{{ route('company.reports.visiting-cards.index', $company->slug) }}";
                    return;
                }
                $('#bulkStatus').text(`Saved ${saved} records. Failed: ${failed}`);
            },
            error: function (xhr) {
                const msg = xhr?.responseJSON?.message || 'Bulk save failed.';
                $('#bulkStatus').text(msg);
            },
            complete: function () {
                $('#bulkSaveBtn').prop('disabled', false);
            }
        });
    });
});
</script>
@endpush
