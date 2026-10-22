@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
@php($reportCompanyIds = \App\Services\ReportBranches::ids($company))
@include('company.reports.partials.branch_filter')
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
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ count($reportCompanyIds) > 1 ? ' â€” '.\App\Services\ReportBranches::name($customer->company_id) : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="stock_type">Stock Type</label>
                    <select id="stock_type" class="form-select">
                        @foreach([''=>'All Stock Types', 'raw_material'=>'Raw Material', 'finished_item'=>'Finished Item', 'customer_received'=>'Customer Received', 'scrap'=>'Scrap', 'repair'=>'Repair'] as $value=>$label)
                        <option value="{{ $value }}" @selected(request('stock_type', '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="filter" class="btn btn-success me-2">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                </div>
                <div class="col-md-2 d-flex align-items-end justify-content-end mt-2 mt-md-0">
                    <button id="export_excel" class="btn btn-info me-2">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <div class="row g-3 mb-3" id="stockSummary">
@foreach(['with_image'=>'With image','without_image'=>'Without image'] as $key=>$label)
<div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="text-muted small">{{ $label }}</div><div class="fw-bold" data-summary="{{ $key }}">0</div></div></div>
@endforeach
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

            <section class="border rounded p-3 mb-4">
                <h4>Supplier metal balances</h4>
                <label for="supplier_balance_id">Supplier</label>
                <select id="supplier_balance_id" class="form-control mb-3"><option value="">All suppliers</option>
                @foreach(\App\Models\Customer::whereIn('company_id', $reportCompanyIds)->whereHas('categoryPerson', fn($q) => $q->whereRaw('LOWER(TRIM(category_name)) = ?', ['bullion']))->orderBy('name')->get() as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
                </select>
                <p>Account balances only; excluded from physical stock totals. Branch and Supplier filters apply separately from the stock filters.</p>
                <div class="table-responsive"><table class="table table-bordered mb-0">
                    <thead><tr><th>Supplier</th><th>Branch</th><th>Cash balance (INR)</th><th>Gold fine (g)</th><th>Silver fine (g)</th></tr></thead>
                    <tbody id="supplier-balances"><tr><td colspan="5">Loading supplier balances…</td></tr></tbody>
                </table></div>
            </section>
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
                    <th>With image</th><th>Without image</th><th>Branch</th></tr>
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
                d.stock_type = $('#stock_type').val();
                d.supplier_id = $('#supplier_balance_id').val();
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
            { data: 'with_image', orderable: false, searchable: false, className: 'text-center' },
            { data: 'without_image', orderable: false, searchable: false, className: 'text-center' },
            { data: 'branch_name', orderable: false, searchable: false, defaultContent: '' },
        ]
    });

    table.on('xhr.dt', function (e, settings, json) {
        const balances = document.getElementById('supplier-balances');
        balances.replaceChildren();
        const supplierRows = (json && json.supplier_balances) || [];
        const missingBalances = !json || !Array.isArray(json.supplier_balances);
        supplierRows.forEach(function (row) {
            const tr = document.createElement('tr');
            [row.supplier_name, row.branch_name, Number(row.cash_balance).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}), Number(row.gold_balance).toFixed(3), Number(row.silver_balance).toFixed(3)].forEach(function (value) {
                const td = document.createElement('td'); td.textContent = value; tr.appendChild(td);
            });
            balances.appendChild(tr);
        });
        if (!supplierRows.length) {
            const tr = document.createElement('tr'), td = document.createElement('td');
            td.colSpan = 5; td.textContent = missingBalances ? 'Supplier balances were not returned. Update the report controller and SupplierMetalBalances service on the server.' : 'No supplier balances for the selected supplier and branches.';
            tr.appendChild(td); balances.appendChild(tr);
        }
        const summary = (json && json.summary) ? json.summary : {};
        $('#stockSummary [data-summary]').each(function () {
            const key = $(this).data('summary');
            $(this).text(summary[key] || (key === 'qty_pcs' ? '0' : '0.000'));
        });
    });

    $('#supplier_balance_id').on('change', function () { table.draw(); });
    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#item_id').val('').trigger('change.select2');
        $('#customer_id').val('').trigger('change.select2');
        $('#stock_type').val('');
        table.draw();
    });

    function queryParams() {
        return $.param({
            item_id: $('#item_id').val(),
            customer_id: $('#customer_id').val(),
            stock_type: $('#stock_type').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = window.reportBranchUrl("{{ route('company.reports.stock-position.export.excel', $company->slug) }}?" + queryParams());
    });

    $('#export_pdf').on('click', function () {
        window.location.href = window.reportBranchUrl("{{ route('company.reports.stock-position.export.pdf', $company->slug) }}?" + queryParams());
    });
});
</script>
@endpush
