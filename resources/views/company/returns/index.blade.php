@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center text-end">
            <h4 class="card-title">Approval Return List</h4>
            <a href="{{ route('company.returns.selectSale',$company->slug) }}"
                class="btn btn-primary ">
                Create Approval Return
            </a>
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

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-success" id="filterBtn">Show</button>
                    <button class="btn btn-secondary" id="resetBtn">Reset</button>
                    <a class="btn btn-danger" id="exportPdfBtn" target="_blank">PDF</a>
                </div>

            </div>

            <table class="table table-bordered yajra-datatable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher No</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Item Name</th>
                        <th>Qty</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
                        <th>Fine Wt</th>
                        <th>Metal Amt</th>
                        <th>Labour Amt</th>
                        <th>Other Amt</th>
                        <th>Total Amt</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>

        </div>
    </div>
</div>

@endsection


@push('scripts')
<script>
    $(function() {
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        $('#from_date').val(today);
        $('#to_date').val(today);

        let table = $('.yajra-datatable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,

            ajax: {
                url: "{{ route('company.returns.index',$company->slug) }}",
                data: function(d) {
                    d.from_date = $('#from_date').val();
                    d.to_date = $('#to_date').val();
                    d.customer_id = $('#customer_id').val();
                }
            },

            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'voucher_no',
                    name: 'return_voucher_no'
                },
                {
                    data: 'customer_name',
                    name: 'approval.customer.name'
                },
                {
                    data: 'return_date',
                    name: 'return_date'
                },
                {
                    data: 'item_names',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'qty',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'gross_wt',
                    render: $.fn.dataTable.render.number(',', '.', 3),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'net_wt',
                    render: $.fn.dataTable.render.number(',', '.', 3),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'fine_wt',
                    render: $.fn.dataTable.render.number(',', '.', 3),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'metal_amt',
                    render: $.fn.dataTable.render.number(',', '.', 2),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'labour_amt',
                    render: $.fn.dataTable.render.number(',', '.', 2),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'other_amt',
                    render: $.fn.dataTable.render.number(',', '.', 2),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'total_amt',
                    render: $.fn.dataTable.render.number(',', '.', 2),
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'created_by',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        // 🔍 FILTER
        $('#filterBtn').click(function() {
            updatePdfLink();
            table.draw();
        });

        // 🔄 RESET
        $('#resetBtn').click(function() {
            $('#customer_id').val('');
            $('#from_date').val(today);
            $('#to_date').val(today);
            updatePdfLink();
            table.draw();
        });

        function updatePdfLink() {
            const base = "{{ route('company.returns.export.pdf', $company->slug) }}";
            const params = new URLSearchParams({
                customer_id: $('#customer_id').val() || '',
                from_date: $('#from_date').val() || '',
                to_date: $('#to_date').val() || '',
            });
            $('#exportPdfBtn').attr('href', `${base}?${params.toString()}`);
        }

        $('#customer_id, #from_date, #to_date').on('change', updatePdfLink);
        updatePdfLink();

    });
</script>
@endpush
