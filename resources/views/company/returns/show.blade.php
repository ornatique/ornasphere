@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Approval Return Details</h4>
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
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($return->items as $index => $row)
                            @php
                                $approvalItem = $row->approvalItem;
                                $itemset = $row->itemSet
                                    ?? optional($row->saleItem)->itemset
                                    ?? optional($approvalItem)->itemSet
                                    ?? optional($approvalItem)->legacyItemSet;
                                $itemName = optional(optional($itemset)->item)->item_name
                                    ?? optional(optional($approvalItem)->item)->item_name
                                    ?? '-';
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $itemName }}</td>
                                <td>{{ optional($itemset)->HUID ?? '-' }}</td>
                                <td>{{ optional($itemset)->qr_code ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) ($row->return_amount ?? 0), 2) }}</td>
                                <td class="text-center">
                                    <form action="{{ route('company.returns.items.destroy', [
                                            $company->slug,
                                            \Illuminate\Support\Facades\Crypt::encryptString((string) $return->id),
                                            \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)
                                        ]) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Remove this item from approval return and move it back to approval?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No return items found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
