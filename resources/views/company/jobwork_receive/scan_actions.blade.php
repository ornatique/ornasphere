@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><h4>Jobwork Receive</h4><strong>{{ $receive->receive_no }}</strong></div>
        <img src="{{ \App\Services\VoucherQr::dataUri('jobwork-receive', $receive->id) }}" width="40" height="40" alt="Receive QR">
    </div>
    <div class="card-body"><p>{{ $company->name }}</p><div class="d-flex flex-wrap" style="gap:12px">
        @if($menu['actions'][0]['enabled'])
        <a class="btn btn-info" href="{{ route('company.jobwork-issue.show', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string)$menu['issue_id'])]) }}">View Issue Details</a>
        @else
        <button class="btn btn-info" disabled title="{{ $menu['actions'][0]['reason'] }}">View Issue Details</button>
        <span>{{ $menu['actions'][0]['reason'] }}</span>
        @endif
        <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route($receive->jobwork_issue_id ? 'company.jobwork-receive.pdf' : 'company.jobwork-receive.direct.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string)($receive->jobwork_issue_id ?: $receive->id))]) }}">View PDF</a>
        <a class="btn btn-secondary" href="{{ route('company.jobwork-receive.index', array_merge(['slug'=>$company->slug], $menu['issue_id'] ? ['issue_id'=>$menu['issue_id']] : [])) }}">View Receive List</a>
    </div></div>
</div></div>
@endsection
