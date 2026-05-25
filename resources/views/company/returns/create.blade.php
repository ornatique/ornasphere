@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-body">

            <h4 class="card-title">Sales Return Voucher</h4>

            <p>
                Customer: {{ $sale->customer->name }} <br>
                Date: {{ $sale->sale_date }}
            </p>

            <form method="POST" action="{{ route('company.sales.return.store', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $sale->id)]) }}">
                @csrf

                <div class="mb-3">
                    <label>Voucher Remarks</label>
                    <textarea name="voucher_remarks" class="form-control" rows="2" placeholder="Enter remarks for this return">{{ old('voucher_remarks') }}</textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Refund Paid Amount</label>
                        <input type="number" step="0.01" min="0" name="refund_paid_amount" class="form-control" value="{{ old('refund_paid_amount', 0) }}">
                    </div>
                    <div class="col-md-3">
                        <label>Refund Mode</label>
                        <select name="refund_mode" class="form-select">
                            <option value="">Select Mode</option>
                            <option value="cash">Cash</option>
                            <option value="online">Online</option>
                            <option value="upi">UPI</option>
                            <option value="bank">Bank</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Refund Ref No</label>
                        <input type="text" name="refund_reference" class="form-control" value="{{ old('refund_reference') }}" placeholder="Txn/Ref No">
                    </div>
                    <div class="col-md-3">
                        <label>Refund Note</label>
                        <input type="text" name="refund_note" class="form-control" value="{{ old('refund_note') }}" placeholder="Optional">
                    </div>
                </div>

                @php
                    $filteredItems = $sale->saleItems;
                    if (!empty($selectedSaleItemId)) {
                        $filteredItems = $sale->saleItems->where('id', (int) $selectedSaleItemId);
                    }

                    $totalNet = 0;
                    $totalAmount = 0;
                @endphp

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Label</th>
                                <th>Gross Wt</th>
                                <th>Other Wt</th>
                                <th>Net Wt</th>
                                <th>Purity</th>
                                <th>Waste %</th>
                                <th>Net Purity</th>
                                <th>Fine Wt</th>
                                <th>Metal Rate</th>
                                <th>Metal Amt</th>
                                <th>Labour Rate</th>
                                <th>Labour Amt</th>
                                <th>Other Amt</th>
                                <th>Total Amt</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($filteredItems as $item)
                                @php
                                    $totalNet += (float) $item->net_weight;
                                    $totalAmount += (float) $item->total_amount;
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" name="return_items[]" value="{{ $item->id }}" {{ !empty($selectedSaleItemId) ? 'checked' : '' }}>
                                    </td>
                                    <td>
                                        <strong>{{ $item->itemset->HUID ?? '-' }}</strong><br>
                                        <small>{{ $item->itemset->qr_code ?? '-' }}</small><br>
                                        <small>{{ $item->itemset->item->item_name ?? '-' }}</small>
                                    </td>
                                    <td>{{ number_format((float) $item->gross_weight, 3) }}</td>
                                    <td>{{ number_format((float) ($item->other_weight ?? 0), 3) }}</td>
                                    <td>{{ number_format((float) $item->net_weight, 3) }}</td>
                                    <td>{{ number_format((float) ($item->purity ?? 0), 3) }}</td>
                                    <td>{{ number_format((float) ($item->waste_percent ?? 0), 3) }}</td>
                                    <td>{{ number_format((float) ($item->net_purity ?? 0), 3) }}</td>
                                    <td>{{ number_format((float) ($item->fine_weight ?? 0), 3) }}</td>
                                    <td>{{ number_format((float) ($item->metal_rate ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($item->metal_amount ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($item->labour_rate ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($item->labour_amount ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) ($item->other_amount ?? 0), 2) }}</td>
                                    <td>{{ number_format((float) $item->total_amount, 2) }}</td>
                                    <td>
                                        <input type="text"
                                            name="remarks[{{ $item->id }}]"
                                            class="form-control"
                                            value="{{ old('remarks.' . $item->id, $item->remarks ?? '') }}"
                                            placeholder="Remarks">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="text-center">No items found</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Totals</th>
                                <th>{{ number_format($totalNet, 3) }}</th>
                                <th colspan="9"></th>
                                <th>{{ number_format($totalAmount, 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button class="btn btn-danger">Process Return</button>
            </form>

        </div>
    </div>
</div>
@endsection
