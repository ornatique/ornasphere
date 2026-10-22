@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
@include('company.reports.partials.branch_filter')
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Outstanding Amount Report</h4>
        </div>
        <div class="card-body">
            <style>
                .outstanding-report .report-filter-panel {
                    border: 1px solid #3a3f6d;
                    border-radius: 8px;
                    padding: 16px;
                    background: rgba(255, 255, 255, 0.018);
                    margin-bottom: 16px;
                }
                .outstanding-report .filter-section-title {
                    color: #ffffff;
                    font-size: 14px;
                    font-weight: 700;
                    margin-bottom: 12px;
                }
                .outstanding-report .report-filter-grid {
                    display: grid;
                    grid-template-columns: repeat(12, minmax(0, 1fr));
                    gap: 14px;
                    align-items: stretch;
                }
                .outstanding-report .filter-card {
                    border: 1px solid rgba(70, 75, 124, 0.7);
                    border-radius: 8px;
                    padding: 12px;
                    background: rgba(24, 26, 48, 0.42);
                    min-height: 112px;
                }
                .outstanding-report .filter-card.is-compact {
                    min-height: auto;
                }
                .outstanding-report .filter-span-2 {
                    grid-column: span 2;
                }
                .outstanding-report .filter-span-3 {
                    grid-column: span 3;
                }
                .outstanding-report .filter-span-4 {
                    grid-column: span 4;
                }
                .outstanding-report .filter-actions-card {
                    grid-column: span 2;
                    display: flex;
                    flex-direction: column;
                    justify-content: end;
                    gap: 10px;
                }
                .outstanding-report .filter-action-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                }
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
                    padding-left: 0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .outstanding-report .filter-toggle-row .form-check-input {
                    float: none;
                    margin-left: 0;
                    margin-top: 0;
                    position: static;
                    flex: 0 0 auto;
                }
                .outstanding-report .filter-toggle-row .form-check-label {
                    color: #cbd3f6;
                    font-weight: 600;
                }
                .outstanding-report .field-label {
                    color: #c4cbed;
                    display: block;
                    font-size: 13px;
                }
                .outstanding-report .form-control,
                .outstanding-report .form-select,
                .outstanding-report .select2-container--bootstrap4 .select2-selection {
                    min-height: 44px;
                    border-color: #464b7c;
                    background: #30325b;
                    color: #ffffff;
                }
                .outstanding-report .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
                    color: #ffffff;
                    line-height: 42px;
                }
                .outstanding-report .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
                    height: 42px;
                }
                .outstanding-report .select2-dropdown {
                    background: #252744;
                    border-color: #464b7c;
                    color: #ffffff;
                }
                .outstanding-report .select2-container--bootstrap4 .select2-results__option {
                    color: #ffffff;
                    padding: 8px 12px;
                }
                .outstanding-report .select2-container--bootstrap4 .select2-results__option--highlighted,
                .outstanding-report .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
                    background: #1877f2;
                    color: #ffffff;
                }
                .outstanding-report .select2-container--bootstrap4 .select2-search__field {
                    background: #30325b;
                    border-color: #464b7c;
                    color: #ffffff;
                }
                .outstanding-report .form-control:disabled,
                .outstanding-report .form-select:disabled,
                .outstanding-report .select2-container--disabled .select2-selection {
                    background: #2e3158 !important;
                    color: #97a0c7 !important;
                    opacity: 1;
                    border-color: #3d4372;
                    cursor: not-allowed;
                }
                .outstanding-report .select2-container--disabled .select2-selection__rendered {
                    color: #97a0c7 !important;
                }
                .outstanding-report .action-buttons {
                    display: contents;
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
                .outstanding-report .table-wrap {
                    border: 1px solid #34395f;
                    border-radius: 8px;
                    overflow: hidden;
                    background: rgba(255, 255, 255, 0.01);
                }
                .outstanding-report #outstandingTable {
                    margin-bottom: 0 !important;
                }
                .outstanding-report #outstandingTable thead th {
                    background: #252744;
                    vertical-align: middle;
                    white-space: nowrap;
                }
                .outstanding-report #outstandingTable tbody td {
                    vertical-align: middle;
                }
                .outstanding-report .report-actions {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                    margin-top: 14px;
                }
                @media (max-width: 991.98px) {
                    .outstanding-report .report-filter-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                    .outstanding-report .filter-span-2,
                    .outstanding-report .filter-span-3,
                    .outstanding-report .filter-span-4,
                    .outstanding-report .filter-actions-card {
                        grid-column: span 1;
                    }
                }
                @media (max-width: 575.98px) {
                    .outstanding-report .report-filter-grid {
                        grid-template-columns: 1fr;
                    }
                    .outstanding-report .filter-span-2,
                    .outstanding-report .filter-span-3,
                    .outstanding-report .filter-span-4,
                    .outstanding-report .filter-actions-card {
                        grid-column: span 1;
                    }
                }
            </style>

            <div class="outstanding-report">
            <div class="report-filter-panel">
                <div class="filter-section-title">Report Filters</div>
                <div class="report-filter-grid">
                    <div class="filter-card filter-span-2 is-compact">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_default_report" checked>
                                <label class="form-check-label" for="use_default_report">Default Report</label>
                            </div>
                        </div>
                    </div>
                    <div class="filter-card filter-span-4 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_date">
                                <label class="form-check-label" for="use_date">Use Date</label>
                            </div>
                        </div>
                        <div class="range-grid">
                            <div class="input-block">
                                <label class="field-label">From Date</label>
                                <input type="date" id="from_date" class="form-control filter-input" disabled>
                            </div>
                            <div class="input-block">
                                <label class="field-label">To Date</label>
                                <input type="date" id="to_date" class="form-control filter-input" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="filter-card filter-span-3 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_customer">
                                <label class="form-check-label" for="use_customer">Use Party</label>
                            </div>
                        </div>
                        <label class="field-label">Party</label>
                        <select id="customer_id" class="form-select filter-input" disabled>
                            <option value="">All Parties</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-card filter-span-3 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_city">
                                <label class="form-check-label" for="use_city">Use City</label>
                            </div>
                        </div>
                        <label class="field-label">City</label>
                        <select id="city" class="form-select filter-input" disabled>
                            <option value="">All Cities</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}">{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-card filter-span-3 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_payment_mode">
                                <label class="form-check-label" for="use_payment_mode">Use Mode</label>
                            </div>
                        </div>
                        <label class="field-label">Payment Mode</label>
                        <select id="payment_mode" class="form-select filter-input" disabled>
                            <option value="">All Modes</option>
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                            <option value="upi">UPI</option>
                            <option value="bank">Bank</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="filter-card filter-span-4 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_weight">
                                <label class="form-check-label" for="use_weight">Use Weight</label>
                            </div>
                        </div>
                        <div class="range-grid">
                            <div class="input-block">
                                <label class="field-label">Weight From</label>
                                <input type="number" step="0.001" id="weight_from" class="form-control filter-input" placeholder="0.000" disabled>
                            </div>
                            <div class="input-block">
                                <label class="field-label">Weight To</label>
                                <input type="number" step="0.001" id="weight_to" class="form-control filter-input" placeholder="0.000" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="filter-card filter-span-3 filter-block">
                        <div class="filter-toggle-row">
                            <div class="form-check">
                                <input class="form-check-input filter-toggle" type="checkbox" id="use_amount">
                                <label class="form-check-label" for="use_amount">Use Amount</label>
                            </div>
                        </div>
                        <div class="range-grid">
                            <div class="input-block">
                                <label class="field-label">Amount From</label>
                                <input type="number" step="0.01" id="amount_from" class="form-control filter-input" placeholder="0.00" disabled>
                            </div>
                            <div class="input-block">
                                <label class="field-label">Amount To</label>
                                <input type="number" step="0.01" id="amount_to" class="form-control filter-input" placeholder="0.00" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="filter-card filter-actions-card">
                        <div class="filter-action-row">
                            <button id="filter" class="btn btn-success">Filter</button>
                            <button id="reset" class="btn btn-primary">Reset</button>
                        </div>
                        <div class="filter-action-row">
                            <button id="excel" class="btn btn-primary">Excel</button>
                            <button id="pdf" class="btn btn-danger">PDF</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 summary-cards">
                    <div class="summary-card"><span class="title">Vouchers</span><span class="value" id="sum_vouchers">0</span></div>
                    <div class="summary-card"><span class="title">Gross Wt</span><span class="value" id="sum_gross">0.000</span></div>
                    <div class="summary-card"><span class="title">Net Wt</span><span class="value" id="sum_net">0.000</span></div>
                    <div class="summary-card"><span class="title">Fine Wt</span><span class="value" id="sum_fine">0.000</span></div>
                    <div class="summary-card"><span class="title">Metal Amt</span><span class="value" id="sum_metal_amount">0.00</span></div>
                    <div class="summary-card"><span class="title">Labour Amt</span><span class="value" id="sum_labour_amount">0.00</span></div>
                    <div class="summary-card"><span class="title">Metal Dr</span><span class="value" id="sum_metal_debit">0.000</span></div>
                    <div class="summary-card"><span class="title">Total Amount</span><span class="value" id="sum_total">0.00</span></div>
                    <div class="summary-card"><span class="title">Amount In</span><span class="value" id="sum_in">0.00</span></div>
                    <div class="summary-card"><span class="title">Amount Out</span><span class="value" id="sum_out">0.00</span></div>
                    <div class="summary-card"><span class="title">Pending</span><span class="value" id="sum_pending">0.00</span></div>
                </div>
            </div>

            <div class="table-wrap">
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
                            <th>Fine Wt</th>
                            <th>Metal Amt</th>
                            <th>Labour Amt</th>
                            <th>Metal Dr</th>
                            <th>Total Amount</th>
                            <th>Amount In</th>
                            <th>Amount Out</th>
                            <th>Pending</th>
                        <th>Branch</th></tr>
                    </thead>
                </table>
            </div>
            <div class="report-actions">
                <div></div>
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
    if ($.fn.select2) {
        const dropdownParent = $('.outstanding-report');
        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Parties',
            allowClear: true,
            dropdownParent
        });

        $('#city').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Cities',
            allowClear: true,
            dropdownParent
        });

        $('#payment_mode').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Modes',
            allowClear: true,
            dropdownParent
        });
    }

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
            inputs.forEach(sel => $(sel).prop('disabled', !enabled).trigger('change.select2'));
        });
    }

    function queryParams() {
        return {
            use_default_report: $('#use_default_report').is(':checked') ? 1 : 0,
            use_date: $('#use_date').is(':checked') ? 1 : 0,
            use_customer: $('#use_customer').is(':checked') ? 1 : 0,
            use_city: $('#use_city').is(':checked') ? 1 : 0,
            use_payment_mode: $('#use_payment_mode').is(':checked') ? 1 : 0,
            use_weight: $('#use_weight').is(':checked') ? 1 : 0,
            use_amount: $('#use_amount').is(':checked') ? 1 : 0,
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
            { data: 'fine_weight_fmt', orderable: false, searchable: false },
            { data: 'metal_amount_fmt', orderable: false, searchable: false },
            { data: 'labour_amount_fmt', orderable: false, searchable: false },
            { data: 'metal_debit_fmt', orderable: false, searchable: false },
            { data: 'net_total', name: 'net_total' },
            { data: 'amount_in_fmt', orderable: false, searchable: false },
            { data: 'amount_out_fmt', orderable: false, searchable: false },
            { data: 'pending_amount_fmt', orderable: false, searchable: false },
            { data: 'branch_name', orderable: false, searchable: false, defaultContent: '' },
        ]
    });

    table.on('xhr.dt', function () {
        const json = table.ajax.json() || {};
        const s = json.summary || {};
        $('#sum_vouchers').text(s.voucher_count ?? 0);
        $('#sum_gross').text((parseFloat(s.gross_weight ?? 0)).toFixed(3));
        $('#sum_net').text((parseFloat(s.net_weight ?? 0)).toFixed(3));
        $('#sum_fine').text((parseFloat(s.fine_weight ?? 0)).toFixed(3));
        $('#sum_metal_amount').text((parseFloat(s.metal_amount ?? 0)).toFixed(2));
        $('#sum_labour_amount').text((parseFloat(s.labour_amount ?? 0)).toFixed(2));
        $('#sum_metal_debit').text((parseFloat(s.metal_debit ?? 0)).toFixed(3));
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
        $('#customer_id').val('').trigger('change.select2');
        $('#city').val('').trigger('change.select2');
        $('#payment_mode').val('').trigger('change.select2');
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
        window.location.href = window.reportBranchUrl("{{ route('company.reports.outstanding-amount.export.excel', $company->slug) }}?" + $.param(queryParams()));
    });

    $('#pdf').on('click', function () {
        window.location.href = window.reportBranchUrl("{{ route('company.reports.outstanding-amount.export.pdf', $company->slug) }}?" + $.param(queryParams()));
    });

    $('#ledger_print').on('click', function () {
        window.location.href = window.reportBranchUrl("{{ route('company.reports.outstanding-amount.export.ledger-pdf', $company->slug) }}?" + $.param(queryParams()));
    });
});
</script>
@endpush
