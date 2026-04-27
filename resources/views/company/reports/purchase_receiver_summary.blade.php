@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Purchase / Receiver Summary Report</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-md-end gap-2 mt-2 mt-md-0 flex-wrap">
                    <button id="filter" class="btn btn-success">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    <button id="export_excel" class="btn btn-info">Excel</button>
                    <button id="export_pdf" class="btn btn-primary">PDF</button>
                </div>
            </div>

            <table class="table table-bordered" id="purchaseReceiverSummaryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Source</th>
                        <th>Qty</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
                        <th>Fine Wt</th>
                        <th>Metal Amt</th>
                        <th>Labour Amt</th>
                        <th>Other Amt</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Total</th>
                        <th id="t_qty_pcs">0</th>
                        <th id="t_gross_weight">0.000</th>
                        <th id="t_net_weight">0.000</th>
                        <th id="t_fine_weight">0.000</th>
                        <th id="t_metal_amount">0.00</th>
                        <th id="t_labour_amount">0.00</th>
                        <th id="t_other_amount">0.00</th>
                        <th id="t_return_total">0.00</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);

    const table = $('#purchaseReceiverSummaryTable').DataTable({
        processing: true,
        serverSide: true,
        order: [[2, 'desc']],
        ajax: {
            url: "{{ route('company.reports.purchase-receiver-summary.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.customer_id = $('#customer_id').val();
            }
        },
        drawCallback: function () {
            const json = this.api().ajax.json() || {};
            const totals = json.totals || {};

            $('#t_qty_pcs').text((totals.qty_pcs ?? 0).toString());
            $('#t_gross_weight').text(Number(totals.gross_weight ?? 0).toFixed(3));
            $('#t_net_weight').text(Number(totals.net_weight ?? 0).toFixed(3));
            $('#t_fine_weight').text(Number(totals.fine_weight ?? 0).toFixed(3));
            $('#t_metal_amount').text(Number(totals.metal_amount ?? 0).toFixed(2));
            $('#t_labour_amount').text(Number(totals.labour_amount ?? 0).toFixed(2));
            $('#t_other_amount').text(Number(totals.other_amount ?? 0).toFixed(2));
            $('#t_return_total').text(Number(totals.return_total ?? 0).toFixed(2));
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'return_voucher_no' },
            { data: 'return_date' },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'source_type', orderable: false, searchable: false },
            { data: 'qty_pcs', orderable: false, searchable: false },
            { data: 'gross_weight', orderable: false, searchable: false },
            { data: 'net_weight', orderable: false, searchable: false },
            { data: 'fine_weight', orderable: false, searchable: false },
            { data: 'metal_amount', orderable: false, searchable: false },
            { data: 'labour_amount', orderable: false, searchable: false },
            { data: 'other_amount', orderable: false, searchable: false },
            { data: 'return_total' },
        ]
    });

    $('#filter').on('click', function () { table.draw(); });
    $('#reset').on('click', function () {
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('');
        table.draw();
    });

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            customer_id: $('#customer_id').val()
        });
    }

    $('#export_excel').on('click', function () {
        window.location.href = "{{ route('company.reports.purchase-receiver-summary.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#export_pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.purchase-receiver-summary.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
