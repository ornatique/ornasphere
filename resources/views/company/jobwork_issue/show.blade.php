@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Jobwork Issue Details</h4>
            <div class="d-flex gap-2">
                <a href="{{ route('company.jobwork-issue.export-single.excel', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)]) }}" class="btn btn-success">Export Excel</a>
                <a href="{{ route('company.jobwork-issue.export-single.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)]) }}" class="btn btn-danger">Export PDF</a>
                <a href="{{ route('company.jobwork-issue.edit', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $row->id)]) }}" class="btn btn-primary">Edit</a>
                <a href="{{ route('company.jobwork-issue.index', $company->slug) }}" class="btn btn-info">Back</a>
            </div>
        </div>

        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Voucher No:</strong> {{ $row->voucher_no }}</div>
                <div class="col-md-3"><strong>Voucher Date:</strong> {{ optional($row->jobwork_date)->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>Jobworker:</strong> {{ $row->jobWorker?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>Production Step:</strong> {{ $row->productionStep?->name ?? '-' }}</div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3"><strong>Gross Wt:</strong> {{ number_format((float) ($row->gross_wt_sum ?? 0), 3, '.', '') }}</div>
                <div class="col-md-3"><strong>Net Wt:</strong> {{ number_format((float) ($row->net_wt_sum ?? 0), 3, '.', '') }}</div>
                <div class="col-md-3"><strong>Fine Wt:</strong> {{ number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', '') }}</div>
                <div class="col-md-3"><strong>Total Amt:</strong> {{ number_format((float) ($row->total_amt_sum ?? 0), 2, '.', '') }}</div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4"><strong>Created By:</strong> {{ $row->createdByUser?->name ?? '-' }}</div>
                <div class="col-md-4"><strong>Modified:</strong> {{ optional($row->updated_at)->format('d-m-Y h:i A') }}</div>
                <div class="col-md-4"><strong>Created:</strong> {{ optional($row->created_at)->format('d-m-Y h:i A') }}</div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Other Charge</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Qty</th>
                            <th>Purity</th>
                            <th>Net Purity</th>
                            <th>Total Amt</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($row->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->item?->item_name ?? '-' }}</td>
                                <td>{{ $item->otherCharge?->other_charge ?? '-' }}</td>
                                <td>{{ number_format((float) ($item->gross_wt ?? 0), 3, '.', '') }}</td>
                                <td>{{ number_format((float) ($item->other_wt ?? 0), 3, '.', '') }}</td>
                                <td>{{ number_format((float) ($item->net_wt ?? 0), 3, '.', '') }}</td>
                                <td>{{ number_format((float) ($item->fine_wt ?? 0), 3, '.', '') }}</td>
                                <td>{{ (int) ($item->qty_pcs ?? 0) }}</td>
                                <td>{{ number_format((float) ($item->purity ?? 0), 3, '.', '') }}</td>
                                <td>{{ number_format((float) ($item->net_purity ?? 0), 3, '.', '') }}</td>
                                <td>{{ number_format((float) ($item->total_amt ?? 0), 2, '.', '') }}</td>
                                <td>{{ $item->remarks ?? '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center">No item rows found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
