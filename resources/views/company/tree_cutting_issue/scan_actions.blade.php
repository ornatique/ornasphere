@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card">
    <div class="card-header">
        <h4>Tree Cutting Issue: {{ $menu['voucher_no'] }}</h4>
        <span>{{ $company->name }}</span>
    </div>
    <div class="card-body">
        <p>Choose an action for this voucher:</p>
        <div class="d-flex flex-wrap" style="gap:12px">
            @foreach($menu['actions'] as $action)
                <a class="btn btn-primary" href="{{ $action['web_url'] }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    </div>
</div></div>
@endsection
