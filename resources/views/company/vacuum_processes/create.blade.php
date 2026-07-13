@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">{{ isset($data) ? 'Edit Vacuum Process' : 'Create Vacuum Process' }}</h4>
        </div>

        <form method="POST"
            action="{{ isset($data) ? route('company.vacuum-processes.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.vacuum-processes.store', $company->slug) }}">
            @csrf

            <div class="card-body">
                @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $data->name ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">{{ isset($data) ? 'Update' : 'Save' }}</button>
                <a href="{{ route('company.vacuum-processes.index', $company->slug) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
