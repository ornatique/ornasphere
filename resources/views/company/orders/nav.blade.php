<div class="mb-3 d-flex flex-wrap" style="gap:8px">
    <a class="btn btn-outline-primary" href="{{ route('company.orders.index', $company->slug) }}">Customer Orders</a>
    <a class="btn btn-outline-primary" href="{{ route('company.cad.index', $company->slug) }}">CAD Designs</a>
    <a class="btn btn-outline-primary" href="{{ route('company.rpt.index', $company->slug) }}">RPT Batches</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
