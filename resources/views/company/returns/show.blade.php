@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Return Voucher Details</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('company.returns.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $return->id)]) }}"
                   class="btn btn-danger" target="_blank">View PDF</a>
                <a href="{{ route('company.returns.index', $company->slug) }}" class="btn btn-primary">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Voucher No:</strong> {{ $return->return_voucher_no }}</div>
                <div class="col-md-3"><strong>Date:</strong> {{ optional($return->return_date)->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>Source:</strong> {{ ucfirst((string) ($return->source_type ?? 'sale')) }}</div>
                <div class="col-md-3"><strong>Total:</strong> {{ number_format((float) ($return->return_total ?? 0), 2) }}</div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Customer:</strong>
                    {{ optional($return->sale?->customer)->name ?? optional($return->approval?->customer)->name ?? '-' }}
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>HUID</th>
                            <th>QR Code</th>
                            <th class="text-end">Return Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($return->items as $index => $row)
                            @php
                                $itemset = $row->itemSet ?? optional($row->saleItem)->itemset;
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ optional(optional($itemset)->item)->item_name ?? '-' }}</td>
                                <td>{{ optional($itemset)->HUID ?? '-' }}</td>
                                <td>{{ optional($itemset)->qr_code ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) ($row->return_amount ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No return items found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
