@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Sale Voucher Details</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('company.sales.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $sale->id)]) }}"
                   class="btn btn-danger" target="_blank">View PDF</a>
                <a href="{{ route('company.sales.index', $company->slug) }}" class="btn btn-primary">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Voucher No:</strong> {{ $sale->voucher_no }}</div>
                <div class="col-md-3"><strong>Date:</strong> {{ optional($sale->sale_date)->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>Customer:</strong> {{ optional($sale->customer)->name ?? '-' }}</div>
                <div class="col-md-3"><strong>Total:</strong> {{ number_format((float) ($sale->net_total ?? 0), 2) }}</div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>HUID</th>
                            <th>QR Code</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Total Amt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sale->saleItems as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ optional(optional($row->itemset)->item)->item_name ?? '-' }}</td>
                                <td>{{ optional($row->itemset)->HUID ?? '-' }}</td>
                                <td>{{ optional($row->itemset)->qr_code ?? '-' }}</td>
                                <td>{{ number_format((float) ($row->gross_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->other_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->net_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->fine_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->total_amount ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No sale items found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
