@extends('company_layout.admin')
@section('content')
<div class="content-wrapper"><div class="card">
    <div class="card-header"><h4>Casting Voucher: {{ $menu['voucher_no'] }}</h4><span>{{ $company->name }}</span></div>
    <div class="card-body">
        <p>Select a stage to open this voucher. Review the details before saving.</p>
        <div class="row">
        @foreach($menu['actions'] as $action)
            @if($action['key'] !== 'export_excel')
            <div class="col-md-6 mb-3">
                @if($action['enabled'] && $action['web_url'])
                <a class="btn btn-primary btn-block" href="{{ $action['web_url'] }}">{{ $action['label'] }}</a>
                @else
                <button class="btn btn-secondary btn-block" disabled>{{ $action['label'] }}</button>
                <small>{{ $action['reason'] }}</small>
                @endif
            </div>
            @endif
        @endforeach
        </div>
    </div>
</div></div>
@endsection
