@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">{{ isset($data) ? 'Edit Production Cost' : 'Create Production Cost' }}</h4>
        </div>

        <div class="card-body">
            <form method="POST"
                action="{{ isset($data) ? route('company.production-cost.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.production-cost.store', $company->slug) }}">
                @csrf

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
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text"
                                name="name"
                                class="form-control"
                                required
                                value="{{ old('name', $data->name ?? '') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="1" {{ (string) old('status', isset($data) ? (int) $data->status : 1) === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ (string) old('status', isset($data) ? (int) $data->status : 1) === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-success">
                {{ isset($data) ? 'Update' : 'Save' }}
            </button>

            <a href="{{ route('company.production-cost.index', $company->slug) }}" class="btn btn-secondary">
                Cancel
            </a>
        </div>
        </form>
    </div>
</div>
@endsection
