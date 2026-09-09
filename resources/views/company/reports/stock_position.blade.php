@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Stock Position Report</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>Item</label>
                    <select id="item_id" class="form-select">
                        <option value="">All Items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Party</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Parties</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button id="filter" class="btn btn-success me-2">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-end mt-2 mt-md-0">
                    <button id="export_excel" class="btn btn-info me-2">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <div class="row g-3 mb-3" id="stockSummary">
                <div class="col-md-2 col-sm-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total Qty Pcs</div>
                        <div class="fw-bold" data-summary="qty_pcs">0</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total Gross Wt</div>
                        <div class="fw-bold" data-summary="gross_weight">0.000</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total Other Wt</div>
                        <div class="fw-bold" data-summary="other_weight">0.000</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total Net Wt</div>
                        <div class="fw-bold" data-summary="net_weight">0.000</div>
                    </div>
                </div>
            </div>

            <table class="table table-bordered" id="stockTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Stock Type</th>
                        <th>Party</th>
                        <th>Qty Pcs</th>
                        <th>Gross Wt</th>
                        <th>Other Wt</th>
                        <th>Net Wt</th>
                        <th>Fine Wt</th>
                        <th>Labour Amt</th>
                        <th>Other Amt</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    if ($.fn.select2) {
        $('#item_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Items',
            allowClear: true
        });

        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Parties',
            allowClear: true
        });
    }

    const table = $('#stockTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.stock-position.index', $company->slug) }}",
            data: function (d) {
                d.item_id = $('#item_id').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'item_name_link', name: 'item_name' },
            { data: 'stock_type_name', name: 'stock_type_name' },
            { data: 'customer_name', name: 'customer_name' },
            { data: 'qty_pcs', orderable: false, searchable: false },
            { data: 'gross_weight', orderable: false, searchable: false },
            { data: 'other_weight', orderable: false, searchable: false },
            { data: 'net_weight', orderable: false, searchable: false },
            { data: 'fine_weight', orderable: false, searchable: false },
            { data: 'labour_amount', orderable: false, searchable: false },
            { data: 'other_amount', orderable: false, searchable: false },
        ]
    });

    table.on('xhr.dt', function (e, settings, json) {
        const summary = (json && json.summary) ? json.summary : {};
        $('#stockSummary [data-summary]').each(function () {
            const key = $(this).data('summary');
            $(this).text(summary[key] || (key === 'qty_pcs' ? '0' : '0.000'));
        });
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#item_id').val('').trigger('change.select2');
        $('#customer_id').val('').trigger('change.select2');
        table.draw();
    });

    function queryParams() {
        return $.param({
            item_id: $('#item_id').val(),
            customer_id: $('#customer_id').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.stock-position.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.stock-position.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
