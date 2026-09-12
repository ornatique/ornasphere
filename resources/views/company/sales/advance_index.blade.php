@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Receive / Return / Purchase Vouchers</h4>
            <a href="{{ route('company.sales.advance.create', $company->slug) }}" class="btn btn-primary">
                + Add Voucher
            </a>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('company.sales.advance.index', $company->slug) }}" class="row g-3 mb-4 align-items-end" id="advanceVoucherFilterForm">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-3">
                    <label>Party Name</label>
                    <select name="customer_id" class="form-select searchable-party-select">
                        <option value="">All Party</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ (int) request('customer_id') === (int) $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="button" id="filterAdvanceVouchers" class="btn btn-primary flex-fill">Filter</button>
                    <button type="button" id="resetAdvanceVouchers" class="btn btn-secondary flex-fill">Reset</button>
                </div>
            </form>

            <div class="table-responsive advance-voucher-list-wrap">
                <table class="table table-bordered mb-0" id="advanceVoucherTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Voucher No</th>
                            <th>Date</th>
                            <th>Party Name</th>
                            <th>Entry Type</th>
                            <th>Mode</th>
                            <th>Cash In</th>
                            <th>Cash Out</th>
                            <th>Metal Type</th>
                            <th>Metal In</th>
                            <th>Metal Out</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .advance-voucher-list-wrap {
        overflow: auto;
        scrollbar-width: thin;
    }

    .advance-voucher-list-wrap table {
        min-width: 1500px;
    }

    .advance-voucher-list-wrap th,
    .advance-voucher-list-wrap td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .searchable-party-select {
        width: 100%;
    }

    #advanceVoucherTable_wrapper .row {
        align-items: center;
        margin-left: 0;
        margin-right: 0;
    }

    #advanceVoucherTable_wrapper .dataTables_length,
    #advanceVoucherTable_wrapper .dataTables_filter,
    #advanceVoucherTable_wrapper .dataTables_info,
    #advanceVoucherTable_wrapper .dataTables_paginate {
        padding: 8px 0;
    }
</style>
@endpush

@push('scripts')
<script>
$(function() {
    $('.searchable-party-select').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Select Party',
        allowClear: true
    });

    const table = $('#advanceVoucherTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        scrollX: true,
        order: [[2, 'desc']],
        ajax: {
            url: "{{ route('company.sales.advance.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('input[name="from_date"]').val();
                d.to_date = $('input[name="to_date"]').val();
                d.customer_id = $('select[name="customer_id"]').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'voucher_no', name: 'voucher_no' },
            { data: 'voucher_date', name: 'voucher_date' },
            { data: 'customer_name', name: 'customer.name', orderable: false },
            { data: 'entry_type', name: 'entry_type' },
            { data: 'payment_mode_label', name: 'payment_mode' },
            { data: 'cash_in', name: 'cash_in', className: 'text-end' },
            { data: 'cash_out', name: 'cash_out', className: 'text-end' },
            { data: 'metal_type_label', name: 'metal_type' },
            { data: 'metal_in', name: 'metal_in', className: 'text-end' },
            { data: 'metal_out', name: 'metal_out', className: 'text-end' },
            { data: 'rate', name: 'rate', className: 'text-end' },
            { data: 'amount', name: 'amount', className: 'text-end' },
            { data: 'pdf', orderable: false, searchable: false }
        ]
    });

    $('#filterAdvanceVouchers').on('click', function () {
        table.draw();
    });

    $('#resetAdvanceVouchers').on('click', function () {
        $('input[name="from_date"]').val('');
        $('input[name="to_date"]').val('');
        $('select[name="customer_id"]').val('').trigger('change.select2');
        table.search('').draw();
    });

    $('#advanceVoucherFilterForm').on('submit', function (event) {
        event.preventDefault();
        table.draw();
    });
});
</script>
@endpush
