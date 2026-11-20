@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h4>Approval Return</h4><strong>{{ $return->return_voucher_no }}</strong></div>
            <img src="{{ \App\Services\VoucherQr::dataUri('sale-return', $return->id) }}" width="40" height="40" alt="Approval return QR">
        </div>
        <div class="card-body">
            <p>{{ $company->name }} · {{ $return->return_date }}</p>
            <div class="d-flex flex-wrap" style="gap:12px">
                <a class="btn btn-primary" href="{{ route('company.returns.pdf', [$company->slug, $encryptedReturnId]) }}" target="_blank" rel="noopener">View PDF</a>
                <a class="btn btn-info" href="{{ route('company.returns.index', $company->slug) }}">View Return List</a>
                <a class="btn btn-secondary" href="#return-details">View Return Details</a>
            </div>
            <div id="return-details" class="mt-4">
                <h5>Return Details</h5>
                <p>Returned items: {{ $return->items->count() }}</p>
                <p>Total: {{ number_format((float)$return->return_total, 2) }}</p>
                @if($return->remarks)<p>{{ $return->remarks }}</p>@endif
                <div class="table-responsive"><table class="table table-bordered">
                    <thead><tr><th>Returned item ID</th><th>Return amount</th></tr></thead>
                    <tbody>@foreach($return->items as $item)<tr><td>{{ $item->id }}</td><td>{{ number_format((float)$item->return_amount, 2) }}</td></tr>@endforeach</tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
@endsection
