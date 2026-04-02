@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

<div class="card">

    {{-- HEADER --}}
    <div class="card-header d-flex justify-content-between">
        <h4 class="card-title">Approval Details</h4>

        <a href="{{ route('company.approval.index', $company->slug) }}"
           class="btn btn-secondary">
            Back
        </a>
    </div>

    <div class="card-body">

        {{-- BASIC INFO --}}
        <div class="row mb-4">

            <div class="col-md-4">
                <label><strong>Customer</strong></label>
                <p>{{ $approval->customer->name ?? '-' }}</p>
            </div>

            <div class="col-md-4">
                <label><strong>Date</strong></label>
                <p>{{ $approval->approval_date ?? '-' }}</p>
            </div>

            <div class="col-md-4">
                <label><strong>Status</strong></label>
                <p>
                    @if($approval->status == 'pending')
                        <span class="badge bg-warning">Pending</span>
                    @elseif($approval->status == 'partial')
                        <span class="badge bg-info">Partial</span>
                    @else
                        <span class="badge bg-success">Completed</span>
                    @endif
                </p>
            </div>

        </div>

        @php
            $totalGross = (float) $approval->items->sum('gross_weight');
            $totalNet = (float) $approval->items->sum('net_weight');
            $totalAmount = (float) $approval->items->sum('total_amount');
        @endphp

        <div class="row mb-3">
            <div class="col-md-4">
                <label><strong>Total Gross Wt</strong></label>
                <p>{{ number_format($totalGross, 3) }}</p>
            </div>
            <div class="col-md-4">
                <label><strong>Total Net Wt</strong></label>
                <p>{{ number_format($totalNet, 3) }}</p>
            </div>
            <div class="col-md-4">
                <label><strong>Total Amount</strong></label>
                <p>{{ number_format($totalAmount, 2) }}</p>
            </div>
        </div>

        {{-- ITEMS TABLE --}}
        <h5>Items</h5>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>HUID</th>
                    <th>QR Code</th>
                    <th>Gross</th>
                    <th>Other</th>
                    <th>Net</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @php $totalOther = 0; @endphp

                @foreach($approval->items as $index => $row)

                    @php
                        $totalOther += (float) ($row->other_weight ?? 0);
                    @endphp

                    <tr>
                        <td>{{ $index + 1 }}</td>

                        <td>{{ optional(optional($row->itemSet ?? $row->legacyItemSet)->item)->item_name ?? '-' }}</td>

                        <td>{{ $row->huid ?? optional($row->itemSet ?? $row->legacyItemSet)->HUID ?? '-' }}</td>

                        <td>{{ $row->qr_code ?? optional($row->itemSet ?? $row->legacyItemSet)->qr_code ?? '-' }}</td>

                        <td>{{ number_format($row->gross_weight, 3) }}</td>

                        <td>{{ number_format((float) ($row->other_weight ?? 0), 3) }}</td>

                        <td>{{ number_format($row->net_weight, 3) }}</td>

                        <td>{{ number_format((float) ($row->total_amount ?? 0), 2) }}</td>

                        <td>
                            @if($row->status == 'sold')
                                <span class="badge bg-success">Sold</span>
                            @elseif($row->status == 'returned')
                                <span class="badge bg-secondary">Return</span>
                            @else 
                                <span class="badge bg-secondary">Pending</span>
                            @endif
                        </td>
                    </tr>

                @endforeach

            </tbody>

            {{-- TOTAL --}}
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Total</th>
                    <th>{{ number_format($totalGross, 3) }}</th>
                    <th>{{ number_format($totalOther, 3) }}</th>
                    <th>{{ number_format($totalNet, 3) }}</th>
                    <th>{{ number_format($totalAmount, 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>

        </table>

        {{-- ACTION BUTTONS --}}
        <!-- <div class="mt-3">

            <button class="btn btn-success">
                Sell Selected
            </button>

            <button class="btn btn-danger">
                Return Remaining
            </button>

        </div> -->

    </div>

</div>

</div>
@endsection
