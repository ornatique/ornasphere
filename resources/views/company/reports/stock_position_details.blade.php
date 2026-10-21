@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
@include('company.reports.partials.branch_filter')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="card-title mb-1">Stock Position Details</h4>
                <div class="text-muted">{{ $itemName }} | {{ $stockTypeName }} @if($customerName !== '-') | {{ $customerName }} @endif</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('company.reports.stock-position.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <a href="{{ route('company.reports.stock-position.details.excel', ['slug' => $company->slug] + request()->only(['item_id', 'stock_type', 'customer_id', 'report_branches', 'image_status'])) }}" class="btn btn-info">Excel</a>
                <a href="{{ route('company.reports.stock-position.details.pdf', ['slug' => $company->slug] + request()->only(['item_id', 'stock_type', 'customer_id', 'report_branches', 'image_status'])) }}" class="btn btn-primary">PDF</a>
            </div>
        </div>
        <div class="card-body"><p>With image: <strong>{{ request()->attributes->get('stock_image_counts')['with_image'] ?? 0 }}</strong> &nbsp; Without image: <strong>{{ request()->attributes->get('stock_image_counts')['without_image'] ?? 0 }}</strong> (before image filter)</p>
<form method="GET" class="mb-3 d-flex align-items-end flex-wrap" style="gap:12px">
@foreach(request()->only(['item_id','stock_type','customer_id','report_branches']) as $key=>$value)
<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endforeach
<div><label for="image_status">Image status</label><select id="image_status" name="image_status" class="form-control">
@foreach(['all'=>'All items','with'=>'With image','without'=>'Without image'] as $value=>$label)
<option value="{{ $value }}" @selected(request('image_status','all') === $value)>{{ $label }}</option>
@endforeach
</select></div><button class="btn btn-primary">Apply filter</button></form>
            <div class="row mb-3">
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2">
                        <div class="text-muted">Qty Pcs</div>
                        <strong>{{ (int) $summary['qty_pcs'] }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2">
                        <div class="text-muted">Gross Wt</div>
                        <strong>{{ number_format((float) $summary['gross_weight'], 3) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2">
                        <div class="text-muted">Net Wt</div>
                        <strong>{{ number_format((float) $summary['net_weight'], 3) }}</strong>
                    </div>
                </div>
                <div class="col-md-2 col-6 mb-2">
                    <div class="border rounded p-2">
                        <div class="text-muted">Fine Wt</div>
                        <strong>{{ number_format((float) $summary['fine_weight'], 3) }}</strong>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="stockDetailTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Label Code</th>
                            <th>Item</th>
                            <th>Stock Type</th>
                            <th>Qty Pcs</th>
                            <th>Gross Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                        <th>Image status</th><th>Branch</th></tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row->source_date ? \Carbon\Carbon::parse($row->source_date)->format('d-m-Y h:i A') : '-' }}</td>
                                <td>{{ $row->label_code ?? '-' }}</td>
                                <td>{{ $row->item_name ?? '-' }}</td>
                                <td>{{ $row->stock_type_name ?? '-' }}</td>
                                <td>{{ (int) ($row->qty_pcs ?? 0) }}</td>
                                <td>{{ number_format((float) ($row->gross_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->net_weight ?? 0), 3) }}</td>
                                <td>{{ number_format((float) ($row->fine_weight ?? 0), 3) }}</td>
                            <td>{{ $row->has_image ? 'With image' : 'Without image' }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id ?? null) }}</td></tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center">No stock details found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
@media print {
    @page { size: A4 portrait; margin: 7mm 5mm; }
}
</style>
<script>
$(function () {
    @if($rows->isNotEmpty())
    $('#stockDetailTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']]
    });
    @endif
});
</script>
@endpush
