@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Stock Position Report</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Item</label>
                    <select id="item_id" class="form-select">
                        <option value="">All Items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button id="filter" class="btn btn-success me-2">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                </div>
                <div class="col-md-4 d-flex align-items-end justify-content-end mt-2 mt-md-0">
                    <button id="export_excel" class="btn btn-info me-2">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <table class="table table-bordered" id="stockTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Qty Pcs</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
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
    const table = $('#stockTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.stock-position.index', $company->slug) }}",
            data: function (d) {
                d.item_id = $('#item_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'item_name' },
            { data: 'qty_pcs', orderable: false, searchable: false },
            { data: 'gross_weight', orderable: false, searchable: false },
            { data: 'net_weight', orderable: false, searchable: false },
            { data: 'labour_amount', orderable: false, searchable: false },
            { data: 'other_amount', orderable: false, searchable: false },
        ]
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#item_id').val('');
        table.draw();
    });

    function queryParams() {
        return $.param({
            item_id: $('#item_id').val()
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
