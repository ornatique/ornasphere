@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Outstanding Amount Report</h4>
        </div>
        <div class="card-body">
            <style>
                .outstanding-report .filter-block label {
                    margin-bottom: 6px;
                    font-weight: 500;
                }
                .outstanding-report .filter-toggle-row {
                    min-height: 24px;
                    display: flex;
                    align-items: center;
                    margin-bottom: 8px;
                }
                .outstanding-report .filter-toggle-row .form-check {
                    margin-bottom: 0 !important;
                }
                .outstanding-report .form-control:disabled,
                .outstanding-report .form-select:disabled {
                    background: #2e3158 !important;
                    color: #97a0c7 !important;
                    opacity: 1;
                    border-color: #3d4372;
                    cursor: not-allowed;
                }
                .outstanding-report .action-buttons {
                    min-height: 100px;
                    display: flex;
                    align-items: end;
                    justify-content: flex-start;
                    gap: 10px;
                }
                .outstanding-report .export-buttons {
                    min-height: 100px;
                    display: flex;
                    align-items: end;
                    justify-content: flex-end;
                    gap: 10px;
                }
                .outstanding-report .range-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                }
                .outstanding-report .range-grid .input-block label {
                    display: block;
                }
                .outstanding-report .summary-cards {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 12px;
                }
                .outstanding-report .summary-card {
                    border: 1px solid #3a3f6d;
                    border-radius: 8px;
                    padding: 10px 12px;
                    background: rgba(255, 255, 255, 0.02);
                }
                .outstanding-report .summary-card .title {
                    font-size: 13px;
                    color: #a5aed4;
                    margin-bottom: 4px;
                    display: block;
                }
                .outstanding-report .summary-card .value {
                    font-size: 22px;
                    font-weight: 700;
                    line-height: 1.1;
                    color: #fff;
                }
                @media (max-width: 991.98px) {
                    .outstanding-report .action-buttons,
                    .outstanding-report .export-buttons {
                        justify-content: flex-start;
                    }
                }
            </style>

            <div class="outstanding-report">
            <div class="row g-3 mb-3">
                <div class="col-md-12 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                            <input class="form-check-input filter-toggle" type="checkbox" id="use_default_report" checked>
                            <label class="form-check-label" for="use_default_report">Default Report</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_date">
                        <label class="form-check-label" for="use_date">Use Date</label>
                    </div>
                    </div>
                    <div class="range-grid">
                        <div class="input-block">
                            <label>From Date</label>
                            <input type="date" id="from_date" class="form-control filter-input" disabled>
                        </div>
                        <div class="input-block">
                            <label>To Date</label>
                            <input type="date" id="to_date" class="form-control filter-input" disabled>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_customer">
                        <label class="form-check-label" for="use_customer">Use Party</label>
                    </div>
                    </div>
                    <label>Party</label>
                    <select id="customer_id" class="form-select filter-input" disabled>
                        <option value="">All Parties</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_city">
                        <label class="form-check-label" for="use_city">Use City</label>
                    </div>
                    </div>
                    <label>City</label>
                    <select id="city" class="form-select filter-input" disabled>
                        <option value="">All Cities</option>
                        @foreach($cities as $city)
                            <option value="{{ $city }}">{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_payment_mode">
                        <label class="form-check-label" for="use_payment_mode">Use Mode</label>
                    </div>
                    </div>
                    <label>Payment Mode</label>
                    <select id="payment_mode" class="form-select filter-input" disabled>
                        <option value="">All Modes</option>
                        <option value="cash">Cash</option>
                        <option value="online">Online</option>
                        <option value="upi">UPI</option>
                        <option value="bank">Bank</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="action-buttons">
                    <button id="filter" class="btn btn-success">Filter</button>
                    <button id="reset" class="btn btn-secondary">Reset</button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_weight">
                        <label class="form-check-label" for="use_weight">Use Weight</label>
                    </div>
                    </div>
                    <div class="range-grid">
                        <div class="input-block">
                            <label>Weight From</label>
                            <input type="number" step="0.001" id="weight_from" class="form-control filter-input" placeholder="0.000" disabled>
                        </div>
                        <div class="input-block">
                            <label>Weight To</label>
                            <input type="number" step="0.001" id="weight_to" class="form-control filter-input" placeholder="0.000" disabled>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 filter-block">
                    <div class="filter-toggle-row">
                        <div class="form-check">
                        <input class="form-check-input filter-toggle" type="checkbox" id="use_amount">
                        <label class="form-check-label" for="use_amount">Use Amount</label>
                    </div>
                    </div>
                    <div class="range-grid">
                        <div class="input-block">
                            <label>Amount From</label>
                            <input type="number" step="0.01" id="amount_from" class="form-control filter-input" placeholder="0.00" disabled>
                        </div>
                        <div class="input-block">
                            <label>Amount To</label>
                            <input type="number" step="0.01" id="amount_to" class="form-control filter-input" placeholder="0.00" disabled>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="export-buttons">
                    <button id="excel" class="btn btn-primary">Excel</button>
                    <button id="pdf" class="btn btn-danger">PDF</button>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 summary-cards">
                    <div class="summary-card"><span class="title">Vouchers</span><span class="value" id="sum_vouchers">0</span></div>
                    <div class="summary-card"><span class="title">Gross Wt</span><span class="value" id="sum_gross">0.000</span></div>
                    <div class="summary-card"><span class="title">Net Wt</span><span class="value" id="sum_net">0.000</span></div>
                    <div class="summary-card"><span class="title">Total Amount</span><span class="value" id="sum_total">0.00</span></div>
                    <div class="summary-card"><span class="title">Amount In</span><span class="value" id="sum_in">0.00</span></div>
                    <div class="summary-card"><span class="title">Amount Out</span><span class="value" id="sum_out">0.00</span></div>
                    <div class="summary-card"><span class="title">Pending</span><span class="value" id="sum_pending">0.00</span></div>
                </div>
            </div>

            <table class="table table-bordered" id="outstandingTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Voucher No</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>City</th>
                        <th>Payment Mode</th>
                        <th>Gross Wt</th>
                        <th>Net Wt</th>
                        <th>Total Amount</th>
                        <th>Amount In</th>
                        <th>Amount Out</th>
                        <th>Pending</th>
                    </tr>
                </thead>
            </table>
            <div class="d-flex justify-content-end mt-3">
                <button type="button" id="ledger_print" class="btn btn-warning">Ledger Print</button>
            </div>
            </div>
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

    function syncFilterInputs() {
        if ($('#use_default_report').is(':checked')) {
            $('.filter-toggle').not('#use_default_report').prop('checked', false);
        }

        const toggleMap = [
            ['#use_date', ['#from_date', '#to_date']],
            ['#use_customer', ['#customer_id']],
            ['#use_city', ['#city']],
            ['#use_payment_mode', ['#payment_mode']],
            ['#use_weight', ['#weight_from', '#weight_to']],
            ['#use_amount', ['#amount_from', '#amount_to']],
        ];
        toggleMap.forEach(([toggle, inputs]) => {
            const enabled = $(toggle).is(':checked');
            inputs.forEach(sel => $(sel).prop('disabled', !enabled));
        });
    }

    function queryParams() {
        return {
            from_date: $('#use_date').is(':checked') ? $('#from_date').val() : '',
            to_date: $('#use_date').is(':checked') ? $('#to_date').val() : '',
            customer_id: $('#use_customer').is(':checked') ? $('#customer_id').val() : '',
            city: $('#use_city').is(':checked') ? $('#city').val() : '',
            payment_mode: $('#use_payment_mode').is(':checked') ? $('#payment_mode').val() : '',
            weight_from: $('#use_weight').is(':checked') ? $('#weight_from').val() : '',
            weight_to: $('#use_weight').is(':checked') ? $('#weight_to').val() : '',
            amount_from: $('#use_amount').is(':checked') ? $('#amount_from').val() : '',
            amount_to: $('#use_amount').is(':checked') ? $('#amount_to').val() : '',
        };
    }

    const table = $('#outstandingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.reports.outstanding-amount.index', $company->slug) }}",
            data: function (d) {
                Object.assign(d, queryParams());
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no', name: 'voucher_no' },
            { data: 'sale_date_fmt', name: 'sale_date' },
            { data: 'customer_name', orderable: false, searchable: false },
            { data: 'city', orderable: false, searchable: false },
            { data: 'payment_mode', name: 'payment_mode' },
            { data: 'gross_weight_fmt', orderable: false, searchable: false },
            { data: 'net_weight_fmt', orderable: false, searchable: false },
            { data: 'net_total', name: 'net_total' },
            { data: 'amount_in_fmt', orderable: false, searchable: false },
            { data: 'amount_out_fmt', orderable: false, searchable: false },
            { data: 'pending_amount_fmt', orderable: false, searchable: false },
        ]
    });

    table.on('xhr.dt', function () {
        const json = table.ajax.json() || {};
        const s = json.summary || {};
        $('#sum_vouchers').text(s.voucher_count ?? 0);
        $('#sum_gross').text((parseFloat(s.gross_weight ?? 0)).toFixed(3));
        $('#sum_net').text((parseFloat(s.net_weight ?? 0)).toFixed(3));
        $('#sum_total').text((parseFloat(s.total_amount ?? 0)).toFixed(2));
        $('#sum_in').text((parseFloat(s.amount_in ?? 0)).toFixed(2));
        $('#sum_out').text((parseFloat(s.amount_out ?? 0)).toFixed(2));
        $('#sum_pending').text((parseFloat(s.pending_amount ?? 0)).toFixed(2));
    });

    $('#filter').on('click', function () {
        table.draw();
    });

    $('#reset').on('click', function () {
        $('.filter-toggle').prop('checked', false);
        $('#use_default_report').prop('checked', true);
        syncFilterInputs();
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#customer_id').val('');
        $('#city').val('');
        $('#payment_mode').val('');
        $('#weight_from').val('');
        $('#weight_to').val('');
        $('#amount_from').val('');
        $('#amount_to').val('');
        table.draw();
    });

    $('.filter-toggle').on('change', function () {
        if (this.id === 'use_default_report' && this.checked) {
            $('.filter-toggle').not('#use_default_report').prop('checked', false);
        } else if (this.id !== 'use_default_report' && this.checked) {
            $('#use_default_report').prop('checked', false);
        } else if ($('.filter-toggle').not('#use_default_report').filter(':checked').length === 0) {
            $('#use_default_report').prop('checked', true);
        }
        syncFilterInputs();
    });
    syncFilterInputs();

    $('#excel').on('click', function () {
        window.location.href = "{{ route('company.reports.outstanding-amount.export.excel', $company->slug) }}?" + $.param(queryParams());
    });

    $('#pdf').on('click', function () {
        window.location.href = "{{ route('company.reports.outstanding-amount.export.pdf', $company->slug) }}?" + $.param(queryParams());
    });

    $('#ledger_print').on('click', function () {
        window.location.href = "{{ route('company.reports.outstanding-amount.export.ledger-pdf', $company->slug) }}?" + $.param(queryParams());
    });
});
</script>
@endpush
