@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Permission</h3>
        </div>
        <div class="card-body">
             <h4> Permission Name</h4>
            <form method="POST"
                action="{{ route('company.permissions.update', [$company->slug, encrypt($permission->id)]) }}">
                @csrf @method('PUT')
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <input type="text"
                                name="name"
                                class="form-control mb-3"
                                value="{{ $permission->name }}"
                                required>
                        </div>
                    </div>
                </div>

        </div>
        <div class="card-footer text-end">
            <a href="{{ route('company.permissions.index', $company->slug) }}"
                class="btn btn-info">
                Back
            </a>

            <button class="btn btn-success">
                Upsate
            </button>
        </div>
        </form>
    </div>
</div>
@endsection