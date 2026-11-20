@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

<div class="card">

    {{-- HEADER --}}
    <div class="card-header d-flex justify-content-between">
        <div><h4 class="card-title">Approval Details</h4>
            <strong>{{ $approval->approval_no }}</strong>
        </div>
        <div class="d-flex gap-2">
            <a id="approvalPdfLink" href="{{ route('company.approval.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $approval->id)]) }}"
               class="btn btn-primary"
               target="_blank">
                View PDF
            </a>
            <a href="{{ route('company.approval.index', $company->slug) }}"
               class="btn btn-secondary">
                Back
            </a>
        </div>
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
            $totalGross = 0;
            $totalNet = 0;
            $totalAmount = 0;
            foreach ($approval->items as $summaryItem) {
                $totalGross += (float) ($summaryItem->gross_weight ?? 0);
                $totalNet += (float) ($summaryItem->net_weight ?? 0);
                $totalAmount += \App\Services\ApprovalItemPricing::totalAmount($summaryItem);
            }
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
        <div class="d-flex align-items-end justify-content-between gap-3 mb-2 flex-wrap">
            <div>
                <h5 class="mb-1">Items</h5>
                <div class="approval-item-counts">
                    Item Count: <strong id="approvalVisibleItemCount">0</strong>
                    <span>/</span>
                    <strong id="approvalTotalItemCount">{{ $approval->items->count() }}</strong>
                </div>
            </div>
            <div class="approval-status-filter">
                <label for="approvalItemStatusFilter"><strong>Status Filter</strong></label>
                <select id="approvalItemStatusFilter" class="form-select">
                    <option value="all" selected>All Items</option>
                    <option value="pending">Pending</option>
                    <option value="partial">Partial</option>
                    <option value="sold">Sold</option>
                    <option value="returned">Return</option>
                </select>
            </div>
        </div>

        <div class="approval-items-scroll">
            <table class="table table-bordered approval-items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>HUID</th>
                        <th>QR Code</th>
                        <th>Qty</th>
                        <th>Sold</th>
                        <th>Returned</th>
                        <th>Pending</th>
                        <th>Gross</th>
                        <th>Other</th>
                        <th>Net</th>
                        <th>Amount</th>
                        <th>Status</th><th>Sale Price</th>
                    </tr>
                </thead>

                <tbody>
                    @php $totalOther = 0; @endphp

                    @foreach($approval->items as $index => $row)

                        @php
                            $originalQty = max(1, (int) ($row->qty ?? 1));
                            $soldQty = min($originalQty, \App\Services\ApprovalItemAvailability::soldQuantity($row));
                            $returnedQty = min($originalQty - $soldQty, \App\Services\ApprovalItemAvailability::returnedQuantity($row));
                            $pendingQty = min($originalQty, \App\Services\ApprovalItemAvailability::availableQuantity($row));
                            if ($pendingQty === 0 && $soldQty === 0 && $returnedQty === 0) {
                                if (strtolower(trim((string) $row->status)) === 'sold') {
                                    $soldQty = $originalQty;
                                } elseif (strtolower(trim((string) $row->status)) === 'returned') {
                                    $returnedQty = $originalQty;
                                }
                            }
                            $displayStatus = $pendingQty === 0
                                ? ($returnedQty > 0 && $soldQty === 0 ? 'returned' : 'sold')
                                : (($soldQty > 0 || $returnedQty > 0) ? 'partial' : (strtolower((string) $row->status) === 'returned' ? 'returned' : 'pending'));
                            $totalOther += (float) ($row->other_weight ?? 0);
                        @endphp

                        <tr class="approval-item-row"
                            data-status="{{ $displayStatus }}"
                            data-gross="{{ (float) ($row->gross_weight ?? 0) }}"
                            data-other="{{ (float) ($row->other_weight ?? 0) }}"
                            data-net="{{ (float) ($row->net_weight ?? 0) }}"
                            data-amount="{{ \App\Services\ApprovalItemPricing::totalAmount($row) }}">
                            <td>{{ $index + 1 }}</td>

                            <td>{{ optional(optional($row->itemSet ?? $row->legacyItemSet)->item)->item_name ?? '-' }}</td>

                            <td>{{ $row->huid ?? optional($row->itemSet ?? $row->legacyItemSet)->HUID ?? '-' }}</td>

                            <td>{{ $row->qr_code ?? optional($row->itemSet ?? $row->legacyItemSet)->qr_code ?? '-' }}</td>
                            <td>{{ number_format($originalQty) }}</td>
                            <td>{{ number_format($soldQty) }}</td>
                            <td>{{ number_format($returnedQty) }}</td>
                            <td>{{ number_format($pendingQty) }}</td>

                            <td>{{ number_format($row->gross_weight, 3) }}</td>

                            <td>{{ number_format((float) ($row->other_weight ?? 0), 3) }}</td>

                            <td>{{ number_format($row->net_weight, 3) }}</td>

                            <td>{{ number_format(\App\Services\ApprovalItemPricing::totalAmount($row), 2) }}</td>

                            <td>
                                @if($displayStatus == 'sold')
                                    <span class="badge bg-success">Sold</span>
                                @elseif($displayStatus == 'returned')
                                    <span class="badge bg-secondary">Return</span>
                                @elseif($displayStatus == 'partial')
                                    <span class="badge bg-info">Partial</span>
                                @else
                                    <span class="badge bg-secondary">Pending</span>
                                @endif
                            </td><td>{{ \App\Services\ApprovalItemPricing::unitSalePrice($row) > 0 ? number_format(\App\Services\ApprovalItemPricing::unitSalePrice($row), 2) : "-" }}</td>
                        </tr>

                    @endforeach

                </tbody>

                {{-- TOTAL --}}
                <tfoot>
                    <tr>
                        <th colspan="8" class="text-end">Visible Total</th>
                        <th id="approvalVisibleGrossTotal">{{ number_format($totalGross, 3) }}</th>
                        <th id="approvalVisibleOtherTotal">{{ number_format($totalOther, 3) }}</th>
                        <th id="approvalVisibleNetTotal">{{ number_format($totalNet, 3) }}</th>
                        <th id="approvalVisibleAmountTotal">{{ number_format($totalAmount, 2) }}</th>
                        <th></th><th></th>
                    </tr>
                </tfoot>

            </table>
        </div>

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

@push('styles')
<style>
    .approval-items-scroll {
        max-height: calc(100vh - 330px);
        min-height: 280px;
        overflow: auto;
        border: 1px solid rgba(185, 198, 255, 0.22);
    }

    .approval-items-table {
        margin-bottom: 0;
        min-width: 1120px;
    }

    .approval-items-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #252840;
        border-color: rgba(185, 198, 255, 0.28) !important;
    }

    .approval-items-table tfoot th {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #252840;
        border-color: rgba(185, 198, 255, 0.28) !important;
    }

    .approval-items-table th,
    .approval-items-table td {
        vertical-align: middle;
        border-color: rgba(185, 198, 255, 0.22) !important;
        white-space: nowrap;
    }

    .approval-status-filter {
        width: 220px;
    }

    .approval-item-counts {
        color: #b8b8c7;
        font-size: 13px;
    }

    .approval-item-counts strong {
        color: #fff;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filter = document.getElementById('approvalItemStatusFilter');
        const rows = Array.from(document.querySelectorAll('.approval-item-row'));
        const totals = {
            gross: document.getElementById('approvalVisibleGrossTotal'),
            other: document.getElementById('approvalVisibleOtherTotal'),
            net: document.getElementById('approvalVisibleNetTotal'),
            amount: document.getElementById('approvalVisibleAmountTotal'),
        };
        const visibleItemCount = document.getElementById('approvalVisibleItemCount');

        function numberValue(row, key) {
            return Number(row.dataset[key] || 0);
        }

        function format(value, decimals) {
            return value.toLocaleString('en-IN', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function rowMatches(row, selectedStatus) {
            const status = row.dataset.status || 'pending';

            if (selectedStatus === 'all') {
                return true;
            }

            if (selectedStatus === 'partial') {
                return status === 'partial';
            }

            return status === selectedStatus;
        }

        function applyStatusFilter() {
            const selectedStatus = filter.value;
            const pdfLink = document.getElementById('approvalPdfLink');
            const pdfUrl = new URL(pdfLink.href, window.location.origin);
            pdfUrl.searchParams.set('status', selectedStatus);
            pdfLink.href = pdfUrl.toString();
            const sums = { gross: 0, other: 0, net: 0, amount: 0 };
            let count = 0;

            rows.forEach(function (row) {
                const visible = rowMatches(row, selectedStatus);
                row.classList.toggle('d-none', !visible);

                if (visible) {
                    count += 1;
                    sums.gross += numberValue(row, 'gross');
                    sums.other += numberValue(row, 'other');
                    sums.net += numberValue(row, 'net');
                    sums.amount += numberValue(row, 'amount');
                }
            });

            totals.gross.textContent = format(sums.gross, 3);
            totals.other.textContent = format(sums.other, 3);
            totals.net.textContent = format(sums.net, 3);
            totals.amount.textContent = format(sums.amount, 2);
            visibleItemCount.textContent = count.toLocaleString('en-IN');
        }

        filter.addEventListener('change', applyStatusFilter);
        applyStatusFilter();
    });
</script>
@endpush
