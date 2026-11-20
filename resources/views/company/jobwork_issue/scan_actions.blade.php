@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><h4>Jobwork Issue</h4><strong>{{ $row->voucher_no }}</strong></div>
        <img src="{{ \App\Services\VoucherQr::dataUri('jobwork-issue', $row->id) }}" width="40" height="40" alt="Jobwork issue QR">
    </div>
    <div class="card-body">
        <p>{{ $company->name }}</p>
        <div class="d-flex flex-wrap" style="gap:12px">
            <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route('company.jobwork-issue.export-single.pdf', [$company->slug, $encryptedId]) }}">View PDF</a>
            <a class="btn btn-info" href="{{ route('company.jobwork-issue.index', [$company->slug, 'voucher_id'=>$row->id]) }}">View Issue List</a>
            @if($receiveEnabled)
            <a class="btn btn-secondary" href="{{ route('company.jobwork-receive.index', [$company->slug, 'issue_id'=>$row->id]) }}">View Receive</a>
            @else
            <button class="btn btn-secondary" disabled title="You do not have access to Jobwork Receive.">View Receive</button>
            @endif
        </div>
    </div>
</div></div>
@endsection
