@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Barcode History Report</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6 search-wrapper">
                    <label>Barcode / QR / HUID</label>
                    <input type="text" id="code" class="form-control" placeholder="Enter exact barcode or QR code" autocomplete="off">
                    <div id="code_results" class="list-group"></div>
                </div>
                <div class="col-md-6 d-flex align-items-end justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
                    <button id="filter" class="btn btn-success">Search</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    <button id="export_excel" class="btn btn-info">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <table class="table table-bordered" id="barcodeHistoryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Label Code</th>
                        <th>Label Created</th>
                        <th>Label Printed</th>
                        <th>Approval History</th>
                        <th>Sale History</th>
                        <th>Return History</th>
                        <th>Status</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .search-wrapper {
        position: relative;
    }

    #code_results {
        position: absolute;
        z-index: 2000;
        width: 100%;
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: #2b2f4a;
        display: none;
    }

    #code_results .list-group-item {
        background: #2b2f4a;
        color: #f5f5f7;
        border-color: rgba(255, 255, 255, 0.1);
        cursor: pointer;
    }

    #code_results .list-group-item:hover {
        background: #3a3f63;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const $codeInput = $('#code');
    const $codeResults = $('#code_results');
    let suggestTimer = null;

    const table = $('#barcodeHistoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.barcode-history.index', $company->slug) }}",
            data: function (d) {
                d.code = $('#code').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'item_name', orderable: false, searchable: false },
            { data: 'label_code', orderable: false, searchable: false },
            { data: 'label_created_at_fmt', orderable: false, searchable: false },
            { data: 'label_printed_at_fmt', orderable: false, searchable: false },
            { data: 'approval_history_html', orderable: false, searchable: false },
            { data: 'sale_history_html', orderable: false, searchable: false },
            { data: 'return_history_html', orderable: false, searchable: false },
            { data: 'current_status', orderable: false, searchable: false },
        ]
    });

    function loadSuggestions(query) {
        $.get("{{ route('company.reports.barcode-history.suggest', $company->slug) }}", { q: query }, function (res) {
            $codeResults.empty();
            const list = (res && res.data) ? res.data : [];
            if (!list.length) {
                $codeResults.html('<div class="list-group-item">No result</div>').show();
                return;
            }

            let html = '';
            list.forEach(function (row) {
                const code = String(row.code || '').replace(/"/g, '&quot;');
                const itemName = String(row.item_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                html += `
                    <a href="#" class="list-group-item list-group-item-action selectCode" data-code="${code}">
                        ${code}<br>
                        <small>${itemName || '-'}</small>
                    </a>
                `;
            });
            $codeResults.html(html).show();
        });
    }

    $codeInput.on('input', function () {
        const v = $(this).val().trim();
        clearTimeout(suggestTimer);
        if (v.length < 1) {
            $codeResults.hide().empty();
            return;
        }
        suggestTimer = setTimeout(function () {
            loadSuggestions(v);
        }, 180);
    });

    $codeInput.on('change', function () {
        if ($(this).val().trim() !== '') {
            table.draw();
        }
    });

    $codeInput.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            table.draw();
            $codeResults.hide();
        }
    });

    $(document).on('click', '.selectCode', function (e) {
        e.preventDefault();
        const code = String($(this).data('code') || '').trim();
        if (!code) return;
        $codeInput.val(code);
        $codeResults.hide().empty();
        table.draw();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-wrapper').length) {
            $codeResults.hide();
        }
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#code').val('');
        $codeResults.hide().empty();
        table.draw();
    });

    function queryParams() {
        return $.param({
            code: $('#code').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.barcode-history.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.barcode-history.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
