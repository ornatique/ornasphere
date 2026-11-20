@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h4>Sale Voucher</h4><strong>{{ $sale->voucher_no }}</strong></div>
            <img src="{{ \App\Services\VoucherQr::dataUri('sale', $sale->id) }}" width="40" height="40" alt="Sale voucher QR">
        </div>
        <div class="card-body">
            <p>{{ $company->name }} · {{ $sale->customer?->name }} · {{ $sale->sale_date }}</p>
            <div class="d-flex flex-wrap" style="gap:12px">
                <a class="btn btn-primary" href="{{ route('company.sales.pdf', [$company->slug, $encryptedId]) }}" target="_blank" rel="noopener">View PDF</a>
                <a class="btn btn-info" href="{{ route('company.sales.index', $company->slug) }}">View Sales List</a>
            </div>
        </div>
    </div>
</div>
@endsection
