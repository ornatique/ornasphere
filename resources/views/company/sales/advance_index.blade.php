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
            <form method="GET" action="{{ route('company.sales.advance.index', $company->slug) }}" class="row g-3 mb-4 align-items-end">
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
                    <button class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('company.sales.advance.index', $company->slug) }}" class="btn btn-secondary flex-fill">Reset</a>
                </div>
            </form>

            <div class="table-responsive advance-voucher-list-wrap">
                <table class="table table-bordered mb-0">
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
                    <tbody>
                        @forelse($vouchers as $voucher)
                            <tr>
                                <td>{{ $loop->iteration + (($vouchers->currentPage() - 1) * $vouchers->perPage()) }}</td>
                                <td>{{ $voucher->voucher_no }}</td>
                                <td>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
                                <td>{{ optional($voucher->customer)->name ?? '-' }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $voucher->entry_type)) }}</td>
                                <td>{{ $voucher->payment_mode ? ucfirst($voucher->payment_mode) : '-' }}</td>
                                <td>{{ number_format((float) $voucher->cash_in, 2) }}</td>
                                <td>{{ number_format((float) $voucher->cash_out, 2) }}</td>
                                <td>{{ $voucher->metal_type ? ucfirst($voucher->metal_type) : '-' }}</td>
                                <td>{{ number_format((float) $voucher->metal_in, 3) }}</td>
                                <td>{{ number_format((float) $voucher->metal_out, 3) }}</td>
                                <td>{{ number_format((float) $voucher->rate, 2) }}</td>
                                <td>{{ number_format((float) $voucher->amount, 2) }}</td>
                                <td>
                                    <a class="btn btn-danger btn-sm" target="_blank" href="{{ route('company.sales.advance.voucher.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center">No vouchers available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $vouchers->links() }}
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

    .advance-voucher-list-wrap th {
        white-space: nowrap;
    }

    .searchable-party-select {
        width: 100%;
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
});
</script>
@endpush
